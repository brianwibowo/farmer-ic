<?php

namespace App\AI\Services;

use App\AI\Contracts\LlmGateway;
use App\AI\Exceptions\LlmException;
use App\Models\AiAttachment;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiUsageEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiChatService
{
    public function __construct(
        private readonly LlmGateway $gateway,
        private readonly ModelRouter $models,
        private readonly KnowledgeService $knowledge,
        private readonly PromptAssembler $prompts,
        private readonly AttachmentService $attachments,
    ) {}

    public function createConversation(User $user, Team $team, ?string $title = null): AiConversation
    {
        return AiConversation::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => $title,
            'status' => 'active',
            'knowledge_snapshot' => $this->knowledge->snapshot($team),
        ]);
    }

    /** @param array<int, UploadedFile> $files */
    public function createUserMessage(AiConversation $conversation, User $user, ?string $content, array $files = []): AiMessage
    {
        $attachments = $this->attachments->store($conversation, $user, $files);
        $content = trim((string) $content);

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $content !== '' ? $content : null,
            'status' => 'completed',
        ]);

        foreach ($attachments as $attachment) {
            $attachment->forceFill(['ai_message_id' => $message->id])->saveQuietly();
        }

        $conversation->forceFill(['last_message_at' => now()])->save();

        if (blank($conversation->title) && $content !== '') {
            $conversation->forceFill([
                'title' => Str::limit($content, 72),
            ])->save();
        }

        return $message->load('attachments');
    }

    /**
     * @return \Generator<int, array{event: string, data: array<string, mixed>}, mixed, void>
     */
    public function streamReply(AiConversation $conversation, bool $forceInfographic = false): \Generator
    {
        $userMessage = $conversation->messages()->latest('id')->first();
        if (! $userMessage) {
            return;
        }

        $userText = (string) ($userMessage->content ?? '');
        $isInfographic = $forceInfographic
            || (bool) preg_match('/\b(infografis|diagram visual|bagan alur|poster visual|diagram smart farming)\b/i', $userText);

        if ($isInfographic) {
            yield from $this->streamInfographicReply($conversation, $userMessage);

            return;
        }

        $hasVision = $userMessage->attachments->contains(fn ($attachment): bool => $attachment->kind === 'image');
        $prompt = $this->prompts->build($conversation, isInfographic: false);
        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => 'streaming',
            'content' => '',
        ]);
        $startedAt = microtime(true);
        $models = $this->models->candidates($hasVision);
        $content = '';
        $lastError = null;
        $lastChunk = [];

        yield ['event' => 'message.start', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
        ]];

        foreach ($models as $index => $model) {
            try {
                $assistant->forceFill(['model' => $model, 'provider' => 'openrouter'])->save();

                foreach ($this->gateway->stream($prompt, $model) as $chunk) {
                    $lastChunk = $chunk;
                    $delta = data_get($chunk, 'choices.0.delta.content');
                    if (! is_string($delta) || $delta === '') {
                        continue;
                    }

                    $content .= $delta;
                    $assistant->forceFill(['content' => $content])->saveQuietly();

                    yield ['event' => 'message.delta', 'data' => [
                        'conversation_id' => (string) $conversation->id,
                        'message_id' => (string) $assistant->id,
                        'delta' => $delta,
                    ]];
                }

                $usage = $this->usageFromChunk($lastChunk);
                $latency = (int) round((microtime(true) - $startedAt) * 1000);
                $assistant->forceFill([
                    'content' => $content,
                    'status' => 'completed',
                    'request_id' => data_get($lastChunk, 'id'),
                    'input_tokens' => $usage['input_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                    'latency_ms' => $latency,
                    'metadata' => ['fallback_index' => $index],
                ])->save();
                $conversation->forceFill(['last_message_at' => now()])->save();
                $this->recordUsage($conversation, $assistant, $usage, $latency);

                yield ['event' => 'message.done', 'data' => [
                    'conversation_id' => (string) $conversation->id,
                    'message' => $this->messagePayload($assistant->fresh('attachments')),
                ]];

                return;
            } catch (LlmException $exception) {
                $lastError = $exception;
                if ($content !== '' || ! $this->mayFallback($exception)) {
                    break;
                }
            } catch (\Throwable $exception) {
                $lastError = $exception;
                if ($content !== '') {
                    break;
                }
            }
        }

        $latency = (int) round((microtime(true) - $startedAt) * 1000);
        $assistant->forceFill([
            'content' => $content,
            'status' => 'failed',
            'error_message' => $this->userFacingError($lastError),
            'latency_ms' => $latency,
            'metadata' => [
                'fallback_attempted' => count($models),
                'provider_status' => $lastError instanceof LlmException ? $lastError->status : null,
            ],
        ])->save();
        Log::warning('AI completion failed after fallback attempts.', [
            'conversation_id' => $conversation->id,
            'models_attempted' => $models,
            'provider_status' => $lastError instanceof LlmException ? $lastError->status : null,
            'error_type' => $lastError !== null ? get_class($lastError) : null,
        ]);
        $this->recordUsage($conversation, $assistant, [], $latency, $lastError?->getCode());

        yield ['event' => 'message.error', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'error' => $this->userFacingError($lastError),
        ]];
    }

    /**
     * @return \Generator<int, array{event: string, data: array<string, mixed>}, mixed, void>
     */
    protected function streamInfographicReply(AiConversation $conversation, AiMessage $userMessage): \Generator
    {
        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => 'streaming',
            'content' => '',
        ]);
        $startedAt = microtime(true);
        $models = $this->models->infographicCandidates();
        $prompt = $this->prompts->build($conversation, isInfographic: true);
        $lastError = null;

        yield ['event' => 'message.start', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
        ]];

        yield ['event' => 'message.delta', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'delta' => "🎨 Sedang merancang & membuat gambar infografis diagram dengan AI...\n\n",
        ]];

        foreach ($models as $index => $model) {
            try {
                $assistant->forceFill(['model' => $model, 'provider' => 'openrouter'])->save();

                $response = $this->gateway->complete($prompt, $model, [
                    'modalities' => ['text', 'image'],
                    'max_tokens' => 2048,
                ]);

                $rawImage = data_get($response, 'choices.0.message.images.0.image_url.url');
                $textContent = trim((string) data_get($response, 'choices.0.message.content', ''));
                $publicUrl = null;

                if (is_string($rawImage) && preg_match('/^data:image\/(\w+);base64,(.+)$/', $rawImage, $matches)) {
                    $ext = strtolower($matches[1]);
                    $binary = base64_decode($matches[2]);
                    $filename = 'infographic-'.Str::uuid().'.'.$ext;
                    $relativePath = 'ai/infographics/'.$filename;
                    Storage::disk('public')->put($relativePath, $binary);
                    $publicUrl = '/storage/'.$relativePath;

                    AiAttachment::create([
                        'ai_conversation_id' => $conversation->id,
                        'ai_message_id' => $assistant->id,
                        'user_id' => $conversation->user_id,
                        'disk' => 'public',
                        'path' => $relativePath,
                        'original_name' => 'infografis-'.date('Ymd-His').'.'.$ext,
                        'mime_type' => 'image/'.($ext === 'jpg' ? 'jpeg' : $ext),
                        'size' => strlen($binary),
                        'sha256' => hash('sha256', $binary),
                        'kind' => 'image',
                        'status' => 'ready',
                        'metadata' => [
                            'is_generated' => true,
                            'url' => $publicUrl,
                        ],
                    ]);
                }

                // Keep the demo visual consistent when a provider returns
                // text-only output or a small catalog icon instead of an
                // infographic image.
                if ($publicUrl === null) {
                    $demoAsset = (string) config('ai.models.infographic_demo_asset', '');
                    $demoPath = public_path(ltrim($demoAsset, '/'));
                    if ($demoAsset !== '' && is_file($demoPath)) {
                        $publicUrl = $demoAsset;
                    }
                }

                $textContent = preg_replace(
                    '/!\[[^\]]*\]\((?:https?:\/\/|\/)[^)]+\)\s*/i',
                    '',
                    $textContent,
                ) ?? $textContent;

                $title = $conversation->title ?: 'Diagram Infografis Pertanian';
                $finalContent = $publicUrl
                    ? '!['.$title.']('.$publicUrl.")\n\n".$textContent
                    : $textContent;

                $usage = $this->usageFromChunk($response);
                $latency = (int) round((microtime(true) - $startedAt) * 1000);

                $assistant->forceFill([
                    'content' => $finalContent,
                    'status' => 'completed',
                    'request_id' => data_get($response, 'id'),
                    'input_tokens' => $usage['input_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                    'latency_ms' => $latency,
                    'metadata' => [
                        'is_infographic' => true,
                        'has_image' => $publicUrl !== null,
                        'fallback_index' => $index,
                    ],
                ])->save();

                $conversation->forceFill(['last_message_at' => now()])->save();
                $this->recordUsage($conversation, $assistant, $usage, $latency);

                yield ['event' => 'message.delta', 'data' => [
                    'conversation_id' => (string) $conversation->id,
                    'message_id' => (string) $assistant->id,
                    'delta' => $finalContent,
                ]];

                yield ['event' => 'message.done', 'data' => [
                    'conversation_id' => (string) $conversation->id,
                    'message' => $this->messagePayload($assistant->fresh('attachments')),
                ]];

                return;
            } catch (LlmException $exception) {
                $lastError = $exception;
                if (! $this->mayFallback($exception)) {
                    break;
                }
            } catch (\Throwable $exception) {
                $lastError = $exception;
            }
        }

        // Fallback to text completion if image generation failed
        Log::warning('Infographic image generation failed, falling back to standard text response.', [
            'conversation_id' => $conversation->id,
            'error' => $lastError?->getMessage(),
        ]);

        $textPrompt = $this->prompts->build($conversation, isInfographic: false);
        $textModels = $this->models->candidates(hasVision: false);

        foreach ($textModels as $index => $model) {
            try {
                $assistant->forceFill(['model' => $model, 'provider' => 'openrouter'])->save();
                $content = '';

                foreach ($this->gateway->stream($textPrompt, $model) as $chunk) {
                    $delta = data_get($chunk, 'choices.0.delta.content');
                    if (! is_string($delta) || $delta === '') {
                        continue;
                    }
                    $content .= $delta;
                    $assistant->forceFill(['content' => $content])->saveQuietly();

                    yield ['event' => 'message.delta', 'data' => [
                        'conversation_id' => (string) $conversation->id,
                        'message_id' => (string) $assistant->id,
                        'delta' => $delta,
                    ]];
                }

                $usage = $this->usageFromChunk($chunk ?? []);
                $latency = (int) round((microtime(true) - $startedAt) * 1000);
                $assistant->forceFill([
                    'content' => $content,
                    'status' => 'completed',
                    'latency_ms' => $latency,
                ])->save();
                $conversation->forceFill(['last_message_at' => now()])->save();
                $this->recordUsage($conversation, $assistant, $usage, $latency);

                yield ['event' => 'message.done', 'data' => [
                    'conversation_id' => (string) $conversation->id,
                    'message' => $this->messagePayload($assistant->fresh('attachments')),
                ]];

                return;
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }

        $latency = (int) round((microtime(true) - $startedAt) * 1000);
        $assistant->forceFill([
            'content' => '',
            'status' => 'failed',
            'error_message' => $this->userFacingError($lastError),
            'latency_ms' => $latency,
        ])->save();

        yield ['event' => 'message.error', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'error' => $this->userFacingError($lastError),
        ]];
    }

    protected function userFacingError(?\Throwable $error): string
    {
        if ($error instanceof LlmException && $error->getMessage() === 'AI provider is not configured.') {
            return 'AI belum dikonfigurasi di server. Hubungi administrator.';
        }

        return 'Maaf, layanan AI sedang tidak tersedia. Coba lagi nanti.';
    }

    protected function mayFallback(LlmException $exception): bool
    {
        $status = $exception->status;

        if ($status === null) {
            return true;
        }

        return $status === 408
            || $status === 409
            || $status === 425
            || $status === 429
            || $status >= 500;
    }

    /** @return array{input_tokens: int|null, output_tokens: int|null} */
    /**
     * @param  array<string, mixed>  $chunk
     * @return array{input_tokens: int|null, output_tokens: int|null}
     */
    protected function usageFromChunk(array $chunk): array
    {
        return [
            'input_tokens' => data_get($chunk, 'usage.prompt_tokens') ?? data_get($chunk, 'usage.input_tokens'),
            'output_tokens' => data_get($chunk, 'usage.completion_tokens') ?? data_get($chunk, 'usage.output_tokens'),
        ];
    }

    /** @param array{input_tokens?: int|null, output_tokens?: int|null} $usage */
    protected function recordUsage(AiConversation $conversation, AiMessage $message, array $usage, int $latency, int|string|null $errorCode = null): void
    {
        AiUsageEvent::create([
            'team_id' => $conversation->team_id,
            'user_id' => $conversation->user_id,
            'ai_conversation_id' => $conversation->id,
            'ai_message_id' => $message->id,
            'provider' => $message->provider,
            'model' => $message->model,
            'event_type' => $errorCode === null ? 'completion' : 'completion_failed',
            'request_id' => $message->request_id,
            'input_tokens' => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'latency_ms' => $latency,
            'metadata' => $errorCode === null ? null : ['provider_error_code' => $errorCode],
        ]);
    }

    /** @return array<string, mixed> */
    public function messagePayload(AiMessage $message): array
    {
        return [
            'id' => (string) $message->id,
            'role' => $message->role,
            'content' => $message->content ?? '',
            'status' => $message->status,
            'created_at' => $message->created_at?->toISOString(),
            'attachments' => $message->attachments->map(fn ($attachment): array => [
                'id' => (string) $attachment->id,
                'name' => $attachment->original_name,
                'size' => $attachment->size,
                'type' => $attachment->mime_type,
                'url' => $attachment->disk === 'public'
                    ? ('/storage/'.$attachment->path)
                    : data_get($attachment->metadata, 'url'),
            ])->values()->all(),
        ];
    }
}

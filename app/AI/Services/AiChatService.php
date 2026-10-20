<?php

namespace App\AI\Services;

use App\AI\Contracts\KnowledgeRetriever;
use App\AI\Contracts\LlmGateway;
use App\AI\Data\AiRouteDecision;
use App\AI\Exceptions\EmbeddingException;
use App\AI\Exceptions\LlmException;
use App\AI\Exceptions\VectorStoreException;
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
        private readonly AiIntentRouter $router,
        private readonly KnowledgeRetriever $retriever,
        private readonly InMemoryKnowledgeRetriever $fallbackRetriever,
        private readonly GroundedResponseValidator $groundedValidator,
        private readonly FinanceSnapshotService $financeSnapshot,
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
    public function streamReply(
        AiConversation $conversation,
        bool $forceInfographic = false,
        ?AiMessage $activeMessage = null,
    ): \Generator {
        $userMessage = $activeMessage?->loadMissing('attachments')
            ?? $conversation->messages()->latest('id')->first();
        if (! $userMessage) {
            return;
        }

        // A caller can pass a message loaded before refreshing the
        // conversation. Refuse a message from another conversation rather
        // than accidentally routing the wrong prompt.
        if ((int) $userMessage->ai_conversation_id !== (int) $conversation->getKey()) {
            return;
        }

        $userText = (string) ($userMessage->content ?? '');
        $hasVision = $userMessage->attachments->contains(fn ($attachment): bool => $attachment->kind === 'image');
        $pipelineStartedAt = microtime(true);
        $routeStartedAt = microtime(true);
        $decision = $this->router->decide($userText, $hasVision);
        $routeMs = (int) round((microtime(true) - $routeStartedAt) * 1000);
        $traceId = (string) Str::uuid();
        $isInfographic = $forceInfographic || $decision->intent === 'infographic'
            || (bool) preg_match('/\b(infografis|diagram visual|bagan alur|poster visual|diagram smart farming)\b/i', $userText);

        // Flush a visible progress event before embedding/Qdrant work starts.
        // This keeps the client informed during the first cold retrieval call.
        yield ['event' => 'message.progress', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'progress' => $isInfographic ? 'Menyiapkan konteks infografis…' : 'Mencari konteks yang relevan…',
            'route' => $decision->toArray(),
        ]];
        $retrievalStartedAt = microtime(true);
        $evidence = $this->evidenceFor($decision, $conversation, $userText);
        $timings = [
            'route_ms' => $routeMs,
            'retrieval_ms' => (int) round((microtime(true) - $retrievalStartedAt) * 1000),
        ];

        if ($deterministic = $this->deterministicResponse($decision)) {
            yield from $this->streamDeterministicReply($conversation, $decision, $deterministic, $evidence, $traceId, $timings, $pipelineStartedAt);

            return;
        }

        if ($isInfographic) {
            yield from $this->streamInfographicReply($conversation, $userMessage, $evidence, $decision, $traceId, $timings, $pipelineStartedAt);

            return;
        }

        $promptStartedAt = microtime(true);
        $prompt = $this->prompts->build(
            $conversation,
            isInfographic: false,
            activeMessage: $userMessage,
            evidence: $evidence,
        );
        $timings['prompt_ms'] = (int) round((microtime(true) - $promptStartedAt) * 1000);
        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => 'streaming',
            'content' => '',
            'metadata' => $this->traceMetadata($decision, $evidence, $traceId, $timings),
        ]);
        $startedAt = microtime(true);
        $models = $this->models->candidates(
            $hasVision,
            ! $hasVision && (bool) config('ai.models.prefer_fast_text', false),
        );
        $generationOptions = $this->textGenerationOptions();
        $content = '';
        $lastError = null;
        $lastChunk = [];
        $lastPersistedAt = microtime(true);
        $lastPersistedLength = 0;

        yield ['event' => 'message.start', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'route' => $decision->toArray(),
            'retrieval' => $this->retrievalMetadata($evidence),
        ]];
        // Retrieval is complete at this point.  Emit a second phase marker
        // before entering the provider's blocking stream so the client can
        // explain the otherwise invisible first-token wait.
        yield ['event' => 'message.progress', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'progress' => count($evidence) > 0
                ? 'Menyusun jawaban dari '.count($evidence).' sumber…'
                : 'Menyusun jawaban…',
            'phase' => 'generation',
        ]];

        foreach ($models as $index => $model) {
            try {
                $assistant->forceFill(['model' => $model, 'provider' => 'openrouter'])->save();

                $lastChunk = [];
                $usage = ['input_tokens' => null, 'output_tokens' => null];
                $firstTokenAt = null;
                $generationStartedAt = microtime(true);
                foreach ($this->gateway->stream($prompt, $model, $generationOptions) as $chunk) {
                    $lastChunk = $chunk;
                    $usage = $this->mergeUsage($usage, $chunk);
                    $delta = data_get($chunk, 'choices.0.delta.content');
                    if (! is_string($delta) || $delta === '') {
                        continue;
                    }

                    $firstTokenAt ??= microtime(true);
                    $content .= $delta;
                    // The browser already receives every delta over SSE. Avoid
                    // a SQLite write for every token; persist a bounded
                    // checkpoint for reconnect/reconciliation and let the
                    // final save below remain the source of truth.
                    $now = microtime(true);
                    if (
                        strlen($content) - $lastPersistedLength >= 512
                        || $now - $lastPersistedAt >= 0.5
                    ) {
                        $assistant->forceFill(['content' => $content])->saveQuietly();
                        $lastPersistedAt = $now;
                        $lastPersistedLength = strlen($content);
                    }

                    yield ['event' => 'message.delta', 'data' => [
                        'conversation_id' => (string) $conversation->id,
                        'message_id' => (string) $assistant->id,
                        'delta' => $delta,
                    ]];
                }

                if (trim($content) === '') {
                    $lastError = new LlmException('AI provider returned an empty response.', 502);
                    if ($index < count($models) - 1) {
                        continue;
                    }

                    break;
                }

                $latency = (int) round((microtime(true) - $startedAt) * 1000);
                $validation = $this->groundedValidator->validate($content, $evidence);
                $timings['generation_ms'] = $latency;
                $timings['total_ms'] = (int) round((microtime(true) - $pipelineStartedAt) * 1000);
                $timings['first_token_ms'] = $firstTokenAt === null
                    ? null
                    : (int) round(($firstTokenAt - $generationStartedAt) * 1000);
                $cleanContent = $this->sanitizeAssistantReply($this->appendCitations($content, $evidence));
                $assistant->forceFill([
                    'content' => $cleanContent,
                    'status' => 'completed',
                    'request_id' => data_get($lastChunk, 'id'),
                    'input_tokens' => $usage['input_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                    'latency_ms' => $latency,
                    'metadata' => $this->mergeMetadata($assistant, [
                        'request_trace' => $this->requestTrace(
                            $prompt,
                            '/api/v1/chat/completions',
                            $models[$index] ?? $model,
                            data_get($lastChunk, 'provider_request_id') ?: data_get($lastChunk, 'id'),
                            'completed',
                            $latency,
                            $evidence,
                        ),
                        'fallback_index' => $index,
                        'fallback' => [
                            'attempts' => $index + 1,
                            'used' => $index > 0,
                        ],
                        'latency' => $timings,
                        'citation' => $validation->toArray(),
                        'generation' => [
                            'first_token_ms' => $firstTokenAt === null
                                ? null
                                : (int) round(($firstTokenAt - $generationStartedAt) * 1000),
                            'stream_ms' => (int) round((microtime(true) - $generationStartedAt) * 1000),
                            'max_output_tokens' => $generationOptions['max_tokens'],
                        ],
                    ]),
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
            'request_id' => $lastError instanceof LlmException ? $lastError->requestId : null,
            'error_message' => $this->userFacingError($lastError),
            'latency_ms' => $latency,
            'metadata' => $this->mergeMetadata($assistant, [
                'fallback_attempted' => count($models),
                'fallback' => [
                    'attempts' => count($models),
                    'used' => count($models) > 1,
                ],
                'latency' => [
                    ...$timings,
                    'generation_ms' => $latency,
                    'total_ms' => (int) round((microtime(true) - $pipelineStartedAt) * 1000),
                ],
                'provider_status' => $lastError instanceof LlmException ? $lastError->status : null,
                'provider_request_id' => $lastError instanceof LlmException ? $lastError->requestId : null,
            ]),
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
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<string, int|null>  $timings
     * @return \Generator<int, array{event: string, data: array<string, mixed>}, mixed, void>
     */
    protected function streamInfographicReply(
        AiConversation $conversation,
        AiMessage $userMessage,
        array $evidence = [],
        ?AiRouteDecision $decision = null,
        ?string $traceId = null,
        array $timings = [],
        ?float $pipelineStartedAt = null,
    ): \Generator {
        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => 'streaming',
            'content' => '',
            'metadata' => $this->traceMetadata($decision, $evidence, $traceId, $timings),
        ]);
        $startedAt = microtime(true);
        $models = $this->models->infographicCandidates();
        $prompt = $this->prompts->build(
            $conversation,
            isInfographic: true,
            activeMessage: $userMessage,
            evidence: $evidence,
        );
        $lastError = null;

        yield ['event' => 'message.start', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'route' => $decision?->toArray(),
            'retrieval' => $this->retrievalMetadata($evidence),
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
                    'image_generation' => true,
                    'size' => '1024x1024',
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

                if ($publicUrl === null) {
                    throw new LlmException(
                        'AI image provider returned no generated image.',
                        502,
                    );
                }

                $textContent = preg_replace(
                    '/!\[[^\]]*\]\((?:https?:\/\/|\/)[^)]+\)\s*/i',
                    '',
                    $textContent,
                ) ?? $textContent;

                if ($publicUrl === null && trim($textContent) === '') {
                    throw new LlmException('AI provider returned an empty infographic response.', 502);
                }

                $title = $conversation->title ?: 'Diagram Infografis Pertanian';
                $textContent = $this->appendCitations($textContent, $evidence);
                $validation = $this->groundedValidator->validate($textContent, $evidence);
                $finalContent = $publicUrl
                    ? '!['.$title.']('.$publicUrl.")\n\n".$textContent
                    : $textContent;

                $usage = $this->usageFromChunk($response);
                $latency = (int) round((microtime(true) - $startedAt) * 1000);
                $timings['generation_ms'] = $latency;
                $timings['total_ms'] = $pipelineStartedAt === null
                    ? $latency
                    : (int) round((microtime(true) - $pipelineStartedAt) * 1000);

                $assistant->forceFill([
                    'content' => $finalContent,
                    'status' => 'completed',
                    'request_id' => data_get($response, 'provider_request_id') ?: data_get($response, 'id'),
                    'input_tokens' => $usage['input_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                    'latency_ms' => $latency,
                    'metadata' => $this->mergeMetadata($assistant, [
                        'request_trace' => $this->requestTrace(
                            $prompt,
                            '/api/v1/images',
                            $model,
                            data_get($response, 'provider_request_id') ?: data_get($response, 'id'),
                            'completed',
                            $latency,
                            $evidence,
                        ),
                        'is_infographic' => true,
                        'has_image' => true,
                        'image_generation' => [
                            'status' => $index === 0 ? 'generated' : 'fallback',
                            'reason' => $index === 0 ? null : 'primary_image_model_failed',
                            'provider' => 'openrouter',
                            'model' => $model,
                            'attempts' => $index + 1,
                        ],
                        'fallback_index' => $index,
                        'fallback' => [
                            'attempts' => $index + 1,
                            'used' => $index > 0,
                            'reason' => $index > 0 ? 'primary_image_model_failed' : null,
                        ],
                        'latency' => $timings,
                        'citation' => $validation->toArray(),
                    ]),
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

        $latency = (int) round((microtime(true) - $startedAt) * 1000);
        $failureReason = $lastError?->getMessage() ?: 'image_generation_failed';
        $assistant->forceFill([
            'content' => '',
            'status' => 'failed',
            'request_id' => $lastError instanceof LlmException ? $lastError->requestId : null,
            'error_message' => $this->userFacingImageError($lastError),
            'latency_ms' => $latency,
            'metadata' => $this->mergeMetadata($assistant, [
                'request_trace' => $this->requestTrace(
                    $prompt,
                    '/api/v1/images',
                    (string) end($models),
                    $lastError instanceof LlmException ? $lastError->requestId : null,
                    'failed',
                    $latency,
                    $evidence,
                ),
                'is_infographic' => true,
                'has_image' => false,
                'image_generation' => [
                    'status' => 'failed',
                    'reason' => $failureReason,
                    'attempts' => count($models),
                ],
                'fallback' => [
                    'attempts' => count($models),
                    'used' => count($models) > 1,
                    'reason' => $failureReason,
                ],
                'latency' => [
                    ...$timings,
                    'generation_ms' => $latency,
                    'total_ms' => $pipelineStartedAt === null ? $latency : (int) round((microtime(true) - $pipelineStartedAt) * 1000),
                ],
            ]),
        ])->save();
        Log::warning('Infographic image generation failed.', [
            'conversation_id' => $conversation->id,
            'reason' => $failureReason,
            'attempts' => count($models),
        ]);
        yield ['event' => 'message.error', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'error' => $this->userFacingImageError($lastError),
            'image_generation' => data_get($assistant->fresh()->metadata, 'image_generation'),
        ]];
    }

    /** @param array<int, array<string, mixed>> $messages */
    protected function requestTrace(
        array $messages,
        string $endpoint,
        string $model,
        ?string $providerRequestId,
        string $status,
        int $latencyMs,
        array $evidence,
    ): array {
        $system = collect($messages)->firstWhere('role', 'system')['content'] ?? '';
        $user = collect($messages)->reverse()->firstWhere('role', 'user')['content'] ?? '';

        return [
            'user_prompt' => is_string($user) ? $user : json_encode($user, JSON_UNESCAPED_UNICODE),
            'system_prompt' => is_string($system) ? $system : json_encode($system, JSON_UNESCAPED_UNICODE),
            'endpoint' => $endpoint,
            'model' => $model,
            'evidence_ids' => array_values(array_map(
                static fn (array $item): string => (string) ($item['id'] ?? ''),
                $evidence,
            )),
            'provider_request_id' => $providerRequestId,
            'status' => $status,
            'latency_ms' => $latencyMs,
        ];
    }

    protected function userFacingImageError(?\Throwable $error): string
    {
        if ($error instanceof LlmException && $error->getMessage() === 'AI provider is not configured.') {
            return 'Model gambar AI belum dikonfigurasi. Tidak ada gambar lokal yang digunakan.';
        }

        return 'Image generation gagal: provider tidak mengembalikan gambar. Silakan coba lagi.';
    }

    protected function userFacingError(?\Throwable $error): string
    {
        if ($error instanceof LlmException && $error->getMessage() === 'AI provider is not configured.') {
            return 'AI belum dikonfigurasi di server. Hubungi administrator.';
        }

        return 'Maaf, layanan AI sedang tidak tersedia. Coba lagi nanti.';
    }

    /** @return array<int, array<string, mixed>> */
    protected function evidenceFor(AiRouteDecision $decision, AiConversation $conversation, string $userText): array
    {
        if (! config('ai.rag.enabled', true) || ! config('ai.rag.retrieval_enabled', true)) {
            return [];
        }

        if (! in_array($decision->intent, ['knowledge', 'llm', 'infographic'], true)) {
            return [];
        }

        if (in_array($decision->reason, ['unsupported_topic', 'explicit_data_lookup'], true)
            || ($decision->reason === 'general_question' && $decision->needsClarification)) {
            return [];
        }

        if ($decision->domain === 'finance') {
            $snapshot = $this->financeSnapshot->snapshot($conversation->team);

            return [[
                'id' => 'finance-demo-snapshot',
                'title' => 'Finance demo snapshot',
                'source' => 'Farmer IC finance demo dataset',
                'content' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'score' => 1.0,
                'metadata' => ['document_id' => 'finance-demo-snapshot', 'retrieval' => 'deterministic'],
            ]];
        }

        if ($decision->domain === 'general' && $decision->intent !== 'knowledge') {
            return [];
        }

        try {
            return $this->retriever->retrieve($userText, $conversation->team, (int) config('ai.rag.top_k', 4));
        } catch (EmbeddingException|VectorStoreException $exception) {
            Log::warning('Vector knowledge retrieval failed; falling back to in-memory retrieval.', [
                'conversation_id' => $conversation->id,
                'error_type' => $exception::class,
                'status' => $exception->status,
            ]);

            $fallback = $this->fallbackRetriever->retrieve($userText, $conversation->team, (int) config('ai.rag.top_k', 4));

            return array_map(static function (array $item) use ($exception): array {
                $item['metadata'] = [
                    ...((array) $item['metadata']),
                    'retrieval_fallback' => true,
                    'retrieval_error' => $exception::class,
                ];

                return $item;
            }, $fallback);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<string, int|null>  $timings
     * @return array<string, mixed>
     */
    protected function traceMetadata(
        ?AiRouteDecision $decision,
        array $evidence,
        ?string $traceId = null,
        array $timings = [],
    ): array {
        return [
            'trace_id' => $traceId ?? (string) Str::uuid(),
            'route' => $decision?->toArray(),
            'retrieval' => $this->retrievalMetadata($evidence),
            'prompt_version' => config('ai.rag.schema_version', 'grounded-v1'),
            'latency' => $timings,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @return array<string, mixed>
     */
    protected function retrievalMetadata(array $evidence): array
    {
        return [
            'count' => count($evidence),
            'evidence_ids' => array_values(array_map(fn (array $item): string => (string) $item['id'], $evidence)),
            'scores' => array_values(array_map(fn (array $item): float => (float) $item['score'], $evidence)),
            'retriever' => data_get($evidence, '0.metadata.retrieval', config('ai.rag.vector_enabled') ? 'qdrant' : 'in-memory'),
            'fallback' => (bool) data_get($evidence, '0.metadata.retrieval_fallback', false),
            'results' => array_values(array_map(static fn (array $item): array => [
                'id' => (string) ($item['id'] ?? ''),
                'score' => (float) ($item['score'] ?? 0),
                'vector_score' => data_get($item, 'metadata.vector_score'),
                'lexical_score' => data_get($item, 'metadata.lexical_score'),
                'hybrid_score' => data_get($item, 'metadata.hybrid_score'),
            ], $evidence)),
            'index_version' => data_get($evidence, '0.metadata.index_version', config('ai.rag.vector_enabled') ? config('ai.rag.index_version', 'embed-v1-chunk-v1') : 'in-memory-v1'),
            'embedding_model' => data_get($evidence, '0.metadata.embedding_model'),
            'embedding_tokens' => (int) data_get($evidence, '0.metadata.embedding_tokens', 0),
            'embedding_cache_hits' => (int) data_get($evidence, '0.metadata.embedding_cache_hits', 0),
            'embedding_cache_misses' => (int) data_get($evidence, '0.metadata.embedding_cache_misses', 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     */
    protected function appendCitations(string $content, array $evidence): string
    {
        $content = $this->groundedValidator->guard($content, $evidence);

        if ($evidence === []) {
            return $content;
        }

        $sources = collect($evidence)->values()->map(function (array $item, int $index): string {
            return '[S'.($index + 1).'] '.trim((string) ($item['source'] ?? $item['title'] ?? 'Knowledge base'));
        })->implode(' · ');

        return trim($content)."\n\nSumber: {$sources}";
    }

    protected function deterministicResponse(AiRouteDecision $decision): ?string
    {
        return match ($decision->reason) {
            'unsupported_topic' => 'Topik itu belum tersedia di Asisten Kebun. Saya fokus pada data dan aktivitas Farmer IC, perawatan tanaman, serta panduan pertanian.',
            'general_question' => $decision->needsClarification
                ? 'Saya belum menangkap tujuan pertanyaannya. Sebutkan kebun, komoditas, periode, atau tujuan Anda agar saya dapat memilih data, panduan, atau infografis yang tepat.'
                : null,
            'explicit_data_lookup' => 'Permintaan ini membutuhkan data aplikasi yang terstruktur. Jalur data demo di browser dapat menampilkan seed yang tersedia, tetapi server AI belum menerima data tersebut sebagai sumber live.',
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<string, int|null>  $timings
     */
    protected function streamDeterministicReply(
        AiConversation $conversation,
        AiRouteDecision $decision,
        string $content,
        array $evidence,
        ?string $traceId = null,
        array $timings = [],
        ?float $pipelineStartedAt = null,
    ): \Generator {
        $validation = $this->groundedValidator->validate($content, $evidence);
        $timings['total_ms'] = $pipelineStartedAt === null
            ? null
            : (int) round((microtime(true) - $pipelineStartedAt) * 1000);
        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => 'completed',
            'content' => $this->appendCitations($content, $evidence),
            'metadata' => [
                ...$this->traceMetadata($decision, $evidence, $traceId, $timings),
                'deterministic' => true,
                'latency' => $timings,
                'citation' => $validation->toArray(),
            ],
        ]);

        yield ['event' => 'message.start', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'route' => $decision->toArray(),
            'retrieval' => $this->retrievalMetadata($evidence),
        ]];
        yield ['event' => 'message.delta', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'delta' => $assistant->content,
        ]];
        yield ['event' => 'message.done', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message' => $this->messagePayload($assistant->fresh('attachments')),
        ]];
        $this->recordUsage($conversation, $assistant, [], 0, null, 'deterministic');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function mergeMetadata(AiMessage $message, array $extra): array
    {
        return array_filter([
            ...((array) $message->metadata),
            ...$extra,
        ], static fn (mixed $value): bool => $value !== null);
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

    /** @return array<string, mixed> */
    protected function textGenerationOptions(): array
    {
        $maxTokens = max(128, (int) config('ai.models.response_max_tokens', 1024));
        $options = ['max_tokens' => $maxTokens];
        $effort = trim((string) config('ai.models.reasoning_effort', ''));

        if ($effort !== '' && in_array($effort, ['none', 'minimal', 'low', 'medium', 'high'], true)) {
            $options['reasoning'] = ['effort' => $effort];
        }

        return $options;
    }

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

    /**
     * @param  array{input_tokens: int|null, output_tokens: int|null}  $current
     * @param  array<string, mixed>  $chunk
     * @return array{input_tokens: int|null, output_tokens: int|null}
     */
    protected function mergeUsage(array $current, array $chunk): array
    {
        $usage = $this->usageFromChunk($chunk);

        return [
            'input_tokens' => $usage['input_tokens'] ?? $current['input_tokens'],
            'output_tokens' => $usage['output_tokens'] ?? $current['output_tokens'],
        ];
    }

    /** @param array{input_tokens?: int|null, output_tokens?: int|null} $usage */
    protected function recordUsage(
        AiConversation $conversation,
        AiMessage $message,
        array $usage,
        int $latency,
        int|string|null $errorCode = null,
        string $eventType = 'completion',
    ): void {
        AiUsageEvent::create([
            'team_id' => $conversation->team_id,
            'user_id' => $conversation->user_id,
            'ai_conversation_id' => $conversation->id,
            'ai_message_id' => $message->id,
            'provider' => $message->provider,
            'model' => $message->model,
            'event_type' => $errorCode === null ? $eventType : 'completion_failed',
            'request_id' => $message->request_id,
            'input_tokens' => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'latency_ms' => $latency,
            'metadata' => array_filter([
                ...((array) $message->metadata),
                ...($errorCode === null ? [] : ['provider_error_code' => $errorCode]),
            ], static fn (mixed $value): bool => $value !== null),
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
            'metadata' => $message->metadata,
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

    protected function sanitizeAssistantReply(string $content): string
    {
        $cleaned = preg_replace([
            '/\s*(?:karena\s+)?(?:daftar\s+transaksi|data|catatan)\s+yang\s+(?:saya|kami)\s+terima\s+terpotong[^\n.]*[.\n]?/iu',
            '/\s*kalau\s+anda\s+kirim\s+screenshot[^\n.]*[.\n]?/iu',
            '/^[\s]*(?:berdasarkan|menurut)\s+(?:data|informasi)\s+yang\s+(?:saya|kami)\s+terima[,:\s]*/imu',
        ], '', $content) ?? $content;

        return trim(preg_replace('/\n{3,}/u', "\n\n", $cleaned) ?? $cleaned);
    }
}

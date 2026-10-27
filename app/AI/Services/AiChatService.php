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
        private readonly FinanceImageRenderer $financeImageRenderer,
        private readonly FarmSnapshotService $farmSnapshot,
        private readonly FarmImageRenderer $farmImageRenderer,
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
        if ((string) $userMessage->ai_conversation_id !== (string) $conversation->getKey()) {
            return;
        }

        $userText = (string) ($userMessage->content ?? '');
        $hasVision = $userMessage->attachments->contains(fn ($attachment): bool => $attachment->kind === 'image');
        $pipelineStartedAt = microtime(true);
        $routeStartedAt = microtime(true);
        $previousAssistant = $conversation->messages()
            ->where('role', 'assistant')
            ->where('status', 'completed')
            ->latest('id')
            ->first();
        $contextDomain = data_get($previousAssistant?->metadata, 'route.domain');
        $decision = $this->router->decide($userText, $hasVision, is_string($contextDomain) ? $contextDomain : null);
        $routeMs = (int) round((microtime(true) - $routeStartedAt) * 1000);
        $traceId = (string) Str::uuid();
        $isInfographic = $forceInfographic || $decision->intent === 'infographic'
            || (bool) preg_match('/\b(infografis|diagram visual|bagan alur|poster visual|diagram smart farming)\b/i', $userText);
        $financeImagePeriod = null;
        $financeImagePromptContext = null;
        $farmImageSnapshot = null;
        $financeValidationPeriod = null;
        if ($isInfographic && $decision->domain === 'finance') {
            $financeImagePeriod = $this->financeSnapshot->periodForPrompt($conversation->team, $userText);
            if ($financeImagePeriod['available']) {
                $financeImagePromptContext = $this->financeSnapshot->imagePromptContext($financeImagePeriod);
            }
        }
        if (! $isInfographic && $decision->domain === 'finance') {
            $candidate = $this->financeSnapshot->periodForPrompt($conversation->team, $userText);
            if ($candidate['requested'] && ! $candidate['available']) {
                yield from $this->streamDeterministicReply(
                    $conversation,
                    $decision,
                    $this->financeSnapshot->unavailableDataMessage($candidate),
                    [],
                    $traceId,
                    ['route_ms' => $routeMs, 'retrieval_ms' => 0],
                    $pipelineStartedAt,
                );

                return;
            }

            // Quarter and free-form historical periods have a separate
            // aggregation contract. Do not compare them with the annual
            // snapshot until that aggregation has been resolved explicitly.
            if ($candidate['available'] && (
                $candidate['requested']
                || in_array($decision->period, ['current', 'unknown'], true)
            )) {
                $financeValidationPeriod = $candidate;
            }
        }

        // Flush a visible progress event before embedding/Qdrant work starts.
        // This keeps the client informed during the first cold retrieval call.
        yield ['event' => 'message.progress', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'progress' => $isInfographic ? 'Menyiapkan konteks gambar…' : 'Mencari konteks yang relevan…',
            'route' => $decision->toArray(),
        ]];

        if (is_array($financeImagePeriod) && ! $financeImagePeriod['available']) {
            yield from $this->streamDeterministicReply(
                $conversation,
                $decision,
                $this->financeSnapshot->unavailableImageMessage($financeImagePeriod),
                [],
                $traceId,
                ['route_ms' => $routeMs, 'retrieval_ms' => 0],
                $pipelineStartedAt,
            );

            return;
        }

        // A named garden visual must use the same canonical record as the
        // garden detail page. Render it locally so a bitmap model cannot
        // replace exact areas, crops, locations, or status with plausible
        // but invented values.
        if ($isInfographic && in_array($decision->domain, ['garden', 'agronomy', 'activity'], true)) {
            $farmImageSnapshot = $this->farmSnapshot->resolve($userText);
            if (is_array($farmImageSnapshot)) {
                yield from $this->streamDeterministicFarmImageReply(
                    $conversation,
                    $decision,
                    $farmImageSnapshot,
                    $traceId,
                    ['route_ms' => $routeMs, 'retrieval_ms' => 0],
                    $pipelineStartedAt,
                );

                return;
            }
        }

        // Finance figures must remain exact. Render the selected period as a
        // local SVG before retrieval/provider work so an image model cannot
        // redraw a value incorrectly. Generic agriculture images continue
        // through the provider path below.
        if ($isInfographic
            && $decision->domain === 'finance') {
            yield from $this->streamDeterministicFinanceImageReply(
                $conversation,
                $decision,
                $financeImagePeriod,
                $traceId,
                ['route_ms' => $routeMs, 'retrieval_ms' => 0],
                $pipelineStartedAt,
            );

            return;
        }

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
            yield from $this->streamInfographicReply(
                $conversation,
                $userMessage,
                $evidence,
                $decision,
                $traceId,
                $timings,
                $pipelineStartedAt,
                $financeImagePromptContext,
            );

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
            'metadata' => [
                ...$this->traceMetadata($decision, $evidence, $traceId, $timings),
                ...$this->financeMetadata($decision, $evidence),
                ...($decision->domain === 'finance' ? ['finance_prompt' => $userMessage->content] : []),
            ],
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
        $finishReason = null;
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
                    $finishReason = data_get($chunk, 'choices.0.finish_reason') ?? $finishReason;
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
                    if ($financeValidationPeriod === null && (
                        strlen($content) - $lastPersistedLength >= 512
                        || $now - $lastPersistedAt >= 0.5
                    )) {
                        $assistant->forceFill(['content' => $content])->saveQuietly();
                        $lastPersistedAt = $now;
                        $lastPersistedLength = strlen($content);
                    }

                    // Finance answers are buffered until numeric claims have
                    // been reconciled. Streaming a wrong amount for even one
                    // frame would expose an unsafe answer before correction.
                    if ($financeValidationPeriod === null) {
                        yield ['event' => 'message.delta', 'data' => [
                            'conversation_id' => (string) $conversation->id,
                            'message_id' => (string) $assistant->id,
                            'delta' => $delta,
                        ]];
                    }
                }

                if (trim($content) === '') {
                    $lastError = new LlmException('AI provider returned an empty response.', 502);
                    if ($index < count($models) - 1) {
                        continue;
                    }

                    break;
                }

                $latency = (int) round((microtime(true) - $startedAt) * 1000);
                $truncated = $finishReason === 'length';
                $validation = $this->groundedValidator->validate($content, $evidence);
                $numericValidation = $financeValidationPeriod === null
                    ? null
                    : $this->groundedValidator->validateFinance($content, $financeValidationPeriod);
                $timings['generation_ms'] = $latency;
                $timings['total_ms'] = (int) round((microtime(true) - $pipelineStartedAt) * 1000);
                $timings['first_token_ms'] = $firstTokenAt === null
                    ? null
                    : (int) round(($firstTokenAt - $generationStartedAt) * 1000);
                $reconciled = $numericValidation !== null && ! $numericValidation->ok;
                $cleanContent = $reconciled
                    ? $this->financeSnapshot->deterministicSummary($financeValidationPeriod)
                    : $this->sanitizeAssistantReply($content);
                $cleanContent = $this->appendCitations($cleanContent, $evidence);
                // $cleanContent = $this->sanitizeAssistantReply($this->appendCitations($content, $evidence));  previous implementation
                $assistant->forceFill([
                    'content' => $cleanContent,
                    'status' => $truncated ? 'failed' : 'completed',
                    'request_id' => data_get($lastChunk, 'provider_request_id') ?: data_get($lastChunk, 'id'),
                    'input_tokens' => $usage['input_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                    'latency_ms' => $latency,
                    'metadata' => $this->mergeMetadata($assistant, [
                        'request_trace' => $this->requestTrace(
                            $prompt,
                            '/api/v1/chat/completions',
                            $model,
                            data_get($lastChunk, 'provider_request_id') ?: data_get($lastChunk, 'id'),
                            $truncated ? 'failed' : 'completed',
                            $latency,
                            $evidence,
                        ),
                        'provider_finish_reason' => $finishReason,
                        'truncated' => $truncated,
                        'fallback_index' => $index,
                        'fallback' => [
                            'attempts' => $index + 1,
                            'used' => $index > 0,
                        ],
                        'latency' => $timings,
                        'citation' => $validation->toArray(),
                        ...($numericValidation === null ? [] : [
                            'numeric' => $numericValidation->toArray(),
                            'finance_reconciled' => $reconciled,
                        ]),
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

                yield ['event' => $truncated ? 'message.error' : 'message.done', 'data' => [
                    'conversation_id' => (string) $conversation->id,
                    'message' => $this->messagePayload($assistant->fresh('attachments')),
                    ...($truncated ? ['error' => 'Jawaban terpotong karena batas output provider. Silakan coba lagi.'] : []),
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
        ?string $financeImagePromptContext = null,
    ): \Generator {
        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => 'streaming',
            'content' => '',
            'metadata' => [
                ...$this->traceMetadata($decision, $evidence, $traceId, $timings),
                ...$this->financeMetadata($decision, $evidence),
                ...($decision?->domain === 'finance' ? ['finance_prompt' => $userMessage->content] : []),
                ...($financeImagePromptContext !== null ? ['finance_image_prompt' => $financeImagePromptContext] : []),
            ],
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
            'delta' => "🎨 Sedang merancang gambar/ilustrasi pertanian dengan AI...\n\n",
        ]];

        foreach ($models as $index => $model) {
            try {
                $assistant->forceFill(['model' => $model, 'provider' => 'openrouter'])->save();

                $imageOptions = [
                    'image_generation' => true,
                    'size' => '1024x1024',
                ];
                if ($financeImagePromptContext !== null) {
                    $imageOptions['image_prompt_context'] = $financeImagePromptContext;
                }
                $response = $this->gateway->complete($prompt, $model, $imageOptions);

                $rawImage = data_get($response, 'choices.0.message.images.0.image_url.url');
                $textContent = trim((string) data_get($response, 'choices.0.message.content', ''));
                $publicUrl = null;

                if (is_string($rawImage) && preg_match('/^data:image\/([\w.+-]+);base64,(.+)$/', $rawImage, $matches)) {
                    $mediaType = strtolower($matches[1]);
                    $ext = match ($mediaType) {
                        'jpeg' => 'jpg',
                        'svg+xml' => 'svg',
                        default => preg_replace('/[^a-z0-9]/', '', $mediaType) ?: 'png',
                    };
                    $mimeType = match ($ext) {
                        'jpg' => 'image/jpeg',
                        'svg' => 'image/svg+xml',
                        default => 'image/'.$ext,
                    };
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
                        'mime_type' => $mimeType,
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

                $title = $conversation->title ?: 'Visual Pertanian';
                $textContent = $this->appendCitations($textContent, $evidence);
                $validation = $this->groundedValidator->validate($textContent, $evidence);
                $finalContent = '!['.$title.']('.$publicUrl.")\n\n".$textContent;

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
        Log::warning('Agricultural image generation failed.', [
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

    /**
     * Render finance values locally so exact figures are never delegated to a
     * generative image model. This uses the same attachment/message contract
     * as provider generated images, allowing the frontend to render it with
     * no special case.
     *
     * @param  array{requested: bool, available: bool, year: int|null, month: string|null, month_label: string|null, income: int|null, expense: int|null, net: int|null, transaction_count: int, period: string}  $period
     * @param  array<string, int|null>  $timings
     * @return \Generator<int, array{event: string, data: array<string, mixed>}, mixed, void>
     */
    protected function streamDeterministicFinanceImageReply(
        AiConversation $conversation,
        AiRouteDecision $decision,
        array $period,
        ?string $traceId,
        array $timings,
        ?float $pipelineStartedAt,
    ): \Generator {
        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => 'streaming',
            'content' => '',
            'provider' => 'local',
            'model' => 'finance-svg-v1',
            'metadata' => [
                ...$this->traceMetadata($decision, [], $traceId, $timings),
                'deterministic' => true,
                'is_infographic' => true,
                'finance_image_period' => $period,
            ],
        ]);
        $startedAt = microtime(true);

        yield ['event' => 'message.start', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'route' => $decision->toArray(),
            'retrieval' => $this->retrievalMetadata([]),
        ]];
        yield ['event' => 'message.delta', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'delta' => "🎨 Menyiapkan gambar laporan keuangan dari data terverifikasi…\n\n",
        ]];

        try {
            $binary = $this->financeImageRenderer->render($period);
            $scope = ($period['month_label'] ?? null) !== null
                ? (string) $period['month_label'].' '.(int) $period['year']
                : (($period['year'] ?? null) !== null ? 'Tahun '.(int) $period['year'] : (string) $period['period']);
            $safeScope = Str::slug($scope) ?: 'periode';
            $filename = 'finance-'.$safeScope.'-'.Str::uuid().'.svg';
            $relativePath = 'ai/infographics/'.$filename;
            if (! Storage::disk('public')->put($relativePath, $binary)) {
                throw new \RuntimeException('Finance SVG could not be persisted.');
            }
            $publicUrl = '/storage/'.$relativePath;
            $size = strlen($binary);

            AiAttachment::create([
                'ai_conversation_id' => $conversation->id,
                'ai_message_id' => $assistant->id,
                'user_id' => $conversation->user_id,
                'disk' => 'public',
                'path' => $relativePath,
                'original_name' => 'laporan-keuangan-'.$safeScope.'.svg',
                'mime_type' => 'image/svg+xml',
                'size' => $size,
                'sha256' => hash('sha256', $binary),
                'kind' => 'image',
                'status' => 'ready',
                'metadata' => [
                    'is_generated' => true,
                    'generator' => 'finance-svg-v1',
                    'url' => $publicUrl,
                    'exact_values' => [
                        'income' => (int) $period['income'],
                        'expense' => (int) $period['expense'],
                        'net' => (int) $period['net'],
                    ],
                ],
            ]);

            $format = fn (int $value): string => ($value < 0 ? '-' : '').'Rp'.number_format(abs($value), 0, ',', '.');
            $text = "### 📊 Laporan Keuangan {$scope}\n\n"
                .'Pemasukan **'.$format((int) $period['income']).'** · '
                .'Pengeluaran **'.$format((int) $period['expense']).'** · '
                .'Laba bersih **'.$format((int) $period['net'])."**.\n\n"
                .'Sumber: Data finance demo terverifikasi; angka pada gambar dihitung dari transaksi periode tersebut.';
            $finalContent = '!['.'Laporan Keuangan '.$scope.']('.$publicUrl.")\n\n".$text;
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $timings['generation_ms'] = $latency;
            $timings['total_ms'] = $pipelineStartedAt === null
                ? $latency
                : (int) round((microtime(true) - $pipelineStartedAt) * 1000);

            $assistant->forceFill([
                'content' => $finalContent,
                'status' => 'completed',
                'latency_ms' => $latency,
                'metadata' => $this->mergeMetadata($assistant, [
                    'is_infographic' => true,
                    'has_image' => true,
                    'deterministic' => true,
                    'image_generation' => [
                        'status' => 'deterministic',
                        'provider' => 'local',
                        'model' => 'finance-svg-v1',
                        'source' => 'farmDemoData.json',
                        'exact_values' => [
                            'income' => (int) $period['income'],
                            'expense' => (int) $period['expense'],
                            'net' => (int) $period['net'],
                        ],
                    ],
                    'finance_image_period' => $period,
                    'latency' => $timings,
                ]),
            ])->save();
            $conversation->forceFill(['last_message_at' => now()])->save();
            $this->recordUsage($conversation, $assistant, [], $latency, null, 'deterministic');

            yield ['event' => 'message.delta', 'data' => [
                'conversation_id' => (string) $conversation->id,
                'message_id' => (string) $assistant->id,
                'delta' => $finalContent,
            ]];
            yield ['event' => 'message.done', 'data' => [
                'conversation_id' => (string) $conversation->id,
                'message' => $this->messagePayload($assistant->fresh('attachments')),
            ]];
        } catch (\Throwable $exception) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $assistant->forceFill([
                'content' => '',
                'status' => 'failed',
                'error_message' => 'Laporan keuangan tidak dapat dirender.',
                'latency_ms' => $latency,
                'metadata' => $this->mergeMetadata($assistant, [
                    'is_infographic' => true,
                    'has_image' => false,
                    'deterministic' => true,
                    'image_generation' => [
                        'status' => 'failed',
                        'provider' => 'local',
                        'model' => 'finance-svg-v1',
                        'reason' => $exception::class,
                    ],
                ]),
            ])->save();
            Log::error('Deterministic finance image rendering failed.', [
                'conversation_id' => $conversation->id,
                'error_type' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
            yield ['event' => 'message.error', 'data' => [
                'conversation_id' => (string) $conversation->id,
                'message_id' => (string) $assistant->id,
                'error' => 'Gambar laporan keuangan tidak dapat dibuat dari data terverifikasi.',
            ]];
        }
    }

    /**
     * Render a named garden from the canonical farmDemoData.json snapshot.
     * Exact labels stay in a local SVG so image generation cannot hallucinate
     * a different location, crop, area, or status.
     *
     * @param array{
     *     id: string,
     *     name: string,
     *     city: string,
     *     district: string,
     *     address: string,
     *     used_hectare: int|float,
     *     available_hectare: int|float,
     *     seed_type: string,
     *     seed_type_detail: string,
     *     health_status: string,
     *     image: string,
     *     insight: array<string, mixed>
     * } $snapshot
     * @param  array<string, int|null>  $timings
     * @return \Generator<int, array{event: string, data: array<string, mixed>}, mixed, void>
     */
    protected function streamDeterministicFarmImageReply(
        AiConversation $conversation,
        AiRouteDecision $decision,
        array $snapshot,
        ?string $traceId,
        array $timings,
        ?float $pipelineStartedAt,
    ): \Generator {
        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => 'streaming',
            'content' => '',
            'provider' => 'local',
            'model' => 'farm-svg-v1',
            'metadata' => [
                ...$this->traceMetadata($decision, [], $traceId, $timings),
                'deterministic' => true,
                'is_infographic' => true,
                'farm_snapshot' => $snapshot,
            ],
        ]);
        $startedAt = microtime(true);

        yield ['event' => 'message.start', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'route' => $decision->toArray(),
            'retrieval' => $this->retrievalMetadata([]),
        ]];
        yield ['event' => 'message.delta', 'data' => [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistant->id,
            'delta' => "🎨 Menyiapkan gambar kebun dari data terverifikasi…\n\n",
        ]];

        try {
            $binary = $this->farmImageRenderer->render($snapshot);
            $safeName = Str::slug($snapshot['name']) ?: 'kebun';
            $filename = 'farm-'.$safeName.'-'.Str::uuid().'.svg';
            $relativePath = 'ai/infographics/'.$filename;
            if (! Storage::disk('public')->put($relativePath, $binary)) {
                throw new \RuntimeException('Farm SVG could not be persisted.');
            }
            $publicUrl = '/storage/'.$relativePath;
            $size = strlen($binary);

            AiAttachment::create([
                'ai_conversation_id' => $conversation->id,
                'ai_message_id' => $assistant->id,
                'user_id' => $conversation->user_id,
                'disk' => 'public',
                'path' => $relativePath,
                'original_name' => 'kondisi-'.$safeName.'.svg',
                'mime_type' => 'image/svg+xml',
                'size' => $size,
                'sha256' => hash('sha256', $binary),
                'kind' => 'image',
                'status' => 'ready',
                'metadata' => [
                    'is_generated' => true,
                    'generator' => 'farm-svg-v1',
                    'source' => 'farmDemoData.json',
                    'url' => $publicUrl,
                    'garden_id' => $snapshot['id'],
                ],
            ]);

            $name = $snapshot['name'];
            $location = trim(implode(', ', array_filter([
                $snapshot['district'],
                $snapshot['city'],
            ])));
            $crop = trim(implode(' ', array_filter([
                $snapshot['seed_type'],
                $snapshot['seed_type_detail'],
            ])));
            $formatArea = static fn (int|float $value): string => number_format((float) $value, 0, ',', '.').' ha';
            $used = $formatArea($snapshot['used_hectare']);
            $available = $formatArea($snapshot['available_hectare']);
            $text = "### 🌽 Kondisi {$name}\n\n"
                ."Data kebun terverifikasi dari farmDemoData.json.\n\n"
                ."- **Komoditas:** {$crop}\n"
                ."- **Lokasi:** {$location}\n"
                ."- **Luas terpakai:** {$used}\n"
                ."- **Lahan tersedia:** {$available}\n"
                ."- **Status:** {$snapshot['health_status']}\n\n"
                .'Sumber: Data kebun terverifikasi; atribut yang tidak tercatat tidak ditambahkan.';
            $finalContent = '!['.'Kondisi '.$name.']('.$publicUrl.")\n\n".$text;
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $timings['generation_ms'] = $latency;
            $timings['total_ms'] = $pipelineStartedAt === null
                ? $latency
                : (int) round((microtime(true) - $pipelineStartedAt) * 1000);

            $assistant->forceFill([
                'content' => $finalContent,
                'status' => 'completed',
                'latency_ms' => $latency,
                'metadata' => $this->mergeMetadata($assistant, [
                    'is_infographic' => true,
                    'has_image' => true,
                    'deterministic' => true,
                    'image_generation' => [
                        'status' => 'deterministic',
                        'provider' => 'local',
                        'model' => 'farm-svg-v1',
                        'source' => 'farmDemoData.json',
                        'garden_id' => $snapshot['id'],
                    ],
                    'farm_snapshot' => $snapshot,
                    'latency' => $timings,
                ]),
            ])->save();
            $conversation->forceFill(['last_message_at' => now()])->save();
            $this->recordUsage($conversation, $assistant, [], $latency, null, 'deterministic');

            yield ['event' => 'message.delta', 'data' => [
                'conversation_id' => (string) $conversation->id,
                'message_id' => (string) $assistant->id,
                'delta' => $finalContent,
            ]];
            yield ['event' => 'message.done', 'data' => [
                'conversation_id' => (string) $conversation->id,
                'message' => $this->messagePayload($assistant->fresh('attachments')),
            ]];
        } catch (\Throwable $exception) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $assistant->forceFill([
                'content' => '',
                'status' => 'failed',
                'error_message' => 'Gambar kebun tidak dapat dirender.',
                'latency_ms' => $latency,
                'metadata' => $this->mergeMetadata($assistant, [
                    'is_infographic' => true,
                    'has_image' => false,
                    'deterministic' => true,
                    'image_generation' => [
                        'status' => 'failed',
                        'provider' => 'local',
                        'model' => 'farm-svg-v1',
                        'reason' => $exception::class,
                    ],
                ]),
            ])->save();
            Log::error('Deterministic farm image rendering failed.', [
                'conversation_id' => $conversation->id,
                'garden_id' => $snapshot['id'],
                'error_type' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
            yield ['event' => 'message.error', 'data' => [
                'conversation_id' => (string) $conversation->id,
                'message_id' => (string) $assistant->id,
                'error' => 'Gambar kebun tidak dapat dibuat dari data terverifikasi.',
            ]];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $evidence
     * @return array<string, mixed>
     */
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
        $systemText = $this->traceText($system);
        $userText = $this->traceText($user);

        return [
            // Keep operational correlation data, but never persist or return
            // the raw system/user prompts. The system prompt can contain
            // private team knowledge and the message payload is exposed to
            // the browser by AiConversationController.
            'user_prompt_sha256' => hash('sha256', $userText),
            'user_prompt_chars' => mb_strlen($userText),
            'system_prompt_sha256' => hash('sha256', $systemText),
            'system_prompt_chars' => mb_strlen($systemText),
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

    /** Extract text for a trace hash without copying multimodal bytes. */
    protected function traceText(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        return collect($content)
            ->filter(static fn (mixed $part): bool => is_array($part) && ($part['type'] ?? null) === 'text')
            ->map(static fn (array $part): string => (string) ($part['text'] ?? ''))
            ->filter(static fn (string $text): bool => trim($text) !== '')
            ->implode("\n");
    }

    protected function userFacingImageError(?\Throwable $error): string
    {
        if ($error instanceof LlmException && $error->getMessage() === 'AI provider is not configured.') {
            return 'Model gambar AI belum dikonfigurasi. Tidak ada gambar lokal yang digunakan.';
        }
        if ($error instanceof LlmException && $error->status === 429) {
            return 'Image generation sedang dibatasi provider (rate limit). Silakan coba lagi beberapa saat.';
        }
        if ($error instanceof LlmException && in_array($error->status, [408, 504], true)) {
            return 'Image generation timeout. Request concurrent tetap terisolasi; silakan coba lagi.';
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

        if (! in_array($decision->intent, ['knowledge', 'llm', 'infographic', 'data'], true)) {
            return [];
        }

        if ($decision->reason === 'unsupported_topic'
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
            $results = $this->retriever->retrieve($userText, $conversation->team, (int) config('ai.rag.top_k', 4));
            if ($results !== [] || ! config('ai.rag.vector_enabled', false)) {
                return $results;
            }

            // A healthy vector call can still produce no candidates (empty
            // embeddings, stale index, or a score threshold). Treat that as
            // a retrieval miss and use the deterministic in-memory corpus so
            // the model does not silently answer without evidence.
            $fallback = $this->fallbackRetriever->retrieve($userText, $conversation->team, (int) config('ai.rag.top_k', 4));

            return array_map(static function (array $item): array {
                $item['metadata'] = [
                    ...$item['metadata'],
                    'retrieval_fallback' => true,
                    'retrieval_fallback_reason' => 'empty_vector_result',
                ];

                return $item;
            }, $fallback);
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
     * @return array{finance_snapshot: array<string, mixed>}|array{}
     */
    protected function financeMetadata(?AiRouteDecision $decision, array $evidence): array
    {
        if ($decision?->domain !== 'finance') {
            return [];
        }

        $content = (string) ($evidence[0]['content'] ?? '');
        $snapshot = json_decode($content, true);

        return is_array($snapshot) ? ['finance_snapshot' => $snapshot] : [];
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
                ? 'Saya belum menangkap tujuan pertanyaannya. Sebutkan kebun, komoditas, periode, atau tujuan Anda agar saya dapat memilih data, panduan, atau gambar yang tepat.'
                : null,
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
            || $status === 404
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
        // Keep any legacy/raw provider traces server-side. New traces contain
        // only hashes and lengths, but stripping this field here also protects
        // old messages when a conversation is loaded by the browser.
        $metadata = (array) $message->metadata;
        unset($metadata['request_trace']);
        // Numeric reconciliation telemetry can contain the provider's
        // rejected claim. Keep it server-side so the browser only receives
        // the canonical replacement content.
        unset($metadata['numeric']);

        return [
            'id' => (string) $message->id,
            'role' => $message->role,
            'content' => $message->content ?? '',
            'status' => $message->status,
            'metadata' => $metadata,
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

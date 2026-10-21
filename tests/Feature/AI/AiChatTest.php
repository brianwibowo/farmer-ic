<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\EmbeddingGateway;
use App\AI\Contracts\LlmGateway;
use App\AI\Exceptions\EmbeddingException;
use App\AI\Exceptions\LlmException;
use App\AI\Services\AiChatService;
use App\AI\Services\OpenRouterGateway;
use App\Enums\TeamRole;
use App\Models\AiAttachment;
use App\Models\AiConversation;
use App\Models\AiKnowledgeDocument;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_list_ai_conversations(): void
    {
        $user = User::factory()->create();

        $this->get(route('ai.conversations.index', $user->currentTeam->slug))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_open_the_team_ai_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ai.page', $user->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('farmer/Index'));
    }

    public function test_voice_transcription_can_be_disabled_without_exposing_provider_details(): void
    {
        config(['ai.voice.enabled' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('ai.voice.transcribe', $user->currentTeam->slug))
            ->assertStatus(503)
            ->assertJsonPath('message', 'Voice transcription is not configured.');
    }

    public function test_voice_synthesis_requires_an_explicit_tts_model(): void
    {
        config([
            'ai.voice.enabled' => true,
            'ai.voice.tts_model' => null,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('ai.voice.synthesize', $user->currentTeam->slug), ['text' => 'Halo'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'TTS model belum dikonfigurasi.');
    }

    public function test_a_user_can_send_a_message_and_receive_an_sse_response(): void
    {
        $this->app->instance(LlmGateway::class, new class implements LlmGateway
        {
            public function complete(array $messages, string $model, array $options = []): array
            {
                return [];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                yield [
                    'id' => 'test-request',
                    'choices' => [['delta' => ['content' => 'Halo dari AI.']]],
                    'usage' => ['prompt_tokens' => 4, 'completion_tokens' => 3],
                ];
            }
        });
        config(['ai.openrouter.api_key' => 'test-key']);

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this->actingAs($user)->post(route('ai.messages.store', $team->slug), [
            'message' => 'Halo',
        ], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $stream = $response->streamedContent();
        $this->assertStringContainsString('message.delta', $stream);
        $this->assertStringContainsString('Halo dari AI.', $stream);
        $this->assertDatabaseHas('ai_messages', [
            'role' => 'assistant',
            'content' => 'Halo dari AI.',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $user->id,
            'title' => 'Halo',
        ]);
    }

    public function test_grounded_route_injects_retrieved_evidence_citation_and_trace_metadata(): void
    {
        $gateway = new class implements LlmGateway
        {
            /** @var array<int, array<string, mixed>> */
            public array $capturedMessages = [];

            /** @var array<string, mixed> */
            public array $capturedOptions = [];

            public function complete(array $messages, string $model, array $options = []): array
            {
                return [];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                $this->capturedMessages = $messages;
                $this->capturedOptions = $options;
                yield [
                    'id' => 'grounded-request',
                    'choices' => [['delta' => ['content' => 'Gunakan langkah sesuai sumber [S1].']]],
                    'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 8],
                ];
            }
        };
        $this->app->instance(LlmGateway::class, $gateway);
        config(['ai.openrouter.api_key' => 'test-key']);

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $document = $team->aiKnowledgeDocuments()->create([
            'created_by' => $user->id,
            'title' => 'Panduan kedelai demo',
            'content' => 'Penanaman kedelai perlu mengikuti kondisi tanah dan panduan lokal.',
            'status' => AiKnowledgeDocument::STATUS_PUBLISHED,
            'source' => 'Panduan internal kedelai',
            'content_hash' => hash('sha256', 'Penanaman kedelai perlu mengikuti kondisi tanah dan panduan lokal.'),
            'version' => 1,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(
            route('ai.messages.store', $team->slug),
            ['message' => 'bagaimana cara menanam kedelai'],
            ['Accept' => 'text/event-stream'],
        );

        $response->assertOk();
        $stream = $response->streamedContent();
        $this->assertStringContainsString('Sumber: [S1]', $stream);
        $this->assertStringContainsString('retrieval', $stream);
        $this->assertStringContainsString('Menyusun jawaban dari ', $stream);
        $this->assertStringContainsString('Panduan kedelai demo', implode(' ', array_map(
            static fn (array $message): string => (string) ($message['content'] ?? ''),
            $gateway->capturedMessages,
        )));
        $this->assertSame(1024, $gateway->capturedOptions['max_tokens']);
        $assistant = AiConversation::query()->latest('id')->firstOrFail()->messages()->where('role', 'assistant')->latest('id')->firstOrFail();
        $this->assertSame('assistant', $assistant->role);
        $this->assertStringContainsString('Panduan internal kedelai', (string) $assistant->content);
        $this->assertMatchesRegularExpression('/Sumber: (?:\[S\d+\].*Panduan internal kedelai|.*Panduan internal kedelai.*)/s', (string) $assistant->content);
        $this->assertIsArray($assistant->metadata);
        $this->assertSame('llm', data_get($assistant->metadata, 'route.intent'));
        $this->assertDatabaseHas('ai_usage_events', [
            'ai_message_id' => $assistant->id,
        ]);

        $this->assertGreaterThan(0, $document->id);
    }

    public function test_current_finance_uses_deterministic_snapshot_and_calls_provider(): void
    {
        config(['ai.rag.vector_enabled' => true]);
        $this->app->instance(LlmGateway::class, new class implements LlmGateway
        {
            public function complete(array $messages, string $model, array $options = []): array
            {
                return [];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                yield ['id' => 'finance-analysis-request', 'choices' => [['delta' => ['content' => 'Pemasukan Rp170.000.000, pengeluaran Rp60.000.000, laba bersih Rp110.000.000 [S1].']]]];
            }
        });

        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(
            route('ai.messages.store', $user->currentTeam->slug),
            ['message' => 'finansial bulan ini'],
            ['Accept' => 'text/event-stream'],
        );

        $response->assertOk();
        $stream = $response->streamedContent();
        $this->assertStringContainsString('170.000.000', $stream);
        $this->assertStringContainsString('Sumber:', $stream);
        $this->assertStringContainsString('finance-demo-snapshot', $stream);
    }

    public function test_vector_retrieval_failure_falls_back_to_in_memory_without_failing_chat(): void
    {
        config([
            'ai.rag.vector_enabled' => true,
            'ai.openrouter.api_key' => 'test-key',
        ]);
        $this->app->instance(EmbeddingGateway::class, new class implements EmbeddingGateway
        {
            public function embed(array $inputs): array
            {
                throw new EmbeddingException('embedding unavailable');
            }
        });
        $this->app->instance(LlmGateway::class, new class implements LlmGateway
        {
            public function complete(array $messages, string $model, array $options = []): array
            {
                return [];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                yield [
                    'id' => 'fallback-retrieval-request',
                    'choices' => [['delta' => ['content' => 'Jawaban berbasis panduan.']]],
                ];
            }
        });

        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(
            route('ai.messages.store', $user->currentTeam->slug),
            ['message' => 'cara nanam kedelai dari awal'],
            ['Accept' => 'text/event-stream'],
        );

        $response->assertOk();
        $stream = $response->streamedContent();
        $this->assertStringContainsString('Jawaban berbasis panduan.', $stream);
        $this->assertStringContainsString('Sumber:', $stream);
        $this->assertStringContainsString('in-memory', $stream);
    }

    public function test_strict_grounding_abstains_when_provider_omits_citation(): void
    {
        config([
            'ai.rag.require_citations' => true,
            'ai.openrouter.api_key' => 'test-key',
        ]);
        $this->app->instance(LlmGateway::class, new class implements LlmGateway
        {
            public function complete(array $messages, string $model, array $options = []): array
            {
                return [];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                yield [
                    'id' => 'ungrounded-request',
                    'choices' => [['delta' => ['content' => 'Jawaban tanpa sumber.']]],
                ];
            }
        });

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->aiKnowledgeDocuments()->create([
            'created_by' => $user->id,
            'title' => 'Panduan kedelai demo',
            'content' => 'Kedelai perlu dipantau setelah hujan.',
            'status' => AiKnowledgeDocument::STATUS_PUBLISHED,
            'source' => 'Panduan internal kedelai',
            'content_hash' => hash('sha256', 'Kedelai perlu dipantau setelah hujan.'),
            'version' => 1,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(
            route('ai.messages.store', $team->slug),
            ['message' => 'bagaimana cara menanam kedelai'],
            ['Accept' => 'text/event-stream'],
        );

        $response->assertOk();
        $response->streamedContent();
        $assistant = AiConversation::query()->latest('id')->firstOrFail()->messages()->where('role', 'assistant')->latest('id')->firstOrFail();
        $this->assertSame('completed', $assistant->status);
        $this->assertStringContainsString('belum bisa memastikan', (string) $assistant->content);
        $this->assertStringNotContainsString('Jawaban tanpa sumber.', (string) $assistant->content);
    }

    public function test_provider_failure_uses_the_next_configured_model(): void
    {
        $this->app->instance(LlmGateway::class, new class implements LlmGateway
        {
            public function complete(array $messages, string $model, array $options = []): array
            {
                return [];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                if ($model === 'primary-model') {
                    throw new LlmException('provider unavailable', 503);
                }

                yield [
                    'id' => 'fallback-request',
                    'choices' => [['delta' => ['content' => 'Jawaban fallback.']]],
                ];
            }
        });
        config([
            'ai.models.primary' => 'primary-model',
            'ai.models.text_fallbacks' => ['fallback-model'],
            'ai.openrouter.api_key' => 'test-key',
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->post(route('ai.messages.store', $user->currentTeam->slug), ['message' => 'Halo']);

        $response->assertOk();
        $response->streamedContent();
        $this->assertDatabaseHas('ai_messages', [
            'role' => 'assistant',
            'model' => 'fallback-model',
            'content' => 'Jawaban fallback.',
            'status' => 'completed',
        ]);

        $assistant = AiConversation::query()
            ->latest('id')
            ->firstOrFail()
            ->messages()
            ->where('role', 'assistant')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(1, data_get($assistant->metadata, 'fallback_index'));
        $this->assertSame('fallback-model', $assistant->model);
        $this->assertSame('fallback-request', $assistant->request_id);
    }

    public function test_missing_provider_key_returns_an_actionable_stream_error(): void
    {
        $this->app->instance(LlmGateway::class, new OpenRouterGateway);
        config(['ai.openrouter.api_key' => null]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('ai.messages.store', $user->currentTeam->slug), ['message' => 'Tes konfigurasi']);

        $this->assertStringContainsString(
            'AI belum dikonfigurasi di server.',
            $response->streamedContent(),
        );
    }

    public function test_unsupported_upload_is_rejected_before_the_provider_is_called(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('ai.messages.store', $user->currentTeam->slug), [
                'message' => 'Periksa file ini',
                'attachments' => [UploadedFile::fake()->create('script.php', 5, 'text/plain')],
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('attachments.0');
        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_published_team_knowledge_is_snapshotted_for_new_conversations(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $created = $this->actingAs($user)
            ->postJson(route('ai.knowledge.store', $team->slug), [
                'title' => 'Aturan irigasi',
                'content' => 'Irigasi dilakukan pada pagi hari.',
                'source' => 'Panduan internal',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($user)
            ->postJson(route('ai.knowledge.publish', [$team->slug, $created]))
            ->assertOk()
            ->assertJsonPath('status', AiKnowledgeDocument::STATUS_PUBLISHED);

        $this->actingAs($user)
            ->postJson(route('ai.conversations.store', $team->slug))
            ->assertCreated();

        $snapshot = AiConversation::query()->latest('id')->firstOrFail()->knowledge_snapshot;
        $this->assertStringContainsString(
            'Irigasi dilakukan pada pagi hari.',
            (string) ($snapshot['adaptive'] ?? ''),
        );
    }

    public function test_team_members_cannot_manage_adaptive_knowledge(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($member)
            ->postJson(route('ai.knowledge.store', $team->slug), [
                'title' => 'Tidak boleh',
                'content' => 'Konten',
            ])
            ->assertForbidden();
    }

    public function test_conversations_are_scoped_to_the_authenticated_user_and_team(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $team = $user->currentTeam;

        $own = AiConversation::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
        AiConversation::factory()->create(['team_id' => $team->id, 'user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('ai.conversations.index', $team->slug));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', (string) $own->id);
        $response->assertJsonCount(1, 'data');
    }

    public function test_a_user_can_request_infographic_and_receive_generated_image(): void
    {
        Storage::fake('public');
        $this->app->instance(LlmGateway::class, new class implements LlmGateway
        {
            public function complete(array $messages, string $model, array $options = []): array
            {
                $fakePng = base64_encode('fake-png-data');

                return [
                    'id' => 'test-infographic-req',
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Berikut infografis diagram pertanian.',
                                'images' => [
                                    [
                                        'type' => 'image_url',
                                        'image_url' => ['url' => 'data:image/png;base64,'.$fakePng],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
                ];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                yield [];
            }
        });
        config(['ai.openrouter.api_key' => 'test-key']);

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this->actingAs($user)->post(route('ai.messages.store', $team->slug), [
            'message' => 'Buatkan infografis smart farming jagung',
            'is_infographic' => true,
        ], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $stream = $response->streamedContent();
        $this->assertStringContainsString('message.done', $stream);
        $this->assertStringContainsString('Berikut infografis diagram pertanian.', $stream);
        $this->assertStringContainsString('ai/infographics', stripslashes($stream));

        $this->assertDatabaseHas('ai_attachments', [
            'disk' => 'public',
            'kind' => 'image',
        ]);
        $assistant = AiMessage::query()->where('role', 'assistant')->latest('id')->firstOrFail();
        $this->assertSame('generated', data_get($assistant->metadata, 'image_generation.status'));
        $this->assertTrue((bool) data_get($assistant->metadata, 'has_image'));
    }

    public function test_text_only_image_provider_response_is_failed_and_never_uses_local_asset(): void
    {
        Storage::fake('public');
        $this->app->instance(LlmGateway::class, new class implements LlmGateway
        {
            public function complete(array $messages, string $model, array $options = []): array
            {
                return [
                    'id' => 'text-only-image-response',
                    'choices' => [['message' => ['content' => 'Saya tidak dapat membuat gambar.']]],
                ];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                yield [];
            }
        });
        config(['ai.openrouter.api_key' => 'test-key', 'ai.models.infographic_fallbacks' => []]);

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $response = $this->actingAs($user)->post(route('ai.messages.store', $team->slug), [
            'message' => 'Buatkan gambar infografis jagung',
            'is_infographic' => true,
        ], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $stream = $response->streamedContent();
        $this->assertStringContainsString('message.error', $stream);
        $this->assertStringContainsString('Image generation gagal', $stream);
        $this->assertStringNotContainsString('/images/ai-infographics/', $stream);
        $assistant = AiMessage::query()->where('role', 'assistant')->latest('id')->firstOrFail();
        $this->assertSame('failed', $assistant->status);
        $this->assertSame('failed', data_get($assistant->metadata, 'image_generation.status'));
        $this->assertSame('AI image provider returned no generated image.', data_get($assistant->metadata, 'image_generation.reason'));
        $this->assertSame(0, AiAttachment::query()->count());
    }

    public function test_two_concurrent_conversations_keep_trace_and_content_isolated(): void
    {
        $this->app->instance(LlmGateway::class, new class implements LlmGateway
        {
            public function complete(array $messages, string $model, array $options = []): array
            {
                return [];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                $prompt = (string) data_get($messages, '1.content', '');
                $marker = str_contains($prompt, 'USER_A') ? 'USER_A' : 'USER_B';
                yield ['id' => 'request-'.$marker, 'choices' => [['delta' => ['content' => 'Jawaban '.$marker.' selesai.'], 'finish_reason' => 'stop']]];
            }
        });
        config(['ai.openrouter.api_key' => 'test-key']);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $ca = $this->app->make(AiChatService::class)->createConversation($a, $a->currentTeam);
        $cb = $this->app->make(AiChatService::class)->createConversation($b, $b->currentTeam);
        $service = $this->app->make(AiChatService::class);
        $ma = $service->createUserMessage($ca, $a, 'USER_A jelaskan panduan kebun jagung');
        $mb = $service->createUserMessage($cb, $b, 'USER_B jelaskan panduan kebun kedelai');
        $ga = $service->streamReply($ca->fresh(), false, $ma);
        $gb = $service->streamReply($cb->fresh(), false, $mb);
        foreach ([$ga, $gb] as $g) {
            foreach ($g as $_) {
            }
        }
        $aa = $ca->fresh()->messages()->where('role', 'assistant')->firstOrFail();
        $ab = $cb->fresh()->messages()->where('role', 'assistant')->firstOrFail();
        $this->assertSame('completed', $aa->status);
        $this->assertSame('completed', $ab->status);
        $this->assertStringContainsString('USER_A', (string) $aa->content);
        $this->assertStringContainsString('USER_B', (string) $ab->content);
        $this->assertNotSame(data_get($aa->metadata, 'trace_id'), data_get($ab->metadata, 'trace_id'));
        $this->assertSame('stop', data_get($aa->metadata, 'provider_finish_reason'));
        $this->assertSame('stop', data_get($ab->metadata, 'provider_finish_reason'));
    }
}

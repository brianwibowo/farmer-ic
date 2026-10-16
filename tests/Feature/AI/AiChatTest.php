<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\LlmGateway;
use App\AI\Exceptions\LlmException;
use App\AI\Services\OpenRouterGateway;
use App\Enums\TeamRole;
use App\Models\AiConversation;
use App\Models\AiKnowledgeDocument;
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
    }
}

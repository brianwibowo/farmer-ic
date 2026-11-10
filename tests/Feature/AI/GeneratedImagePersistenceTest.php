<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\LlmGateway;
use App\AI\Exceptions\LlmException;
use App\AI\Services\AiChatService;
use App\Models\AiAttachment;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Image storage and history integration against the separate guarded PostgreSQL test database. */
class GeneratedImagePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1cAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai.openrouter.api_key' => 'test-key',
            'ai.models.infographic_model' => 'image-primary',
            'ai.models.infographic_fallbacks' => [],
            'ai.rag.vector_enabled' => false,
        ]);
        Storage::fake('public');
    }

    public function test_generated_image_is_a_readable_bitmap_that_survives_conversation_reload(): void
    {
        $this->gateway(['data:image/png;base64,'.self::PNG]);
        $user = User::factory()->create(['role' => 'owner']);
        $this->actingAs($user);
        $stream = $this->sendImage($user);
        $this->assertStringContainsString('message.done', $stream);

        $attachment = AiAttachment::query()->where('kind', 'image')->firstOrFail();
        $bytes = Storage::disk('public')->get($attachment->path);
        $dimensions = getimagesizefromstring($bytes);
        $this->assertIsArray($dimensions);
        $this->assertSame('image/png', $dimensions['mime']);
        $this->assertSame(1, $dimensions[0]);
        $this->assertSame(hash('sha256', $bytes), $attachment->sha256);
        $this->assertSame(strlen($bytes), $attachment->size);

        $this->getJson(route('ai.conversations.show', [
            'current_team' => $user->currentTeam->slug,
            'conversation' => $attachment->ai_conversation_id,
        ]))->assertOk()
            ->assertJsonPath('messages.1.status', 'completed')
            ->assertJsonPath('messages.1.attachments.0.url', '/storage/'.$attachment->path)
            ->assertJsonPath('messages.1.attachments.0.type', 'image/png');
    }

    /** @return array<string, array{string}> */
    public static function invalidImageData(): array
    {
        return [
            'invalid base64' => ['data:image/png;base64,not-valid-@@@'],
            'text instead of pixels' => ['data:image/png;base64,'.base64_encode('fake-png-data')],
            'spoofed MIME' => ['data:image/jpeg;base64,'.self::PNG],
            'untrusted SVG' => ['data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            'remote URL without inline image bytes' => ['https://images.example.com/generated.png'],
        ];
    }

    #[DataProvider('invalidImageData')]
    public function test_invalid_generated_image_never_becomes_a_ready_attachment(string $dataUrl): void
    {
        Http::fake();
        $this->gateway([$dataUrl]);
        $user = User::factory()->create(['role' => 'owner']);
        $this->actingAs($user);
        $stream = $this->sendImage($user);

        $this->assertStringContainsString('message.error', $stream);
        $this->assertStringNotContainsString('message.done', $stream);
        $this->assertSame(0, AiAttachment::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        Http::assertNothingSent();
        $this->assertDatabaseHas('ai_messages', ['role' => 'assistant', 'status' => 'failed', 'content' => '']);
    }

    public function test_oversized_generated_image_is_rejected_before_persistence(): void
    {
        config(['ai.attachments.max_size_kb' => 1]);
        $this->gateway(['data:image/png;base64,'.base64_encode(base64_decode(self::PNG, true).str_repeat("\0", 2048))]);
        $user = User::factory()->create(['role' => 'owner']);
        $this->actingAs($user);

        $this->assertStringContainsString('message.error', $this->sendImage($user));
        $this->assertSame(0, AiAttachment::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_failed_storage_write_does_not_report_a_completed_image(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::set('public', $disk);
        $this->gateway(['data:image/png;base64,'.self::PNG]);
        $user = User::factory()->create(['role' => 'owner']);
        $this->actingAs($user);

        $stream = $this->sendImage($user);
        $this->assertStringContainsString('message.error', $stream);
        $this->assertStringNotContainsString('message.done', $stream);
        $this->assertSame(0, AiAttachment::query()->count());
    }

    public function test_invalid_primary_image_can_fall_back_to_a_valid_image_model(): void
    {
        config(['ai.models.infographic_fallbacks' => ['image-fallback']]);
        $gateway = $this->gateway(['data:image/png;base64,invalid@@@', 'data:image/png;base64,'.self::PNG]);
        $user = User::factory()->create(['role' => 'owner']);
        $this->actingAs($user);

        $this->assertStringContainsString('message.done', $this->sendImage($user));
        $this->assertSame(['image-primary', 'image-fallback'], $gateway->models);
        $this->assertSame(1, AiAttachment::query()->count());
        $assistant = AiMessage::query()->where('role', 'assistant')->firstOrFail();
        $this->assertSame('fallback', data_get($assistant->metadata, 'image_generation.status'));
        $this->assertSame(2, data_get($assistant->metadata, 'image_generation.attempts'));
    }

    public function test_non_retryable_failure_reports_the_actual_attempt_count_and_hides_provider_details(): void
    {
        config(['ai.models.infographic_fallbacks' => ['unused-image-fallback']]);
        $gateway = $this->gateway([new LlmException('provider secret: credential-private', 401)]);
        $user = User::factory()->create(['role' => 'owner']);
        $this->actingAs($user);

        $stream = $this->sendImage($user);
        $this->assertStringContainsString('message.error', $stream);
        $this->assertStringNotContainsString('credential-private', $stream);
        $this->assertSame(['image-primary'], $gateway->models);
        $assistant = AiMessage::query()->where('role', 'assistant')->firstOrFail();
        $this->assertSame(1, data_get($assistant->metadata, 'image_generation.attempts'));
        $this->assertFalse(data_get($assistant->metadata, 'fallback.used'));
        $payload = app(AiChatService::class)->messagePayload($assistant);
        $this->assertNotEmpty($payload['error_message']);
        $this->assertStringNotContainsString('credential-private', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_private_uploaded_image_has_an_authorized_url_when_history_is_reloaded(): void
    {
        Storage::fake('local');
        config(['ai.attachments.disk' => 'local']);
        $user = User::factory()->create(['role' => 'owner']);
        $this->actingAs($user);
        $chat = app(AiChatService::class);
        $conversation = $chat->createConversation($user, $user->currentTeam);
        $message = $chat->createUserMessage($conversation, $user, 'Periksa foto daun ini.', [
            UploadedFile::fake()->createWithContent('daun.png', base64_decode(self::PNG, true)),
        ]);
        $attachment = $message->attachments->firstOrFail();
        $url = route('ai.attachments.show', ['current_team' => $user->currentTeam->slug, 'attachment' => $attachment->id], false);

        $this->getJson(route('ai.conversations.show', [
            'current_team' => $user->currentTeam->slug, 'conversation' => $conversation->id,
        ]))->assertOk()->assertJsonPath('messages.0.attachments.0.url', $url);
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/png');
    }

    /** @param list<string|LlmException> $responses */
    private function gateway(array $responses): LlmGateway
    {
        $gateway = new class($responses) implements LlmGateway
        {
            /** @var list<string> */
            public array $models = [];

            /** @param list<string|LlmException> $responses */
            public function __construct(private array $responses) {}

            public function complete(array $messages, string $model, array $options = []): array
            {
                $this->models[] = $model;
                $response = array_shift($this->responses);
                if ($response instanceof LlmException) {
                    throw $response;
                }

                return ['choices' => [['message' => ['content' => 'Ilustrasi pertanian.', 'images' => [[
                    'type' => 'image_url', 'image_url' => ['url' => $response],
                ]]]]]];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                yield [];
            }
        };
        $this->app->instance(LlmGateway::class, $gateway);

        return $gateway;
    }

    private function sendImage(User $user): string
    {
        $response = $this->post(route('ai.messages.store', $user->currentTeam->slug), [
            'message' => 'Buatkan infografis smart farming jagung', 'is_infographic' => true,
        ], ['Accept' => 'text/event-stream']);
        $response->assertOk();

        return $response->streamedContent();
    }
}

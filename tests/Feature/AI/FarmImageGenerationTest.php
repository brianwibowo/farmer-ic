<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\LlmGateway;
use App\Models\AiAttachment;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FarmImageGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_garden_image_uses_canonical_svg_without_calling_provider(): void
    {
        Storage::fake('public');
        $gateway = new class implements LlmGateway
        {
            public int $completeCalls = 0;

            public function complete(array $messages, string $model, array $options = []): array
            {
                $this->completeCalls++;

                return [];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                yield [];
            }
        };
        $this->app->instance(LlmGateway::class, $gateway);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(
            route('ai.messages.store', $user->currentTeam->slug),
            ['message' => 'Buatkan saya infografis tentang kebun tani lestari dengan data detail kebunnya juga'],
            ['Accept' => 'text/event-stream'],
        );

        $response->assertOk();
        $stream = $response->streamedContent();
        $this->assertStringContainsString('message.done', $stream);
        $this->assertStringContainsString('Kebun Tani Lestari', $stream);
        $this->assertStringContainsString('180 ha', $stream);
        $this->assertSame(0, $gateway->completeCalls);

        $attachment = AiAttachment::query()->where('kind', 'image')->latest('id')->firstOrFail();
        $this->assertSame('image/svg+xml', $attachment->mime_type);
        $this->assertSame('farm-svg-v1', data_get($attachment->metadata, 'generator'));
        $svg = Storage::disk('public')->get($attachment->path);
        $this->assertStringContainsString('Parakan, Temanggung', $svg);
        $this->assertStringContainsString('180 ha', $svg);
        $this->assertStringContainsString('750 ha', $svg);
        $this->assertStringNotContainsString('Sukamaju', $svg);
        $this->assertStringNotContainsString('2 hektar', $svg);

        $assistant = AiMessage::query()->where('role', 'assistant')->latest('id')->firstOrFail();
        $this->assertSame('deterministic', data_get($assistant->metadata, 'image_generation.status'));
        $this->assertSame('kebun-002', data_get($assistant->metadata, 'image_generation.garden_id'));
    }
}

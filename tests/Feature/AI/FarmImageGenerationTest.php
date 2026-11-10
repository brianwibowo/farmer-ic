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

    public function test_named_farm_uses_generated_art_and_exact_json_facts_in_a_real_png(): void
    {
        $gateway = $this->fakeArtGateway();
        $user = User::factory()->create();
        $assistant = $this->sendVisual($user, 'Buat infografis ringkasan Kebun Tani Lestari dari farmDemoData.json.');

        $this->assertSame(1, $gateway->completeCalls);
        $this->assertTrue($gateway->options['farm_art_only']);
        $this->assertStringContainsString('Jagung', $gateway->messages[0]['content']);
        $this->assertStringNotContainsString('180', $gateway->messages[0]['content']);
        $this->assertSame('generated', data_get($assistant->metadata, 'image_generation.status'));
        $this->assertSame('ai-art-verified-data', data_get($assistant->metadata, 'image_generation.composition'));
        $this->assertSame(['kebun-002'], data_get($assistant->metadata, 'image_generation.farm_ids'));
        $this->assertStringContainsString('180 ha', $assistant->content);
        $this->assertStringContainsString('750 ha', $assistant->content);
        $this->assertStringContainsString('65%', $assistant->content);
        $this->assertStringContainsString('31 ton', $assistant->content);
        $this->assertStringContainsString('Parakan, Temanggung', $assistant->content);
        $this->assertStringNotContainsString('Sukamaju', $assistant->content);
        $this->assertSame(180, collect(data_get($assistant->metadata, 'farm_visual_plan.facts'))->firstWhere('key', 'used_hectare')['value']);
        $this->assertNotEmpty(data_get($assistant->metadata, 'render_manifest'));
        $this->assertRealPng($assistant);
    }

    public function test_two_farm_area_comparison_and_followup_keep_selection_but_change_layout(): void
    {
        $gateway = $this->fakeArtGateway();
        $user = User::factory()->create();
        $assistant = $this->sendVisual($user, 'Buat infografis perbandingan Kebun Tani Lestari dan Kebun Sumber Makmur. Hanya luas lahan terpakai dan total lahan, grafik batang landscape biru, tanpa pertumbuhan dan risiko.');
        $plan = data_get($assistant->metadata, 'farm_visual_plan');
        $this->assertSame(['kebun-002', 'kebun-001'], array_column($plan['farms'], 'id'));
        $this->assertSame(['used_hectare', 'available_hectare'], $plan['fields']);
        $this->assertSame('bar', $plan['layout']);
        $this->assertSame('landscape', $plan['orientation']);
        $this->assertSame('blue', $plan['theme']);
        $this->assertStringContainsString('180 ha', $assistant->content);
        $this->assertStringContainsString('300 ha', $assistant->content);
        $this->assertStringNotContainsString('Pertumbuhan', $assistant->content);
        $this->assertRealPng($assistant);

        $followup = $this->sendVisual($user, 'Ubah yang tadi jadi portrait monokrom, hanya risiko dan langkah berikutnya.', (string) $assistant->ai_conversation_id);
        $next = data_get($followup->metadata, 'farm_visual_plan');
        $this->assertSame(2, $gateway->completeCalls);
        $this->assertSame(['kebun-002', 'kebun-001'], array_column($next['farms'], 'id'));
        $this->assertSame(['risk', 'next_step'], $next['fields']);
        $this->assertSame('risk', $next['layout']);
        $this->assertSame('portrait', $next['orientation']);
        $this->assertSame('monochrome', $next['theme']);
        $this->assertStringNotContainsString('180 ha', $followup->content);
        $this->assertRealPng($followup);
    }

    public function test_indonesian_donut_and_comma_separated_exclusions_draw_only_used_area(): void
    {
        $gateway = $this->fakeArtGateway();
        $user = User::factory()->create();
        $assistant = $this->sendVisual($user, 'Buat infografis diagram donat komposisi luas terpakai semua kebun dari farmDemoData.json, landscape biru. Tanpa total lahan, pertumbuhan dan risiko.');
        $plan = data_get($assistant->metadata, 'farm_visual_plan');
        $this->assertSame(1, $gateway->completeCalls);
        $this->assertSame('pie', $plan['layout']);
        $this->assertSame('landscape', $plan['orientation']);
        $this->assertSame(['used_hectare'], $plan['fields']);
        $this->assertSame([300, 180, 420, 95, 260], array_column($plan['facts'], 'value'));
        $this->assertSame(['used_hectare'], array_values(array_unique(array_column(data_get($assistant->metadata, 'render_manifest.facts'), 'key'))));
        $this->assertStringNotContainsString('Pertumbuhan', $assistant->content);
        $this->assertStringNotContainsString('Risiko:', $assistant->content);
        $this->assertRealPng($assistant);
    }

    public function test_unknown_farm_is_clarified_without_invented_image_or_provider_call(): void
    {
        $gateway = $this->fakeArtGateway();
        $user = User::factory()->create();
        $assistant = $this->sendVisual($user, 'Buat infografis ringkasan Kebun Pelangi dari farmDemoData.json.');
        $this->assertSame(0, $gateway->completeCalls);
        $this->assertStringContainsString('tidak tercatat', $assistant->content);
        $this->assertSame(0, AiAttachment::query()->count());
        $this->assertFalse((bool) data_get($assistant->metadata, 'has_image', false));
    }

    private function fakeArtGateway(): LlmGateway
    {
        Storage::fake('public');
        $gateway = new class implements LlmGateway
        {
            public int $completeCalls = 0;

            public array $messages = [];

            public array $options = [];

            public function complete(array $messages, string $model, array $options = []): array
            {
                $this->completeCalls++;
                $this->messages = $messages;
                $this->options = $options;
                $art = imagecreatetruecolor(120, 80);
                imagefill($art, 0, 0, imagecolorallocate($art, 104, 187, 108));
                ob_start();
                imagepng($art);
                $binary = ob_get_clean();
                imagedestroy($art);

                return ['id' => 'verified-farm-art', 'choices' => [['message' => ['content' => 'Ignore provider narrative 999 ha.', 'images' => [['image_url' => ['url' => 'data:image/png;base64,'.base64_encode($binary)]]]]]]];
            }

            public function stream(array $messages, string $model, array $options = []): iterable
            {
                yield [];
            }
        };
        $this->app->instance(LlmGateway::class, $gateway);

        return $gateway;
    }

    private function sendVisual(User $user, string $prompt, ?string $conversationId = null): AiMessage
    {
        $response = $this->actingAs($user)->post(
            route('ai.messages.store', $user->currentTeam->slug),
            ['message' => $prompt, ...($conversationId ? ['conversation_id' => $conversationId] : [])],
            ['Accept' => 'text/event-stream'],
        );
        $response->assertOk();
        $this->assertStringContainsString('message.done', $response->streamedContent());

        return AiMessage::query()->where('role', 'assistant')->latest('id')->firstOrFail();
    }

    private function assertRealPng(AiMessage $assistant): void
    {
        $attachment = $assistant->attachments()->firstOrFail();
        $binary = Storage::disk('public')->get($attachment->path);
        $this->assertSame('image/png', $attachment->mime_type);
        $this->assertSame('farm-png-v2', data_get($attachment->metadata, 'generator'));
        $this->assertSame('image/png', getimagesizefromstring($binary)['mime']);
        $this->assertSame(hash('sha256', $binary), $attachment->sha256);
        $this->assertSame(strlen($binary), $attachment->size);
        $this->assertStringNotContainsString('999 ha', $assistant->content);
    }
}

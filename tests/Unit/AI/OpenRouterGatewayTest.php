<?php

namespace Tests\Unit\AI;

use App\AI\Services\OpenRouterGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_prompt_does_not_serialize_multimodal_attachment_bytes(): void
    {
        config([
            'ai.openrouter.api_key' => 'test-key',
            'ai.openrouter.base_url' => 'https://openrouter.test/api/v1',
            'ai.openrouter.max_retries' => 0,
        ]);
        Http::fake(['*' => Http::response([
            'id' => 'image-request',
            'data' => [[
                'b64_json' => base64_encode('jpeg'),
                'media_type' => 'image/jpeg',
            ]],
        ])]);

        $result = (new OpenRouterGateway)->complete([
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Buat infografis kondisi jagung'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.str_repeat('A', 10000)]],
                ],
            ],
        ], 'image-model', ['image_generation' => true]);

        Http::assertSent(function (Request $request): bool {
            $prompt = (string) $request['prompt'];

            return str_contains($prompt, 'Buat infografis kondisi jagung')
                && ! str_contains($prompt, 'data:image/png;base64,')
                && ! isset($request['response_format'])
                && strlen($prompt) < 3000;
        });

        $this->assertSame(
            'data:image/jpeg;base64,'.base64_encode('jpeg'),
            data_get($result, 'choices.0.message.images.0.image_url.url'),
        );
    }
}

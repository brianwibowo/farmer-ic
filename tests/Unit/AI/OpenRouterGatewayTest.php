<?php

namespace Tests\Unit\AI;

use App\AI\Services\OpenRouterGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpenRouterGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_farm_art_layer_does_not_inherit_data_or_infographic_text(): void
    {
        config([
            'ai.openrouter.api_key' => 'test-key',
            'ai.openrouter.base_url' => 'https://openrouter.test/api/v1',
            'ai.openrouter.max_retries' => 0,
        ]);
        Http::fake(['*' => Http::response(['data' => [['b64_json' => 'art']]])]);
        $artPrompt = 'Original flat crop illustration. No text, no numbers, no charts.';
        (new OpenRouterGateway)->complete([
            ['role' => 'system', 'content' => '<retrieved-evidence-json>999 ha</retrieved-evidence-json>'],
            ['role' => 'assistant', 'content' => 'Previous garden 180 ha.'],
            ['role' => 'user', 'content' => $artPrompt],
        ], 'image-model', ['image_generation' => true, 'farm_art_only' => true]);
        Http::assertSent(fn (Request $request): bool => $request['prompt'] === $artPrompt
            && ! isset($request['input_references']));
    }

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

    public function test_image_prompt_carries_canonical_farm_evidence_from_system_context(): void
    {
        config([
            'ai.openrouter.api_key' => 'test-key',
            'ai.openrouter.base_url' => 'https://openrouter.test/api/v1',
            'ai.openrouter.max_retries' => 0,
        ]);
        Http::fake(['*' => Http::response([
            'id' => 'farm-image-request',
            'data' => [[
                'b64_json' => base64_encode('jpeg'),
                'media_type' => 'image/jpeg',
            ]],
        ])]);

        (new OpenRouterGateway)->complete([
            [
                'role' => 'system',
                'content' => <<<'PROMPT'
<retrieved-evidence-json>
[{"citation":"[S1]","title":"Kebun Tani Lestari","source":"farmDemoData.json origin/dev","content":"{\"id\":\"kebun-002\",\"name\":\"Kebun Tani Lestari\",\"district\":\"Parakan\",\"city\":\"Temanggung\",\"usedHectare\":180,\"availableHectare\":750,\"seedType\":\"Jagung\",\"seedTypeDetail\":\"Hibrida BISI-18\",\"healthStatus\":\"Sehat\"}"}]
</retrieved-evidence-json>
PROMPT,
            ],
            [
                'role' => 'user',
                'content' => 'Buatkan gambar detail Kebun Tani Lestari.',
            ],
        ], 'image-model', ['image_generation' => true]);

        Http::assertSent(function (Request $request): bool {
            $prompt = (string) $request['prompt'];

            return str_contains($prompt, 'Kebun Tani Lestari')
                && str_contains($prompt, 'Parakan')
                && str_contains($prompt, 'Temanggung')
                && str_contains($prompt, '180')
                && str_contains($prompt, '750')
                && str_contains($prompt, 'Hibrida BISI-18')
                && str_contains($prompt, 'never invent or alter')
                && str_contains($prompt, 'If a field is absent, omit it.')
                && ! str_contains($prompt, 'Sukamaju')
                && ! str_contains($prompt, '2 hektar');
        });
    }

    public function test_image_editing_sends_only_the_latest_user_references_separately_from_the_prompt(): void
    {
        config([
            'ai.openrouter.api_key' => 'test-key',
            'ai.openrouter.base_url' => 'https://openrouter.test/api/v1',
            'ai.openrouter.max_retries' => 0,
            'ai.attachments.max_files' => 1,
        ]);
        Http::fake(['*' => Http::response(['data' => [['b64_json' => 'image-result']]])]);
        $latest = 'data:image/png;base64,latest-reference';
        $extra = 'data:image/png;base64,extra-reference';

        (new OpenRouterGateway)->complete([
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Foto lama'],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,private-history']],
            ]],
            ['role' => 'assistant', 'content' => [
                ['type' => 'text', 'text' => 'Ilustrasi sebelumnya'],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,assistant-history']],
            ]],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Ubah foto daun ini menjadi ilustrasi.'],
                ['type' => 'image_url', 'image_url' => ['url' => $latest]],
                ['type' => 'image_url', 'image_url' => ['url' => $extra]],
            ]],
        ], 'image-model', ['image_generation' => true]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.test/api/v1/images'
            && $request['input_references'] === [['type' => 'image_url', 'image_url' => ['url' => $latest]]]
            && str_contains((string) $request['prompt'], 'Ubah foto daun ini menjadi ilustrasi.')
            && ! str_contains((string) $request['prompt'], 'base64,')
            && ! str_contains(json_encode($request->data(), JSON_THROW_ON_ERROR), 'private-history')
            && ! str_contains(json_encode($request->data(), JSON_THROW_ON_ERROR), 'assistant-history'));
    }

    public function test_exact_educational_poster_request_preserves_illustration_single_subject_and_no_writing(): void
    {
        $request = 'Buat gambar ilustrasi satu tanaman jagung sehat di lahan pertanian, gaya poster edukasi sederhana, tanpa teks dan tanpa angka.';
        $prompt = $this->captureImagePrompt([
            ['role' => 'system', 'content' => '<retrieved-evidence-json>[{"title":"Catatan kebun","content":"180 ha, 750 ha, Jagung"}]</retrieved-evidence-json>'],
            ['role' => 'assistant', 'content' => 'Previous image: photorealistic scenery with crop rows and numbered labels.'],
            ['role' => 'user', 'content' => $request],
        ]);

        $this->assertStringContainsString("USER VISUAL REQUEST (verbatim):\n".$request, $prompt);
        $this->assertStringContainsString('Use a flat two-dimensional, vector-like educational illustration', $prompt);
        $this->assertStringContainsString('Do not use photography, photorealistic textures, cinematic lighting', $prompt);
        $this->assertStringContainsString('Show exactly ONE plant as the only plant subject in the whole image', $prompt);
        $this->assertStringContainsString('Do not add other plants, duplicated crops, crop rows', $prompt);
        $this->assertStringContainsString('simple soil patch or ground plane', $prompt);
        $this->assertStringContainsString('NO VISIBLE WRITING: no text, letters, words, labels, captions, titles, legends, digits, numbers, logos, signatures, or watermarks', $prompt);
        $this->assertStringContainsString('Reference facts must not override its style, subject count, or composition.', $prompt);
        $this->assertStringNotContainsString('A professional agricultural image or illustration for the Farmer IC', $prompt);
        $this->assertStringNotContainsString('Keep labels short and legible.', $prompt);
        $this->assertGreaterThan(strpos($prompt, 'Optional conversation context'), strpos($prompt, 'VISUAL REQUIREMENTS:'));
    }

    public function test_explicit_photographic_request_is_not_forced_into_a_flat_illustration(): void
    {
        $request = 'Buat foto realistis satu tanaman cabai sehat di lahan pertanian untuk panduan pemeriksaan, tanpa teks.';
        $prompt = $this->captureImagePrompt([['role' => 'user', 'content' => $request]]);

        $this->assertStringContainsString($request, $prompt);
        $this->assertStringContainsString('Preserve the explicitly requested photographic or realistic visual style.', $prompt);
        $this->assertStringNotContainsString('Use a flat two-dimensional', $prompt);
        $this->assertStringContainsString('Show exactly ONE plant', $prompt);
        $this->assertStringContainsString('NO VISIBLE WRITING:', $prompt);
        $this->assertStringNotContainsString('Use an informative infographic layout', $prompt);
    }

    /** @return array<string, array{string}> */
    public static function informationalQuickPrompts(): array
    {
        return [
            'fertilization by phase' => ['📊 Gambar: Panduan Pemupukan Jagung sesuai Fase'],
            'water schedule and needs' => ['💧 Gambar: Jadwal & Kebutuhan Air Kebun'],
            'pest detection and control' => ['🐛 Gambar: Deteksi & Pengendalian Hama Daun'],
        ];
    }

    #[DataProvider('informationalQuickPrompts')]
    public function test_existing_image_quick_prompts_request_informative_infographics(string $request): void
    {
        $prompt = $this->captureImagePrompt([['role' => 'user', 'content' => $request]]);

        $this->assertStringContainsString("USER VISUAL REQUEST (verbatim):\n".$request, $prompt);
        $this->assertStringContainsString('Use an informative infographic layout, not a decorative plant picture.', $prompt);
        $this->assertStringContainsString('ordered panels, a process flow, or a timeline suited to the topic', $prompt);
        $this->assertStringContainsString('short labels in the same language as the user request', $prompt);
        $this->assertStringContainsString('three to five short sections at most', $prompt);
        $this->assertStringContainsString('large mobile-legible headings', $prompt);
        $this->assertStringContainsString('Never fill the image with dense paragraphs.', $prompt);
        $this->assertStringContainsString('Each panel must communicate a useful step or fact from the supplied evidence.', $prompt);
        $this->assertStringContainsString('never invent or alter', $prompt);
        $this->assertStringNotContainsString('NO VISIBLE WRITING:', $prompt);
        $this->assertStringNotContainsString('Preserve the explicitly requested photographic', $prompt);
    }

    public function test_explicit_no_text_guide_does_not_gain_infographic_titles_or_panels(): void
    {
        $request = 'Buat ilustrasi panduan penyiraman satu tanaman tomat, tanpa teks dan tanpa angka.';
        $prompt = $this->captureImagePrompt([['role' => 'user', 'content' => $request]]);

        $this->assertStringContainsString($request, $prompt);
        $this->assertStringContainsString('Use a flat two-dimensional', $prompt);
        $this->assertStringContainsString('NO VISIBLE WRITING:', $prompt);
        $this->assertStringNotContainsString('Use an informative infographic layout', $prompt);
        $this->assertStringNotContainsString('Add labels only', $prompt);
    }

    public function test_structured_indonesian_infographic_keeps_requested_title_panels_numbers_and_arrows(): void
    {
        $request = 'Buat infografis edukasi berjudul Panduan Penyiraman Bibit Jagung dengan tiga panel bernomor: 1. Cek media tanam; 2. Siram perlahan; 3. Periksa drainase. Gunakan Bahasa Indonesia, ikon sederhana dan panah penghubung. Jangan berupa foto.';
        $prompt = $this->captureImagePrompt([['role' => 'user', 'content' => $request]]);

        $this->assertStringContainsString("USER VISUAL REQUEST (verbatim):\n".$request, $prompt);
        $this->assertStringContainsString('Use a flat two-dimensional, vector-like educational illustration', $prompt);
        $this->assertStringContainsString('Render explicitly requested titles, panel labels, step numbers, arrows, and text in the requested language and order', $prompt);
        $this->assertStringContainsString('these structural labels are not missing agronomic measurements', $prompt);
        $this->assertStringNotContainsString('NO VISIBLE WRITING:', $prompt);
        $this->assertStringNotContainsString('Do not render any digits', $prompt);
        $this->assertStringNotContainsString('Preserve the explicitly requested photographic', $prompt);
        $this->assertStringNotContainsString('Show exactly ONE plant', $prompt);
    }

    public function test_labelled_diagram_keeps_requested_labels_and_verified_values(): void
    {
        $prompt = $this->captureImagePrompt([[
            'role' => 'user', 'content' => 'Buat diagram alur pemupukan padi dengan label tahap dan angka penjelas.',
        ]]);

        $this->assertStringContainsString('Add labels only when the requested diagram', $prompt);
        $this->assertStringContainsString('Keep requested labels short and legible', $prompt);
        $this->assertStringContainsString('Use only verified values when factual labels are requested', $prompt);
        $this->assertStringContainsString('Use process nodes only for a requested diagram or flowchart', $prompt);
        $this->assertStringNotContainsString('NO VISIBLE WRITING:', $prompt);
        $this->assertStringNotContainsString('Do not render any digits', $prompt);
    }

    public function test_three_dimensional_illustration_keeps_the_requested_rendering_style(): void
    {
        $prompt = $this->captureImagePrompt([[
            'role' => 'user', 'content' => 'Create a 3D illustration of one healthy tomato plant, without text or numbers.',
        ]]);
        $this->assertStringContainsString('explicitly requested three-dimensional illustration style', $prompt);
        $this->assertStringNotContainsString('Use a flat two-dimensional', $prompt);
        $this->assertStringContainsString('Show exactly ONE plant', $prompt);
        $this->assertStringContainsString('NO VISIBLE WRITING:', $prompt);
    }

    public function test_non_numerical_text_labels_are_preserved_when_only_numbers_are_excluded(): void
    {
        $prompt = $this->captureImagePrompt([[
            'role' => 'user', 'content' => 'Buat diagram penanaman dengan label teks, tanpa angka.',
        ]]);
        $this->assertStringContainsString('Do not render any digits, numerical values, or numbered labels', $prompt);
        $this->assertStringContainsString('Requested non-numerical text labels may remain.', $prompt);
        $this->assertStringNotContainsString('NO VISIBLE WRITING:', $prompt);
    }

    /** @param list<array<string, mixed>> $messages */
    private function captureImagePrompt(array $messages): string
    {
        config([
            'ai.openrouter.api_key' => 'test-key',
            'ai.openrouter.base_url' => 'https://openrouter.test/api/v1',
            'ai.openrouter.max_retries' => 0,
        ]);
        $prompt = null;
        Http::fake(function (Request $request) use (&$prompt) {
            $this->assertSame('https://openrouter.test/api/v1/images', $request->url());
            $this->assertSame('image-model', $request['model']);
            $prompt = (string) $request['prompt'];

            return Http::response(['data' => [['b64_json' => 'image-result']]]);
        });
        (new OpenRouterGateway)->complete($messages, 'image-model', ['image_generation' => true]);
        $this->assertIsString($prompt);

        return $prompt;
    }
}

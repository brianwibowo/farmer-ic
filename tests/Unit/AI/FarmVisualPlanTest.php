<?php

namespace Tests\Unit\AI;

use App\AI\Services\FarmSnapshotService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Canonical JSON fixture planning only; bitmap visual review is a separate QA step. */
class FarmVisualPlanTest extends TestCase
{
    private Application $app;

    private Container $previousContainer;

    private mixed $previousFacadeApplication;

    /** @var array<string, mixed> */
    private array $corpus;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->app = new Application(dirname(__DIR__, 3));
        $this->app->instance('config', new Repository);
        $this->app->instance('files', new Filesystem);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $path = resource_path('js/data/farmDemoData.json');
        config(['ai.knowledge.demo_data_path' => $path]);
        $this->corpus = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        $this->app->flush();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_named_summary_has_auditable_source_and_exact_canonical_facts(): void
    {
        $garden = $this->garden('kebun-002');
        $plan = $this->ready('Buat infografis ringkasan Kebun Tani Lestari dari farmDemoData.json.');

        $this->assertSame(['kebun-002'], array_column($plan['farms'], 'id'));
        $this->assertSame('farmDemoData.json', $plan['source']['name']);
        $this->assertSame($this->corpus['version'], $plan['source']['version']);
        $this->assertSame($this->corpus['generatedAt'], $plan['source']['generated_at']);
        $this->assertSame(hash_file('sha256', resource_path('js/data/farmDemoData.json')), $plan['source']['sha256']);
        $this->assertSame($garden['usedHectare'], $this->fact($plan, 'kebun-002', 'used_hectare')['value']);
        $this->assertSame($garden['availableHectare'], $this->fact($plan, 'kebun-002', 'available_hectare')['value']);
        $this->assertSame($garden['healthStatus'], $this->fact($plan, 'kebun-002', 'status')['value']);
        $crop = (string) $this->fact($plan, 'kebun-002', 'crop')['value'];
        $this->assertStringContainsString($garden['seedType'], $crop);
        $this->assertStringContainsString($garden['seedTypeDetail'], $crop);
        $this->assertSame('ha', $this->fact($plan, 'kebun-002', 'used_hectare')['unit']);
    }

    public function test_switching_garden_changes_identity_values_and_condition(): void
    {
        $first = $this->ready('Infografis ringkasan Kebun Tani Lestari.');
        $second = $this->ready('Infografis ringkasan Kebun Harapan Jaya.');
        $garden = $this->garden('kebun-005');

        $this->assertSame(['kebun-005'], array_column($second['farms'], 'id'));
        $this->assertSame($garden['usedHectare'], $this->fact($second, 'kebun-005', 'used_hectare')['value']);
        $this->assertSame($garden['availableHectare'], $this->fact($second, 'kebun-005', 'available_hectare')['value']);
        $this->assertSame($garden['healthStatus'], $this->fact($second, 'kebun-005', 'status')['value']);
        $this->assertNotSame($first['farms'], $second['farms']);
        $this->assertNotSame($first['facts'], $second['facts']);
        $this->assertNotContains('kebun-002', array_column($second['facts'], 'farm_id'));
    }

    public function test_two_garden_comparison_preserves_requested_order_and_visual_options(): void
    {
        $plan = $this->ready('Buat infografis perbandingan Kebun Tani Lestari di kiri dan Kebun Sumber Makmur di kanan. Hanya luas terpakai dan lahan tersedia. Landscape, tema biru.');

        $this->assertSame(['kebun-002', 'kebun-001'], array_column($plan['farms'], 'id'));
        $this->assertSame('comparison', $plan['layout']);
        $this->assertSame('landscape', $plan['orientation']);
        $this->assertSame('blue', $plan['theme']);
        $this->assertEqualsCanonicalizing(['used_hectare', 'available_hectare'], $plan['fields']);
        foreach (['kebun-002', 'kebun-001'] as $id) {
            $garden = $this->garden($id);
            $this->assertSame($garden['usedHectare'], $this->fact($plan, $id, 'used_hectare')['value']);
            $this->assertSame($garden['availableHectare'], $this->fact($plan, $id, 'available_hectare')['value']);
        }
        $this->assertEqualsCanonicalizing(['kebun-002', 'kebun-001'], array_values(array_unique(array_column($plan['facts'], 'farm_id'))));
    }

    /** @return array<string, array{string}> */
    public static function allGardenPrompts(): array
    {
        return [
            'explicit all' => ['Buat infografis ringkasan semua kebun dari farmDemoData.json.'],
            'corpus summary' => ['Buat infografis ringkasan kebun dari farmDemoData.json.'],
        ];
    }

    #[DataProvider('allGardenPrompts')]
    public function test_corpus_summary_contains_every_canonical_garden_without_changing_values(string $prompt): void
    {
        $plan = $this->ready($prompt);

        $this->assertCount(count($this->corpus['kebun']), $plan['farms']);
        $this->assertEqualsCanonicalizing(array_column($this->corpus['kebun'], 'id'), array_column($plan['farms'], 'id'));
        foreach ($this->corpus['kebun'] as $garden) {
            $this->assertSame($garden['usedHectare'], $this->fact($plan, $garden['id'], 'used_hectare')['value']);
            $this->assertSame($garden['availableHectare'], $this->fact($plan, $garden['id'], 'available_hectare')['value']);
            $this->assertSame($garden['healthStatus'], $this->fact($plan, $garden['id'], 'status')['value']);
        }
    }

    public function test_area_only_bar_does_not_silently_include_growth_risk_weather_or_finance(): void
    {
        $plan = $this->ready('Buat grafik batang Kebun Tani Lestari. Tampilkan hanya luas terpakai dan lahan tersedia saja, tanpa informasi lainnya.');

        $this->assertSame('bar', $plan['layout']);
        $this->assertEqualsCanonicalizing(['used_hectare', 'available_hectare'], $plan['fields']);
        $this->assertEqualsCanonicalizing(['used_hectare', 'available_hectare'], array_unique(array_column($plan['facts'], 'key')));
        foreach ($plan['facts'] as $fact) {
            $this->assertSame('ha', $fact['unit']);
        }
    }

    public function test_risk_and_growth_request_uses_selected_insight_fields_only(): void
    {
        $plan = $this->ready('Buat infografis Kebun Harapan Jaya, hanya risiko dan pertumbuhan saja.');
        $insight = $this->corpus['kebunInsights']['kebun-005'];

        $this->assertEqualsCanonicalizing(['risk', 'growth'], $plan['fields']);
        $this->assertEqualsCanonicalizing(['risk', 'growth'], array_unique(array_column($plan['facts'], 'key')));
        $this->assertSame($insight['risk'], $this->fact($plan, 'kebun-005', 'risk')['value']);
        $this->assertSame($insight['growth'], $this->fact($plan, 'kebun-005', 'growth')['value']);
        $this->assertSame('%', $this->fact($plan, 'kebun-005', 'growth')['unit']);
    }

    public function test_explicit_metric_exclusions_are_not_reintroduced_as_requested_fields(): void
    {
        $plan = $this->ready('Buat infografis Kebun Tani Lestari, hanya luas terpakai dan lahan tersedia, tanpa pertumbuhan dan risiko.');

        $this->assertEqualsCanonicalizing(['used_hectare', 'available_hectare'], $plan['fields']);
        $this->assertEqualsCanonicalizing(['used_hectare', 'available_hectare'], array_unique(array_column($plan['facts'], 'key')));
    }

    public function test_an_exclusion_alone_removes_only_that_metric_from_the_summary(): void
    {
        $default = $this->ready('Buat infografis ringkasan Kebun Tani Lestari.');
        $plan = $this->ready('Buat infografis ringkasan Kebun Tani Lestari tanpa pertumbuhan.');

        $this->assertContains('growth', $default['fields']);
        $this->assertEqualsCanonicalizing(array_values(array_diff($default['fields'], ['growth'])), $plan['fields']);
        $this->assertNotContains('growth', array_column($plan['facts'], 'key'));
    }

    public function test_requested_pie_orientation_theme_and_title_are_retained_with_verified_values(): void
    {
        $plan = $this->ready('Buat diagram lingkaran semua kebun, hanya luas terpakai. Portrait, monokrom, judul "Perbandingan Lahan".');

        $this->assertSame('pie', $plan['layout']);
        $this->assertSame('portrait', $plan['orientation']);
        $this->assertSame('monochrome', $plan['theme']);
        $this->assertSame('Perbandingan Lahan', $plan['title']);
        $this->assertSame(['used_hectare'], $plan['fields']);
        foreach ($this->corpus['kebun'] as $garden) {
            $this->assertSame($garden['usedHectare'], $this->fact($plan, $garden['id'], 'used_hectare')['value']);
        }
    }

    public function test_crop_filtered_summary_uses_only_the_matching_canonical_gardens(): void
    {
        $plan = $this->ready('Buat infografis ringkasan semua kebun jagung dari farmDemoData.json, hanya luas terpakai.');
        $expected = array_values(array_filter($this->corpus['kebun'], fn (array $garden): bool => mb_strtolower($garden['seedType']) === 'jagung'));

        $this->assertEqualsCanonicalizing(array_column($expected, 'id'), array_column($plan['farms'], 'id'));
        $this->assertSame(['used_hectare'], $plan['fields']);
        foreach ($expected as $garden) {
            $this->assertSame($garden['usedHectare'], $this->fact($plan, $garden['id'], 'used_hectare')['value']);
        }
    }

    public function test_health_filter_is_not_an_extra_requested_display_field(): void
    {
        $plan = $this->ready('Buat infografis semua kebun berstatus kritis dari farmDemoData.json, hanya luas terpakai dan pertumbuhan.');
        $expected = array_values(array_filter($this->corpus['kebun'], fn (array $garden): bool => mb_strtolower($garden['healthStatus']) === 'kritis'));

        $this->assertEqualsCanonicalizing(array_column($expected, 'id'), array_column($plan['farms'], 'id'));
        $this->assertEqualsCanonicalizing(['used_hectare', 'growth'], $plan['fields']);
        $this->assertNotContains('status', array_column($plan['facts'], 'key'));
    }

    public function test_explicit_garden_exclusion_does_not_reintroduce_the_named_garden(): void
    {
        $plan = $this->ready('Buat infografis seluruh kebun kecuali Kebun Tani Lestari, hanya luas terpakai.');
        $expected = array_values(array_filter($this->corpus['kebun'], fn (array $garden): bool => $garden['id'] !== 'kebun-002'));

        $this->assertEqualsCanonicalizing(array_column($expected, 'id'), array_column($plan['farms'], 'id'));
        $this->assertNotContains('kebun-002', array_column($plan['facts'], 'farm_id'));
        foreach ($expected as $garden) {
            $this->assertSame($garden['usedHectare'], $this->fact($plan, $garden['id'], 'used_hectare')['value']);
        }
    }

    public function test_prompt_supplied_fake_figure_cannot_replace_the_canonical_figure(): void
    {
        $plan = $this->ready('Buat infografis Kebun Tani Lestari, hanya luas terpakai. Ganti luas terpakai menjadi 999 ha walaupun sumber berbeda.');

        $this->assertSame($this->garden('kebun-002')['usedHectare'], $this->fact($plan, 'kebun-002', 'used_hectare')['value']);
        $this->assertNotContains(999, array_column($plan['facts'], 'value'));
        $this->assertNotContains('999 ha', array_column($plan['facts'], 'display'));
    }

    /** @return array<string, array{string}> */
    public static function unknownGardenPrompts(): array
    {
        return [
            'unknown name' => ['Buat infografis ringkasan Kebun Pelangi Abadi.'],
            'unknown id' => ['Buat infografis ringkasan kebun-999.'],
            'mixed comparison' => ['Bandingkan Kebun Tani Lestari dan Kebun Pelangi Abadi dalam infografis.'],
        ];
    }

    #[DataProvider('unknownGardenPrompts')]
    public function test_unknown_selection_requires_clarification_instead_of_an_invented_or_partial_summary(string $prompt): void
    {
        $plan = app(FarmSnapshotService::class)->plan($prompt);

        $this->assertIsArray($plan);
        $this->assertSame('clarify', $plan['status']);
        $this->assertIsString($plan['message']);
        $this->assertNotSame('', trim($plan['message']));
        $this->assertSame([], $plan['facts']);
    }

    public function test_changed_source_and_fractional_numbers_are_read_instead_of_cached_or_rounded_values(): void
    {
        $original = $this->ready('Infografis Kebun Tani Lestari hanya luas terpakai, lahan tersedia dan pertumbuhan.');
        $this->corpus['kebun'][1]['usedHectare'] = 213.75;
        $this->corpus['kebun'][1]['availableHectare'] = 745.25;
        $this->corpus['kebunInsights']['kebun-002']['growth'] = 66.5;
        $this->useCorpus($this->corpus);

        $plan = $this->ready('Infografis Kebun Tani Lestari hanya luas terpakai, lahan tersedia dan pertumbuhan.');

        $this->assertSame(213.75, $this->fact($plan, 'kebun-002', 'used_hectare')['value']);
        $this->assertSame(745.25, $this->fact($plan, 'kebun-002', 'available_hectare')['value']);
        $this->assertSame(66.5, $this->fact($plan, 'kebun-002', 'growth')['value']);
        $this->assertStringContainsString('213,75', $this->fact($plan, 'kebun-002', 'used_hectare')['display']);
        $this->assertStringContainsString('745,25', $this->fact($plan, 'kebun-002', 'available_hectare')['display']);
        $this->assertNotSame($original['source']['sha256'], $plan['source']['sha256']);
    }

    public function test_missing_numeric_data_is_not_fabricated_as_zero(): void
    {
        unset($this->corpus['kebun'][1]['availableHectare'], $this->corpus['kebunInsights']['kebun-002']['growth']);
        $this->useCorpus($this->corpus);
        $plan = app(FarmSnapshotService::class)->plan('Infografis Kebun Tani Lestari hanya lahan tersedia dan pertumbuhan.');

        $this->assertSame('clarify', $plan['status']);
        $this->assertSame([], $plan['facts']);
        $this->assertNotEmpty($plan['warnings']);
        $partial = $this->ready('Infografis Kebun Tani Lestari hanya luas terpakai, lahan tersedia dan pertumbuhan.');
        $this->assertSame(180, $this->fact($partial, 'kebun-002', 'used_hectare')['value']);
        $this->assertNull($partial['farms'][0]['available_hectare']);
        foreach (['available_hectare', 'growth'] as $key) {
            $facts = array_values(array_filter($partial['facts'], fn (array $fact): bool => $fact['key'] === $key));
            foreach ($facts as $fact) {
                $this->assertNull($fact['value']);
                $this->assertDoesNotMatchRegularExpression('/^0(?:[,.]0+)?(?:\s*(?:ha|%))?$/', trim($fact['display']));
            }
        }
    }

    public function test_follow_up_changes_fields_and_layout_without_losing_the_previous_selection(): void
    {
        $previous = $this->ready('Buat infografis ringkasan Kebun Tani Lestari.');
        $plan = app(FarmSnapshotService::class)->plan('Ubah menjadi grafik batang, hanya luas terpakai dan lahan tersedia, landscape.', $previous);

        $this->assertIsArray($plan);
        $this->assertSame('ready', $plan['status']);
        $this->assertSame(['kebun-002'], array_column($plan['farms'], 'id'));
        $this->assertSame('bar', $plan['layout']);
        $this->assertSame('landscape', $plan['orientation']);
        $this->assertEqualsCanonicalizing(['used_hectare', 'available_hectare'], $plan['fields']);
    }

    public function test_a_generic_agriculture_illustration_is_not_replaced_with_a_farm_data_report(): void
    {
        $this->assertNull(app(FarmSnapshotService::class)->plan('Buat ilustrasi satu tanaman jagung sehat tanpa teks dan angka.'));
    }

    /** @return array<string, array{string}> */
    public static function newEducationalPrompts(): array
    {
        return [
            'bare ini is not a revision' => ['Buat infografis jagung ini.'],
            'bare itu is not a revision' => ['Buat ilustrasi jagung itu.'],
            'fertilizer starts a new topic' => ['Buat infografis pemupukan jagung ini.'],
            'revision verb with new educational subject' => ['Ubah jadi poster panduan cara menanam jagung.'],
            'continued fertilizer request is not previous farm selection' => ['Lanjut buat infografis pemupukan jagung.'],
            'corn illustration without text is not a data report' => ['Ubah ilustrasi tanaman jagung menjadi poster tanpa teks dan angka.'],
        ];
    }

    #[DataProvider('newEducationalPrompts')]
    public function test_a_new_generic_visual_does_not_reuse_previous_farm_data(string $prompt): void
    {
        $previous = $this->ready('Buat infografis ringkasan Kebun Tani Lestari.');

        $this->assertNull(app(FarmSnapshotService::class)->plan($prompt, $previous));
    }

    public function test_explicit_finance_request_keeps_the_existing_finance_workflow(): void
    {
        $previous = $this->ready('Buat infografis ringkasan Kebun Tani Lestari.');

        $this->assertNull(app(FarmSnapshotService::class)->plan('Buat infografis keuangan Kebun Tani Lestari.', $previous));
        $this->assertNull(app(FarmSnapshotService::class)->plan('Ubah yang tadi jadi infografis pemasukan dan pengeluaran.', $previous));
    }

    public function test_negated_finance_does_not_block_a_canonical_farm_summary(): void
    {
        $plan = $this->ready('Buat infografis ringkasan Kebun Tani Lestari, tanpa data keuangan, biaya dan pendapatan.');

        $this->assertSame(['kebun-002'], array_column($plan['farms'], 'id'));
        $this->assertSame(180, $this->fact($plan, 'kebun-002', 'used_hectare')['value']);
    }

    public function test_explicit_revision_understands_next_steps_synonym(): void
    {
        $previous = $this->ready('Buat infografis ringkasan Kebun Tani Lestari.');
        $plan = app(FarmSnapshotService::class)->plan('Ubah yang tadi jadi portrait monokrom, hanya risiko dan langkah berikutnya.', $previous);

        $this->assertSame('ready', $plan['status']);
        $this->assertSame(['risk', 'next_step'], $plan['fields']);
        $this->assertSame('risk', $plan['layout']);
        $this->assertSame(['kebun-002'], array_column($plan['farms'], 'id'));
        $this->assertSame($this->corpus['kebunInsights']['kebun-002']['nextStep'], $this->fact($plan, 'kebun-002', 'next_step')['value']);
    }

    public function test_excluding_every_requested_field_requires_clarification(): void
    {
        $plan = app(FarmSnapshotService::class)->plan('Buat infografis Kebun Tani Lestari, hanya luas terpakai, tanpa luas terpakai.');

        $this->assertSame('clarify', $plan['status']);
        $this->assertSame([], $plan['facts']);
        $this->assertNotEmpty($plan['message']);
    }

    public function test_an_unsupported_only_metric_clarifies_instead_of_replacing_the_request(): void
    {
        foreach (['hanya jumlah petani', 'jumlah petani saja', 'hanya kadar nitrogen'] as $selection) {
            $plan = app(FarmSnapshotService::class)->plan('Infografis Kebun Tani Lestari, '.$selection.'.');

            $this->assertSame('clarify', $plan['status']);
            $this->assertSame([], $plan['facts']);
            $this->assertStringContainsString('belum tersedia', $plan['message']);
        }
    }

    public function test_only_farm_and_status_filters_still_allow_a_default_summary(): void
    {
        $healthy = $this->ready('Buat infografis ringkasan hanya kebun sehat.');
        $named = $this->ready('Buat infografis ringkasan hanya Kebun Tani Lestari.');

        $this->assertSame(['kebun-001', 'kebun-002', 'kebun-004'], array_column($healthy['farms'], 'id'));
        $this->assertSame(['kebun-002'], array_column($named['farms'], 'id'));
        $this->assertNotEmpty($healthy['facts']);
        $this->assertNotEmpty($named['facts']);
    }

    public function test_a_named_farm_educational_guide_is_not_replaced_with_a_data_summary(): void
    {
        $service = app(FarmSnapshotService::class);

        $this->assertNull($service->plan('Buat infografis panduan pemupukan Kebun Tani Lestari.'));
        $data = $this->ready('Buat infografis panduan pemupukan Kebun Tani Lestari dengan data luas terpakai.');
        $this->assertSame(['used_hectare'], $data['fields']);
        $risk = $this->ready('Buat infografis Kebun Tani Lestari hanya risiko dan tindak lanjut.');
        $this->assertSame(['risk', 'next_step'], $risk['fields']);
    }

    public function test_indonesian_donut_composition_respects_a_comma_separated_exclusion_list(): void
    {
        $plan = $this->ready('Buat infografis diagram donat komposisi luas terpakai semua kebun dari farmDemoData.json, landscape biru. Tanpa total lahan, pertumbuhan dan risiko.');

        $this->assertSame('pie', $plan['layout']);
        $this->assertSame('landscape', $plan['orientation']);
        $this->assertSame('blue', $plan['theme']);
        $this->assertSame(['used_hectare'], $plan['fields']);
        $this->assertSame(array_column($this->corpus['kebun'], 'id'), array_column($plan['farms'], 'id'));
        $this->assertSame(['used_hectare'], array_values(array_unique(array_column($plan['facts'], 'key'))));
        $this->assertSame(array_column($this->corpus['kebun'], 'usedHectare'), array_column($plan['facts'], 'value'));
    }

    public function test_a_positive_instruction_after_an_exclusion_list_is_preserved(): void
    {
        $plan = $this->ready('Buat infografis Kebun Tani Lestari, hanya luas terpakai. Tanpa total lahan, pertumbuhan dan risiko, tampilkan lokasi.');

        $this->assertSame(['used_hectare', 'location'], $plan['fields']);
        $this->assertSame(180, $this->fact($plan, 'kebun-002', 'used_hectare')['value']);
        $this->assertSame('Parakan, Temanggung', $this->fact($plan, 'kebun-002', 'location')['value']);
        $this->assertSame(['used_hectare', 'location'], array_values(array_unique(array_column($plan['facts'], 'key'))));
    }

    public function test_unreadable_corpus_clarifies_without_inventing_any_facts(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'farm-visual-plan-');
        $this->assertIsString($path);
        $this->temporaryFiles[] = $path;
        file_put_contents($path, '{invalid json');
        config(['ai.knowledge.demo_data_path' => $path]);

        $plan = app(FarmSnapshotService::class)->plan('Buat infografis ringkasan semua kebun dari farmDemoData.json.');

        $this->assertIsArray($plan);
        $this->assertSame('clarify', $plan['status']);
        $this->assertNotEmpty($plan['message']);
        $this->assertSame([], $plan['farms']);
        $this->assertSame([], $plan['facts']);
    }

    /** @return array<string, mixed> */
    private function ready(string $prompt): array
    {
        $plan = app(FarmSnapshotService::class)->plan($prompt);
        $this->assertIsArray($plan);
        $this->assertSame('ready', $plan['status'], (string) ($plan['message'] ?? ''));

        return $plan;
    }

    /** @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function fact(array $plan, string $farmId, string $key): array
    {
        $facts = array_values(array_filter($plan['facts'], fn (array $fact): bool => $fact['farm_id'] === $farmId && $fact['key'] === $key));
        $this->assertCount(1, $facts, "Expected one {$key} fact for {$farmId}");

        return $facts[0];
    }

    /** @return array<string, mixed> */
    private function garden(string $id): array
    {
        foreach ($this->corpus['kebun'] as $garden) {
            if ($garden['id'] === $id) {
                return $garden;
            }
        }
        $this->fail('Missing garden fixture '.$id);
    }

    /** @param array<string, mixed> $corpus */
    private function useCorpus(array $corpus): void
    {
        $path = tempnam(sys_get_temp_dir(), 'farm-visual-plan-');
        $this->assertIsString($path);
        $this->temporaryFiles[] = $path;
        file_put_contents($path, json_encode($corpus, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        config(['ai.knowledge.demo_data_path' => $path]);
    }
}

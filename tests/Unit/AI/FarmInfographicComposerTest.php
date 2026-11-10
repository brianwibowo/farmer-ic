<?php

namespace Tests\Unit\AI;

use App\AI\Services\FarmInfographicComposer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Pure raster tests: no Laravel application, database, or image-provider calls. */
class FarmInfographicComposerTest extends TestCase
{
    private function composer(): FarmInfographicComposer
    {
        $directory = dirname(__DIR__, 3).'/resources/fonts/';

        return new FarmInfographicComposer($directory.'NotoSans-Regular.ttf', $directory.'NotoSans-Bold.ttf');
    }

    private function art(int $red = 25): string
    {
        $image = imagecreatetruecolor(64, 48);
        imagefilledrectangle($image, 0, 0, 63, 47, imagecolorallocate($image, $red, 140, 75));
        imagefilledellipse($image, 25, 24, 24, 35, imagecolorallocate($image, 230, 225, 150));
        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        unset($image);

        return $binary;
    }

    private function fact(string $id, string $name, string $key, string $label, mixed $value, string $display, string $unit = ''): array
    {
        return ['farm_id' => $id, 'farm_name' => $name, 'key' => $key, 'label' => $label, 'value' => $value, 'unit' => $unit, 'display' => $display];
    }

    private function plan(string $layout = 'summary', string $orientation = 'portrait'): array
    {
        return [
            'status' => 'ready', 'title' => 'Ringkasan kebun pilihan', 'layout' => $layout, 'orientation' => $orientation, 'theme' => 'green',
            'source' => ['name' => 'farmDemoData.json', 'version' => 10, 'generated_at' => '2026-10-20', 'sha256' => str_repeat('b', 64)],
            'farms' => [['id' => 'kebun-001', 'name' => 'Kebun Sumber Makmur'], ['id' => 'kebun-002', 'name' => 'Kebun Tani Lestari']],
            'fields' => ['used_hectare', 'growth'],
            'facts' => [
                $this->fact('kebun-001', 'Kebun Sumber Makmur', 'used_hectare', 'Luas terpakai', 300, '300 ha', 'ha'),
                $this->fact('kebun-001', 'Kebun Sumber Makmur', 'growth', 'Pertumbuhan', 78, '78%', '%'),
                $this->fact('kebun-002', 'Kebun Tani Lestari', 'used_hectare', 'Luas terpakai', 180, '180 ha', 'ha'),
                $this->fact('kebun-002', 'Kebun Tani Lestari', 'growth', 'Pertumbuhan', 65, '65%', '%'),
            ], 'warnings' => [],
        ];
    }

    public function test_produces_real_png_with_exact_data_and_visible_source_provenance(): void
    {
        $art = $this->art();
        $result = $this->composer()->render($this->plan(), $art);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($result['binary'], 0, 8));
        $bitmap = imagecreatefromstring($result['binary']);
        $this->assertSame(1080, imagesx($bitmap));
        $this->assertGreaterThanOrEqual(1280, imagesy($bitmap));
        $manifest = $result['manifest'];
        $this->assertSame('farm-png-v2', $manifest['renderer']);
        $this->assertSame(hash('sha256', $art), $manifest['art']['sha256']);
        $this->assertSame(str_repeat('b', 64), $manifest['source']['sha256']);
        $this->assertSame([300, 78, 180, 65], array_column($manifest['facts'], 'value'));
        $text = implode(' ', array_column($manifest['text_runs'], 'text'));
        $this->assertStringContainsString('farmDemoData.json', $text);
        $this->assertStringContainsString('2026-10-20', $text);
        $this->assertStringContainsString('bbbbbbbbbbbb', $text);
        $this->assertStringContainsString('300 ha', $text);

        // The exact-data layer must exist in actual pixels, not just the manifest.
        $run = current(array_filter($manifest['text_runs'], fn ($run) => $run['text'] === '300 ha'));
        $bounds = $run['bounds'];
        $nonWhite = 0;
        for ($y = $bounds['y']; $y <= $bounds['y'] + $bounds['height']; $y++) {
            for ($x = $bounds['x']; $x <= $bounds['x'] + $bounds['width']; $x++) {
                $nonWhite += (imagecolorat($bitmap, $x, $y) & 0xFFFFFF) !== 0xFFFFFF ? 1 : 0;
            }
        }
        $this->assertGreaterThan(100, $nonWhite);
        unset($bitmap);
    }

    public function test_only_requested_facts_are_drawn_and_decimals_and_missing_data_are_preserved(): void
    {
        $plan = $this->plan();
        $plan['fields'] = ['used_hectare', 'weather'];
        $plan['facts'][0]['value'] = 213.75;
        $plan['facts'][0]['display'] = '213,75 ha';
        $plan['facts'][] = $this->fact('kebun-001', 'Kebun Sumber Makmur', 'weather', 'Cuaca', null, '0');
        $plan['facts'][] = $this->fact('outside', 'Tidak dipilih', 'used_hectare', 'Luas terpakai', 99999, '99999 ha', 'ha');
        $result = $this->composer()->render($plan, $this->art());
        $text = implode(' ', array_column($result['manifest']['text_runs'], 'text'));
        $this->assertStringContainsString('213,75 ha', $text);
        $this->assertStringContainsString('Belum tercatat', $text);
        $this->assertStringNotContainsString('78%', $text);
        $this->assertStringNotContainsString('99999', $text);
        $this->assertSame(['used_hectare', 'weather', 'used_hectare'], array_column($result['manifest']['facts'], 'key'));
    }

    public function test_source_data_and_model_art_each_change_the_output(): void
    {
        $plan = $this->plan();
        $first = $this->composer()->render($plan, $this->art());
        $plan['facts'][0]['value'] = 301;
        $plan['facts'][0]['display'] = '301 ha';
        $changedData = $this->composer()->render($plan, $this->art());
        $changedArt = $this->composer()->render($plan, $this->art(220));
        $this->assertNotSame(hash('sha256', $first['binary']), hash('sha256', $changedData['binary']));
        $this->assertNotSame(hash('sha256', $changedData['binary']), hash('sha256', $changedArt['binary']));
        $this->assertSame($changedData['manifest']['facts'], $changedArt['manifest']['facts']);
        $this->assertSame(301, $changedData['manifest']['facts'][0]['value']);
    }

    public function test_bar_compares_actual_values_from_zero_and_separates_incompatible_units(): void
    {
        $result = $this->composer()->render($this->plan('bar', 'landscape'), $this->art());
        $charts = $result['manifest']['charts'];
        $this->assertCount(2, $charts);
        $this->assertSame(['ha', '%'], array_column($charts, 'unit'));
        $this->assertSame([300, 180], array_column($charts[0]['values'], 'value'));
        $this->assertSame([78, 65], array_column($charts[1]['values'], 'value'));
        $this->assertTrue($charts[0]['zero_baseline']);
        $this->assertSame(0, $charts[0]['axis_min']);
        $this->assertEqualsWithDelta(0.6, $charts[0]['values'][1]['bar_width'] / $charts[0]['values'][0]['bar_width'], 0.002);
        $this->assertGreaterThan($result['manifest']['height'], $result['manifest']['width']);
    }

    public function test_pie_uses_one_field_across_farms_and_never_adds_capacity_to_used_area(): void
    {
        $plan = $this->plan('pie');
        $plan['fields'][] = 'available_hectare';
        $plan['facts'][] = $this->fact('kebun-001', 'Kebun Sumber Makmur', 'available_hectare', 'Total lahan', 1000, '1.000 ha', 'ha');
        $result = $this->composer()->render($plan, $this->art());
        $pie = $result['manifest']['charts'][0];
        $this->assertSame('used_hectare', $pie['key']);
        $this->assertSame('selected_farms', $pie['scope']);
        $this->assertSame([300, 180], array_column($pie['values'], 'value'));
        $this->assertSame(480, $pie['geometry_denominator']);
        $text = implode(' ', array_column($result['manifest']['text_runs'], 'text'));
        $this->assertStringNotContainsString('1.480', $text);
        $this->assertStringNotContainsString('480 ha', $text);
        $this->assertStringContainsString('1.000 ha', $text);
        $this->assertStringContainsString('Proporsi antar-kebun terpilih', $text);
    }

    public function test_requested_layouts_have_distinct_geometry_and_long_landscape_text_is_not_cropped(): void
    {
        $summary = $this->composer()->render($this->plan(), $this->art());
        $comparison = $this->composer()->render($this->plan('comparison'), $this->art());
        $this->assertNotSame($summary['manifest']['blocks'], $comparison['manifest']['blocks']);
        $plan = $this->plan('risk', 'landscape');
        $plan['fields'] = ['risk', 'next_step'];
        $plan['facts'] = [];
        $description = 'Risiko kekeringan meningkat pada lereng selatan. Periksa kelembapan dan jadwal irigasi bersama petugas sebelum melakukan tindakan.';
        for ($index = 1; $index <= 5; $index++) {
            $id = 'farm-'.$index;
            $name = 'Kebun wilayah nomor '.$index;
            $plan['farms'][] = ['id' => $id, 'name' => $name];
            foreach ($plan['fields'] as $key) {
                $plan['facts'][] = $this->fact($id, $name, $key, $key === 'risk' ? 'Risiko utama' : 'Langkah berikutnya', $description, $description);
            }
        }
        $result = $this->composer()->render($plan, $this->art());
        $manifest = $result['manifest'];
        $this->assertGreaterThan($manifest['height'], $manifest['width']);
        $this->assertCount(10, $manifest['facts']);
        $this->assertSame($description, $manifest['facts'][9]['display']);
        foreach ($manifest['text_runs'] as $run) {
            $this->assertGreaterThanOrEqual(0, $run['bounds']['x']);
            $this->assertGreaterThanOrEqual(0, $run['bounds']['y']);
            $this->assertLessThan($manifest['width'], $run['bounds']['x'] + $run['bounds']['width']);
            $this->assertLessThan($manifest['height'], $run['bounds']['y'] + $run['bounds']['height']);
        }
        foreach ($manifest['facts'] as $fact) {
            $this->assertGreaterThan($manifest['art']['bounds']['y'] + $manifest['art']['bounds']['height'], $fact['bounds']['y']);
        }
    }

    public function test_summary_uses_the_selected_capacity_as_a_denominator_and_growth_has_its_own_scale(): void
    {
        $plan = $this->plan();
        $plan['fields'][] = 'available_hectare';
        $plan['facts'][] = $this->fact('kebun-001', 'Kebun Sumber Makmur', 'available_hectare', 'Total lahan', 1000, '1.000 ha', 'ha');
        $result = $this->composer()->render($plan, $this->art());
        $gauges = $result['manifest']['charts'];
        $area = current(array_filter($gauges, fn ($chart) => $chart['key'] === 'used_hectare'));
        $this->assertSame(1000, $area['axis_max']);
        $this->assertSame(300, $area['value']);
        $growth = current(array_filter($gauges, fn ($chart) => $chart['key'] === 'growth'));
        $this->assertSame(100, $growth['axis_max']);
        $this->assertEqualsWithDelta(0.3, $area['bar_width'] / $area['track']['width'], 0.002);
        $text = implode(' ', array_column($result['manifest']['text_runs'], 'text'));
        $this->assertStringNotContainsString('1.300', $text);
    }

    public function test_growth_percentages_never_become_pie_slices_of_an_invented_whole(): void
    {
        $plan = $this->plan('pie');
        $plan['fields'] = ['growth'];
        $result = $this->composer()->render($plan, $this->art());
        $this->assertSame([], $result['manifest']['charts']);
        $this->assertSame([78, 65], array_column($result['manifest']['facts'], 'value'));
        $this->assertSame('notice', $result['manifest']['blocks'][0]['type']);
        $this->assertStringNotContainsString('143', implode(' ', array_column($result['manifest']['text_runs'], 'text')));
    }

    public function test_rejects_invalid_model_art_instead_of_using_a_placeholder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('gambar raster');
        $this->composer()->render($this->plan(), '<svg></svg>');
    }

    public function test_reports_missing_font_instead_of_rendering_tiny_bitmap_text(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Font infografis tidak tersedia');
        (new FarmInfographicComposer('/missing-font.ttf', '/missing-font.ttf'))->render($this->plan(), $this->art());
    }
}

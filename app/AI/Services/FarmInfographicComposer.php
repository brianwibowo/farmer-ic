<?php

namespace App\AI\Services;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Composes fresh model artwork with a separate, exact-data raster layer.
 * The model artwork never supplies factual labels, numbers, or chart geometry.
 *
 * @phpstan-type Fact array{farm_id:string, farm_name:string, key:string, label:string, value:int|float|string|null, unit:string, display:string}
 * @phpstan-type Bounds array{x:int, y:int, width:int, height:int}
 * @phpstan-type Plan array<string, mixed>
 * @phpstan-type Item array{fact:Fact, x:int, y:int, w:int, h:int, metric:bool}
 * @phpstan-type Block array<string, mixed>
 * @phpstan-type Palette array{background:int, white:int, text:int, muted:int, border:int, warning:int, dark:int, light:int, series:non-empty-list<int>}
 */
class FarmInfographicComposer
{
    private string $font;

    private string $boldFont;

    /** @var array<string, mixed> */
    private array $manifest = [];

    /** @var Palette */
    private array $colors;

    public function __construct(?string $fontPath = null, ?string $boldFontPath = null)
    {
        $this->font = $fontPath ?? resource_path('fonts/NotoSans-Regular.ttf');
        $this->boldFont = $boldFontPath ?? resource_path('fonts/NotoSans-Bold.ttf');
    }

    /**
     * @param  Plan  $plan
     * @return array{binary: string, manifest: array<string, mixed>}
     */
    public function render(array $plan, string $modelArtBinary): array
    {
        if (! extension_loaded('gd') || ! function_exists('imagettftext')) {
            throw new RuntimeException('Infografis memerlukan GD dan FreeType.');
        }
        if (! is_file($this->font) || ! is_readable($this->font)) {
            throw new RuntimeException('Font infografis tidak tersedia: '.$this->font);
        }
        if (! is_file($this->boldFont)) {
            $this->boldFont = $this->font;
        }
        if (($plan['status'] ?? null) !== 'ready') {
            throw new InvalidArgumentException('Hanya rencana infografis berstatus ready yang dapat digambar.');
        }
        $art = @imagecreatefromstring($modelArtBinary);
        if (! $art instanceof GdImage) {
            throw new InvalidArgumentException('Ilustrasi model harus berupa gambar raster yang valid.');
        }

        $orientation = ($plan['orientation'] ?? '') === 'landscape' ? 'landscape' : 'portrait';
        $width = $orientation === 'landscape' ? 1440 : 1080;
        $layout = in_array($plan['layout'] ?? '', ['summary', 'bar', 'pie', 'comparison', 'risk'], true)
            ? $plan['layout'] : 'summary';
        $theme = in_array($plan['theme'] ?? '', ['green', 'blue', 'monochrome'], true) ? $plan['theme'] : 'green';
        $facts = $this->selectedFacts($plan);
        if ($facts === []) {
            unset($art);
            throw new InvalidArgumentException('Rencana infografis tidak memiliki fakta yang dapat ditampilkan.');
        }
        $title = trim((string) ($plan['title'] ?? 'Ringkasan kebun'));
        $footer = $this->footerLines($plan);
        do {
            $titleWidth = $width - ($orientation === 'landscape' ? 530 : 430);
            $headerHeight = max(270, 110 + count($this->wrap($title, 29, $titleWidth, true)) * 46);
            $blocks = $this->blocks($layout, $facts, $plan, $width, $headerHeight + 64);
            $contentEnd = array_reduce($blocks, fn ($end, $block) => max($end, $block['y'] + $block['h']), $headerHeight + 64);
            $footerHeight = 44;
            foreach ($footer as $line) {
                $footerHeight += count($this->wrap($line, 13, $width - 96)) * 23 + 7;
            }
            $height = max($orientation === 'landscape' ? 960 : 1280, (int) ceil($contentEnd + $footerHeight + 40));
            $expand = $orientation === 'landscape' && $height >= $width;
            if ($expand) {
                // Add legible columns instead of clipping data or shrinking the type.
                $width = max($width + 240, (int) ceil(($height + 100) / 100) * 100);
            }
        } while ($expand);
        $canvas = imagecreatetruecolor($width, $height);
        if (! $canvas instanceof GdImage) {
            unset($art);
            throw new RuntimeException('Kanvas infografis tidak dapat dibuat.');
        }
        $this->colors = $this->palette($canvas, $theme);
        $this->manifest = [
            'renderer' => 'farm-png-v2', 'width' => $width, 'height' => $height,
            'layout' => $layout, 'orientation' => $orientation, 'theme' => $theme,
            'source' => $plan['source'] ?? [], 'fields' => array_values($plan['fields'] ?? []),
            'facts' => [], 'text_runs' => [], 'charts' => [], 'blocks' => [],
            'art' => ['sha256' => hash('sha256', $modelArtBinary)],
        ];
        imagefilledrectangle($canvas, 0, 0, $width - 1, $height - 1, $this->colors['background']);
        $this->rounded($canvas, 32, 32, $width - 64, $headerHeight, $this->colors['dark']);
        $this->paragraph($canvas, 'DATA DEMO · KEBUN', 60, 58, $titleWidth, 14, 'light', true);
        $this->paragraph($canvas, $title, 60, 103, $titleWidth, 29, 'white', true, 46);
        $artWidth = $orientation === 'landscape' ? 400 : 300;
        $artBounds = ['x' => $width - $artWidth - 54, 'y' => 54, 'width' => $artWidth, 'height' => $headerHeight - 44];
        $this->placeArt($canvas, $art, $artBounds);
        $this->manifest['art']['bounds'] = $artBounds;
        unset($art);

        foreach ($blocks as $block) {
            $this->drawBlock($canvas, $block);
        }
        $footerY = max($contentEnd + 30, $height - $footerHeight);
        imageline($canvas, 48, (int) $footerY, $width - 48, (int) $footerY, $this->colors['border']);
        $footerY += 18;
        foreach ($footer as $line) {
            $footerY += $this->paragraph($canvas, $line, 48, (int) $footerY, $width - 96, 13, 'muted', false, 23) + 7;
        }
        ob_start();
        $ok = imagepng($canvas, null, 6);
        $binary = ob_get_clean();
        unset($canvas);
        if (! $ok || ! is_string($binary)) {
            throw new RuntimeException('Gagal menyimpan infografis PNG.');
        }

        return ['binary' => $binary, 'manifest' => $this->manifest];
    }

    /** @param Plan $plan
     * @return list<Fact>
     */
    private function selectedFacts(array $plan): array
    {
        $ids = array_column($plan['farms'] ?? [], 'id');
        $fields = $plan['fields'] ?? [];
        $facts = [];
        foreach ($plan['facts'] ?? [] as $fact) {
            if (! in_array($fact['key'] ?? '', $fields, true) || ! in_array($fact['farm_id'] ?? '', $ids, true)) {
                continue;
            }
            $fact['display'] = $fact['value'] === null ? 'Belum tercatat' : (string) ($fact['display'] ?? $fact['value']);
            $facts[] = $fact;
        }

        return $facts;
    }

    /**
     * Layout is planned before drawing so long descriptions grow the canvas.
     *
     * @param  list<Fact>  $facts
     * @param  Plan  $plan
     * @return list<Block>
     */
    private function blocks(string $layout, array $facts, array $plan, int $width, int $start): array
    {
        $blocks = [];
        $y = $start;
        $gap = 24;
        if ($layout === 'bar') {
            $groups = [];
            foreach ($facts as $fact) {
                if (is_int($fact['value']) || is_float($fact['value'])) {
                    if ($fact['value'] < 0) {
                        throw new InvalidArgumentException('Diagram kebun tidak menerima nilai negatif.');
                    }
                    $groups[(string) $fact['unit']][] = $fact;
                }
            }
            foreach ($groups as $unit => $group) {
                $h = 100;
                foreach ($group as $fact) {
                    $h += 73 + count($this->wrap($fact['farm_name'].' · '.$fact['label'], 17, $width - 136)) * 29;
                }
                $blocks[] = ['type' => 'bar', 'x' => 40, 'y' => $y, 'w' => $width - 80, 'h' => $h, 'facts' => $group, 'unit' => $unit];
                $y += $h + $gap;
            }
            $facts = array_values(array_filter($facts, fn ($fact) => ! is_int($fact['value']) && ! is_float($fact['value'])));
        } elseif ($layout === 'pie') {
            $key = null;
            foreach ($plan['fields'] ?? [] as $field) {
                if (! in_array($field, ['used_hectare', 'available_hectare', 'weight_ton'], true)) {
                    continue;
                }
                $candidates = array_values(array_filter($facts, fn ($fact) => $fact['key'] === $field && is_numeric($fact['value']) && $fact['value'] >= 0));
                // One field across different farms is a meaningful part-to-whole comparison.
                if (count($candidates) >= 2 && count(array_unique(array_column($candidates, 'farm_id'))) === count($candidates)
                    && count(array_unique(array_column($candidates, 'unit'))) === 1 && array_sum(array_column($candidates, 'value')) > 0) {
                    $key = $field;
                    break;
                }
            }
            if ($key !== null) {
                $group = array_values(array_filter($facts, fn ($fact) => $fact['key'] === $key && is_numeric($fact['value']) && $fact['value'] >= 0));
                $legendHeight = 0;
                foreach ($group as $fact) {
                    $legendHeight += count($this->wrap($fact['farm_name'], 17, $width > 1200 ? $width - 615 : $width - 210)) * 29 + 62;
                }
                $h = $width > 1200 ? max(530, 140 + $legendHeight) : 550 + $legendHeight;
                $blocks[] = ['type' => 'pie', 'x' => 40, 'y' => $y, 'w' => $width - 80, 'h' => $h, 'facts' => $group, 'key' => $key];
                $y += $h + $gap;
                $facts = array_values(array_filter($facts, fn ($fact) => $fact['key'] !== $key));
            } else {
                $notice = 'Diagram proporsi memerlukan luas lahan atau hasil panen dari minimal dua kebun. Persentase pertumbuhan dan fakta lain ditampilkan sebagai nilai masing-masing kebun.';
                $noticeHeight = 48 + count($this->wrap($notice, 17, $width - 136)) * 28;
                $blocks[] = ['type' => 'notice', 'x' => 40, 'y' => $y, 'w' => $width - 80, 'h' => $noticeHeight, 'text' => $notice];
                $y += $noticeHeight + 24;
            }
        }

        $grouped = [];
        if ($layout === 'comparison') {
            foreach ($plan['fields'] ?? [] as $field) {
                $matching = array_values(array_filter($facts, fn ($fact) => $fact['key'] === $field));
                if ($matching !== []) {
                    $grouped[] = ['title' => $matching[0]['label'], 'facts' => $matching, 'comparison' => true];
                }
            }
        } else {
            foreach ($plan['farms'] ?? [] as $farm) {
                $matching = array_values(array_filter($facts, fn ($fact) => $fact['farm_id'] === $farm['id']));
                if ($matching !== []) {
                    $grouped[] = ['title' => $farm['name'], 'facts' => $matching, 'comparison' => false];
                }
            }
        }
        $columns = $width > 1200 ? max(2, (int) floor(($width - 56) / 560)) : 1;
        $cardWidth = (int) floor(($width - 80 - $gap * ($columns - 1)) / $columns);
        $row = [];
        foreach ($grouped as $group) {
            $h = 54 + count($this->wrap($group['title'], 22, $cardWidth - 56, true)) * 34;
            $items = [];
            if ($layout === 'summary') {
                $items = $this->summaryItems($group['facts'], $cardWidth - 56);
                $h += max(0, ...array_map(fn ($item) => $item['y'] + $item['h'], $items)) + 6;
            } else {
                foreach ($group['facts'] as $fact) {
                    $label = $group['comparison'] ? $fact['farm_name'] : $fact['label'];
                    $h += count($this->wrap($label, 14, $cardWidth - 56)) * 24
                        + count($this->wrap($fact['display'], 19, $cardWidth - 56, true)) * 31 + 24;
                }
            }
            $row[] = ['type' => $layout === 'risk' ? 'risk' : ($layout === 'summary' ? 'summary' : 'card'), 'w' => $cardWidth, 'h' => $h, 'items' => $items, ...$group];
            if (count($row) === $columns) {
                $y = $this->appendRow($blocks, $row, $y, $gap);
                $row = [];
            }
        }
        if ($row !== []) {
            $this->appendRow($blocks, $row, $y, $gap);
        }

        return $blocks;
    }

    /** @param list<Fact> $facts
     * @return list<Item>
     */
    private function summaryItems(array $facts, int $width): array
    {
        $items = [];
        $y = 0;
        for ($index = 0; $index < count($facts); $index++) {
            $fact = $facts[$index];
            $numeric = is_int($fact['value']) || is_float($fact['value']);
            $pair = $numeric && $width >= 560 && isset($facts[$index + 1])
                && (is_int($facts[$index + 1]['value']) || is_float($facts[$index + 1]['value']));
            $row = $pair ? [$fact, $facts[++$index]] : [$fact];
            $itemWidth = $pair ? (int) floor(($width - 18) / 2) : $width;
            $rowHeight = 0;
            foreach ($row as $column => $itemFact) {
                $itemNumeric = is_int($itemFact['value']) || is_float($itemFact['value']);
                $height = $itemNumeric
                    ? 92 + count($this->wrap($itemFact['label'], 14, $itemWidth - 40)) * 24 + count($this->wrap($itemFact['display'], 26, $itemWidth - 40, true)) * 42
                    : count($this->wrap($itemFact['label'], 14, $itemWidth)) * 24 + count($this->wrap($itemFact['display'], 19, $itemWidth, true)) * 31 + 24;
                $rowHeight = max($rowHeight, $height);
                $items[] = ['fact' => $itemFact, 'x' => $column * ($itemWidth + 18), 'y' => $y, 'w' => $itemWidth, 'h' => $height, 'metric' => $itemNumeric];
            }
            $y += $rowHeight + ($numeric ? 18 : 0);
        }

        return $items;
    }

    /** @param list<Block> $blocks
     * @param  list<Block>  $row
     */
    private function appendRow(array &$blocks, array $row, int $y, int $gap): int
    {
        $height = max(0, ...array_column($row, 'h'));
        foreach ($row as $index => $block) {
            $blocks[] = [...$block, 'x' => 40 + ($block['w'] + $gap) * $index, 'y' => $y];
        }

        return $y + $height + $gap;
    }

    /** @param Block $block */
    private function drawBlock(GdImage $canvas, array $block): void
    {
        ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h] = $block;
        $this->manifest['blocks'][] = ['type' => $block['type'], 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
        $this->rounded($canvas, $x, $y, $w, $h, $this->colors['white'], $this->colors['border']);
        if ($block['type'] === 'bar') {
            $this->drawBars($canvas, $block);
        } elseif ($block['type'] === 'pie') {
            $this->drawPie($canvas, $block);
        } elseif ($block['type'] === 'notice') {
            $this->paragraph($canvas, $block['text'], $x + 28, $y + 24, $w - 56, 17, 'muted');
        } else {
            if ($block['type'] === 'risk') {
                imagefilledrectangle($canvas, $x + 1, $y + 24, $x + 7, $y + $h - 24, $this->colors['warning']);
            }
            $cursor = $y + 26;
            $cursor += $this->paragraph($canvas, $block['title'], $x + 28, $cursor, $w - 56, 22, 'dark', true, 34) + 24;
            if ($block['type'] === 'summary') {
                foreach ($block['items'] as $item) {
                    $this->drawSummaryItem($canvas, $item, $x + 28, $cursor, $block['facts']);
                }

                return;
            }
            foreach ($block['facts'] as $fact) {
                $start = $cursor;
                $label = $block['comparison'] ? $fact['farm_name'] : $fact['label'];
                $cursor += $this->paragraph($canvas, $label, $x + 28, $cursor, $w - 56, 14, 'muted', false, 24) + 3;
                $cursor += $this->paragraph($canvas, $fact['display'], $x + 28, $cursor, $w - 56, 19, 'text', true, 31) + 21;
                $this->recordFact($fact, ['x' => $x + 28, 'y' => $start, 'width' => $w - 56, 'height' => $cursor - $start]);
            }
        }
    }

    /** @param Item $item
     * @param  list<Fact>  $farmFacts
     */
    private function drawSummaryItem(GdImage $canvas, array $item, int $left, int $top, array $farmFacts): void
    {
        $fact = $item['fact'];
        $x = $left + $item['x'];
        $y = $top + $item['y'];
        $w = $item['w'];
        $h = $item['h'];
        if ($item['metric'] && (is_int($fact['value']) || is_float($fact['value']))) {
            $this->rounded($canvas, $x, $y, $w, $h, $this->colors['light']);
            $cursor = $y + 18;
            $cursor += $this->paragraph($canvas, $fact['label'], $x + 20, $cursor, $w - 40, 14, 'muted', false, 24) + 6;
            $this->paragraph($canvas, $fact['display'], $x + 20, $cursor, $w - 40, 26, 'dark', true, 42);
            $max = null;
            $caption = '';
            if ($fact['key'] === 'growth' && $fact['value'] >= 0 && $fact['value'] <= 100) {
                $max = 100;
                $caption = 'Skala 0–100%';
            } elseif ($fact['key'] === 'used_hectare') {
                $capacity = current(array_filter($farmFacts, fn ($other) => $other['key'] === 'available_hectare' && is_numeric($other['value']) && $other['value'] > 0));
                if ($capacity && $fact['value'] >= 0 && $fact['value'] <= $capacity['value']) {
                    $max = $capacity['value'];
                    $caption = 'Total lahan: '.$capacity['display'];
                }
            }
            if ($max !== null) {
                $track = ['x' => $x + 20, 'y' => $y + $h - 52, 'width' => $w - 40, 'height' => 10];
                imagefilledrectangle($canvas, $track['x'], $track['y'], $track['x'] + $track['width'], $track['y'] + 10, $this->colors['white']);
                $filled = (int) round($track['width'] * $fact['value'] / $max);
                if ($filled > 0) {
                    imagefilledrectangle($canvas, $track['x'], $track['y'], $track['x'] + $filled - 1, $track['y'] + 10, $this->colors['dark']);
                }
                $this->paragraph($canvas, $caption, $x + 20, $y + $h - 30, $w - 40, 12, 'muted', false, 19);
                $this->manifest['charts'][] = ['type' => 'progress', 'farm_id' => $fact['farm_id'], 'key' => $fact['key'], 'value' => $fact['value'], 'axis_min' => 0, 'axis_max' => $max, 'track' => $track, 'bar_width' => $filled];
            }
        } else {
            $cursor = $y;
            $cursor += $this->paragraph($canvas, $fact['label'], $x, $cursor, $w, 14, 'muted', false, 24) + 3;
            $this->paragraph($canvas, $fact['display'], $x, $cursor, $w, 19, 'text', true, 31);
        }
        $this->recordFact($fact, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
    }

    /** @param Block $block */
    private function drawBars(GdImage $canvas, array $block): void
    {
        ['x' => $x, 'y' => $y, 'w' => $w] = $block;
        $unit = $block['unit'];
        $this->paragraph($canvas, 'Perbandingan · '.$unit, $x + 28, $y + 25, $w - 56, 22, 'dark', true);
        $max = max(0, ...array_column($block['facts'], 'value'));
        $chart = ['type' => 'bar', 'unit' => $unit, 'zero_baseline' => true, 'axis_min' => 0, 'axis_max' => $max, 'values' => []];
        $cursor = $y + 80;
        foreach ($block['facts'] as $index => $fact) {
            $start = $cursor;
            $cursor += $this->paragraph($canvas, $fact['farm_name'].' · '.$fact['label'], $x + 28, $cursor, $w - 56, 17, 'text', false, 29) + 9;
            $track = ['x' => $x + 28, 'y' => $cursor, 'width' => $w - 56, 'height' => 23];
            imagefilledrectangle($canvas, $track['x'], $cursor, $track['x'] + $track['width'], $cursor + 23, $this->colors['light']);
            $barWidth = $max > 0 ? (int) round($track['width'] * $fact['value'] / $max) : 0;
            if ($barWidth > 0) {
                imagefilledrectangle($canvas, $track['x'], $cursor, $track['x'] + $barWidth - 1, $cursor + 23, $this->colors['series'][$index % count($this->colors['series'])]);
            }
            // Every bar has a common, explicitly visible zero origin.
            imageline($canvas, $track['x'], $cursor - 4, $track['x'], $cursor + 27, $this->colors['dark']);
            $cursor += 31;
            $this->paragraph($canvas, '0', $x + 28, $cursor, 40, 11, 'muted');
            $this->paragraph($canvas, $fact['display'], $x + 85, $cursor, $w - 113, 17, 'dark', true);
            $cursor += 33;
            $this->recordFact($fact, ['x' => $x + 28, 'y' => $start, 'width' => $w - 56, 'height' => $cursor - $start]);
            $chart['values'][] = ['farm_id' => $fact['farm_id'], 'key' => $fact['key'], 'value' => $fact['value'], 'display' => $fact['display'], 'track' => $track, 'bar_width' => $barWidth];
        }
        $this->manifest['charts'][] = $chart;
    }

    /** @param Block $block */
    private function drawPie(GdImage $canvas, array $block): void
    {
        ['x' => $x, 'y' => $y, 'w' => $w] = $block;
        $facts = $block['facts'];
        $this->paragraph($canvas, $facts[0]['label'], $x + 28, $y + 24, $w - 56, 22, 'dark', true);
        $this->paragraph($canvas, 'Proporsi antar-kebun terpilih · '.$facts[0]['unit'], $x + 28, $y + 66, $w - 56, 14, 'muted');
        $wide = $w > 1200;
        $diameter = 350;
        $cx = $wide ? $x + 225 : $x + (int) ($w / 2);
        $cy = $y + 300;
        $total = array_sum(array_column($facts, 'value'));
        $angle = -90.0;
        $chart = ['type' => 'pie', 'key' => $block['key'], 'scope' => 'selected_farms', 'unit' => $facts[0]['unit'], 'geometry_denominator' => $total, 'values' => []];
        $legendX = $wide ? $x + 465 : $x + 36;
        $legendWidth = $wide ? $w - 505 : $w - 100;
        $cursor = $wide ? $y + 130 : $y + 510;
        foreach ($facts as $index => $fact) {
            $color = $this->colors['series'][$index % count($this->colors['series'])];
            $end = $angle + 360 * $fact['value'] / $total;
            if ($fact['value'] > 0) {
                imagefilledarc($canvas, $cx, $cy, $diameter, $diameter, (int) round($angle), (int) round($end), $color, IMG_ARC_PIE);
            }
            $start = $cursor;
            imagefilledellipse($canvas, $legendX + 8, $cursor + 12, 16, 16, $color);
            $cursor += $this->paragraph($canvas, $fact['farm_name'], $legendX + 30, $cursor, $legendWidth - 30, 17, 'text', false, 29) + 6;
            $cursor += $this->paragraph($canvas, $fact['display'], $legendX + 30, $cursor, $legendWidth - 30, 20, 'dark', true, 32) + 24;
            $this->recordFact($fact, ['x' => $legendX, 'y' => $start, 'width' => $legendWidth, 'height' => $cursor - $start]);
            $chart['values'][] = ['farm_id' => $fact['farm_id'], 'key' => $fact['key'], 'value' => $fact['value'], 'display' => $fact['display'], 'start_degrees' => $angle, 'end_degrees' => $end];
            $angle = $end;
        }
        imagefilledellipse($canvas, $cx, $cy, 185, 185, $this->colors['white']);
        $this->paragraph($canvas, 'Komposisi', $cx - 73, $cy - 12, 160, 17, 'dark', true);
        $this->manifest['charts'][] = $chart;
    }

    /** @param Fact $fact
     * @param  Bounds  $bounds
     */
    private function recordFact(array $fact, array $bounds): void
    {
        $this->manifest['facts'][] = [...$fact, 'bounds' => $bounds];
    }

    /** @param Plan $plan
     * @return list<string>
     */
    private function footerLines(array $plan): array
    {
        $source = $plan['source'] ?? [];
        $parts = [(string) ($source['name'] ?? 'Data demo')];
        if (isset($source['version'])) {
            $parts[] = 'v'.$source['version'];
        }
        if (! empty($source['generated_at'])) {
            $parts[] = 'tanggal '.$source['generated_at'];
        }
        if (! empty($source['sha256'])) {
            $parts[] = 'SHA-256 '.substr($source['sha256'], 0, 12);
        }
        $lines = ['Sumber data demo: '.implode(' · ', $parts), 'Ilustrasi dibuat AI. Angka dan label digambar langsung dari data demo.'];
        foreach ($plan['warnings'] ?? [] as $warning) {
            $lines[] = 'Catatan: '.(string) $warning;
        }

        return $lines;
    }

    /** @param 'background'|'white'|'text'|'muted'|'border'|'warning'|'dark'|'light' $color */
    private function paragraph(GdImage $canvas, string $text, int $x, int $y, int $width, int $size, string $color, bool $bold = false, ?int $lineHeight = null): int
    {
        $lineHeight ??= (int) ceil($size * 1.6);
        foreach ($this->wrap($text, $size, $width, $bold) as $index => $line) {
            $font = $bold ? $this->boldFont : $this->font;
            $bbox = $this->textBounds($size, $font, $line);
            $minX = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
            $minY = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
            $maxX = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
            $maxY = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
            $top = $y + $index * $lineHeight;
            imagettftext($canvas, $size, 0, $x - $minX, $top - $minY, $this->colors[$color], $font, $line);
            $this->manifest['text_runs'][] = ['text' => $line, 'x' => $x, 'y' => $top, 'size' => $size, 'font' => $bold ? 'bold' : 'regular', 'bounds' => ['x' => $x, 'y' => $top, 'width' => $maxX - $minX, 'height' => $maxY - $minY]];
        }

        return count($this->wrap($text, $size, $width, $bold)) * $lineHeight;
    }

    /** @return non-empty-list<string> */
    private function wrap(string $text, int $size, int $width, bool $bold = false): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', trim($text)) ?: [''] as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/u', $paragraph) ?: [''] as $word) {
                $candidate = $line === '' ? $word : $line.' '.$word;
                if ($this->textWidth($candidate, $size, $bold) <= $width) {
                    $line = $candidate;

                    continue;
                }
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                // Even a long unbroken identifier wraps without cropping.
                foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
                    if ($line !== '' && $this->textWidth($line.$character, $size, $bold) > $width) {
                        $lines[] = $line;
                        $line = '';
                    }
                    $line .= $character;
                }
            }
            $lines[] = $line;
        }

        return $lines;
    }

    private function textWidth(string $text, int $size, bool $bold): int
    {
        $bbox = $this->textBounds($size, $bold ? $this->boldFont : $this->font, $text);

        return max($bbox[0], $bbox[2], $bbox[4], $bbox[6]) - min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
    }

    /** @return array{int, int, int, int, int, int, int, int} */
    private function textBounds(int $size, string $font, string $text): array
    {
        $bounds = imagettfbbox($size, 0, $font, $text);
        if ($bounds === false) {
            throw new RuntimeException('Dimensi teks infografis tidak dapat dihitung.');
        }

        return [$bounds[0], $bounds[1], $bounds[2], $bounds[3], $bounds[4], $bounds[5], $bounds[6], $bounds[7]];
    }

    /** @param Bounds $bounds */
    private function placeArt(GdImage $canvas, GdImage $art, array $bounds): void
    {
        // Contain, rather than crop, the generated illustration.
        $scale = min($bounds['width'] / imagesx($art), $bounds['height'] / imagesy($art));
        $w = max(1, (int) round(imagesx($art) * $scale));
        $h = max(1, (int) round(imagesy($art) * $scale));
        imagecopyresampled($canvas, $art, $bounds['x'] + (int) (($bounds['width'] - $w) / 2), $bounds['y'] + (int) (($bounds['height'] - $h) / 2), 0, 0, $w, $h, imagesx($art), imagesy($art));
    }

    private function rounded(GdImage $image, int $x, int $y, int $w, int $h, int $fill, ?int $border = null): void
    {
        $radius = 18;
        if ($border !== null) {
            $this->rounded($image, $x, $y, $w, $h, $border);
            $x++;
            $y++;
            $w -= 2;
            $h -= 2;
        }
        imagefilledrectangle($image, $x + $radius, $y, $x + $w - $radius, $y + $h, $fill);
        imagefilledrectangle($image, $x, $y + $radius, $x + $w, $y + $h - $radius, $fill);
        foreach ([[$x + $radius, $y + $radius], [$x + $w - $radius, $y + $radius], [$x + $radius, $y + $h - $radius], [$x + $w - $radius, $y + $h - $radius]] as [$cx, $cy]) {
            imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, $fill);
        }
    }

    /** @return Palette */
    private function palette(GdImage $canvas, string $theme): array
    {
        $base = ['background' => '#F9FAF9', 'white' => '#FFFFFF', 'text' => '#070807', 'muted' => '#53635A', 'border' => '#D9D9D9', 'warning' => '#EDC92C'];
        $themes = [
            'green' => ['dark' => '#1C5E21', 'light' => '#DCEEDF', 'series' => ['#1C5E21', '#68BB6C', '#93A99F', '#4A943E', '#B5CE7D']],
            'blue' => ['dark' => '#174B70', 'light' => '#E2EFF8', 'series' => ['#174B70', '#458CBD', '#8AB8D8', '#426B8B', '#B3CBDC']],
            'monochrome' => ['dark' => '#252C28', 'light' => '#E5E8E6', 'series' => ['#252C28', '#637069', '#919C95', '#454D48', '#BBC3BD']],
        ];
        $values = array_merge($base, $themes[$theme]);
        $color = function (string $hex) use ($canvas): int {
            $value = imagecolorallocate($canvas, $this->colorChannel(substr($hex, 1, 2)), $this->colorChannel(substr($hex, 3, 2)), $this->colorChannel(substr($hex, 5, 2)));
            if ($value === false) {
                throw new RuntimeException('Warna infografis tidak dapat dialokasikan.');
            }

            return $value;
        };
        foreach ($values as $key => $value) {
            $values[$key] = is_array($value) ? array_map($color, $value) : $color($value);
        }

        return $values;
    }

    /** @return int<0, 255> */
    private function colorChannel(string $hex): int
    {
        $value = hexdec($hex);
        if (! is_int($value) || $value < 0 || $value > 255) {
            throw new RuntimeException('Komponen warna infografis tidak valid.');
        }

        return $value;
    }
}

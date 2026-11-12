<?php

namespace App\Notifications;

use App\Notifications\Channels\InAppChannel;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class WeeklyReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data)
    {
        $this->onConnection(config('operational_notifications.connection'));
        $this->onQueue(config('operational_notifications.in_app_queue'));
        $this->afterCommit();
    }

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [InAppChannel::class];
    }

    /** @return array{title: string, message: string, content: array<string, mixed>} */
    public function toInApp(): array
    {
        $owner = $this->data['audience'] === 'owner';
        $lines = $owner ? $this->ownerLines($this->data['farms'], true) : $this->adminLines($this->data['farms']);

        return ['title' => $owner ? 'Laporan Weekly Panen' : 'Laporan Weekly Aktivitas Petugas',
            'message' => $this->header()."\n\n".implode("\n", $lines),
            'content' => $this->toInboxContent()];
    }

    /** @return array<string, mixed> */
    public function toInboxContent(): array
    {
        $base = ['version' => 1, 'period' => [
            'from' => $this->data['period_start'], 'to' => $this->data['period_end'],
        ]];
        if ($this->data['audience'] === 'admin') {
            return [...$base, 'kind' => 'weekly_admin', 'farms' => array_map(fn (array $farm): array => [
                'id' => $farm['id'], 'name' => $farm['name'], 'activity_count' => $farm['activity_count'],
                'blocks' => array_map(fn (array $block): array => [
                    'id' => $block['id'], 'name' => $block['name'],
                    'activities' => $this->countItems($block['activity_counts']),
                    'obstacle_count' => $block['obstacle_count'], 'obstacle_summary' => $block['obstacle_summary'],
                    'incidents' => $this->countItems($block['incidents']),
                ], $farm['blocks']),
            ], $this->data['farms'])];
        }
        $regions = [];
        $farms = $this->data['farms'];
        if (! is_array($farms)) {
            throw new \LogicException('Weekly report farms must be an array.');
        }
        foreach (collect($farms)->groupBy('region_key')->sortBy(fn ($group) => $group->first()['region_name'], SORT_NATURAL | SORT_FLAG_CASE) as $key => $group) {
            $regions[] = ['key' => $key, 'name' => $group->first()['region_name'],
                ...$this->harvestSummary(array_values($group->all()))];
        }

        return [...$base, 'kind' => 'weekly_owner', 'totals' => $this->harvestSummary(array_values($farms)), 'regions' => $regions];
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{label: string, count: int}>
     */
    private function countItems(array $counts): array
    {
        $items = [];
        foreach ($counts as $label => $count) {
            $items[] = ['label' => $label, 'count' => $count];
        }

        return $items;
    }

    public function toWhatsAppText(): string
    {
        $owner = $this->data['audience'] === 'owner';
        $title = $owner ? 'Laporan Weekly Panen' : 'Laporan Weekly Aktivitas Petugas';
        $lines = $owner ? $this->ownerLines($this->data['farms'], false) : $this->adminLines($this->data['farms']);

        return '*'.$title."*\n".$this->header()."\n\n".implode("\n", $lines);
    }

    private function header(): string
    {
        return 'Periode: '.$this->date($this->data['period_start']).'–'.$this->date($this->data['period_end'])
            ."\nBatas rekap: Minggu 20:00 WIB";
    }

    /**
     * @param  list<array<string, mixed>>  $farms
     * @return list<string>
     */
    private function adminLines(array $farms): array
    {
        $lines = [];
        foreach ($farms as $farm) {
            $lines[] = 'Kebun: '.$farm['name'];
            if ($farm['activity_count'] === 0) {
                $lines[] = 'Belum ada aktivitas yang dilaporkan Petugas minggu ini.';
            }
            foreach ($farm['blocks'] as $block) {
                if ($block['activity_counts'] === []) {
                    continue;
                }
                $lines[] = $block['name'].': '.$this->counts($block['activity_counts'], 'kali').'.';
                if ($block['obstacle_count'] > 0) {
                    $lines[] = 'Hambatan: '.$block['obstacle_count'].' laporan. Utama: '.$block['obstacle_summary'];
                }
                if ($block['incidents'] !== []) {
                    $lines[] = 'Insiden: '.$this->counts($block['incidents'], 'kejadian').'.';
                }
            }
            $lines[] = '';
        }

        return $lines;
    }

    /** @param array<string, int> $counts */
    private function counts(array $counts, string $unit): string
    {
        $parts = [];
        foreach ($counts as $name => $count) {
            $parts[] = $name.' '.$count.' '.$unit;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $farms
     * @return list<string>
     */
    private function ownerLines(array $farms, bool $includeTotal): array
    {
        $lines = $includeTotal ? ['Total seluruh kebun', ...$this->harvestLines($farms), ''] : [];
        $regions = collect($farms)->groupBy('region_key')->sortBy(fn ($group) => $group->first()['region_name'], SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($regions as $group) {
            $lines[] = 'Kabupaten/kota: '.$group->first()['region_name'];
            array_push($lines, ...$this->harvestLines(array_values($group->all())));
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $farms
     * @return array{commodities: list<array<string, mixed>>, unidentified_batches: int|float}
     */
    private function harvestSummary(array $farms): array
    {
        $totals = [];
        $unidentified = 0;
        foreach ($farms as $farm) {
            $unidentified += $farm['unidentified_batches'];
            foreach ($farm['crops'] as $crop) {
                $name = $crop['name'];
                $totals[$name] ??= ['target_ton' => 0.0, 'target_known' => 0, 'target_missing' => 0,
                    'harvest_ton' => 0.0, 'harvest_area_ha' => 0.0, 'harvest_count' => 0, 'harvest_missing' => 0];
                if ($crop['target_ton'] !== null) {
                    $totals[$name]['target_ton'] += $crop['target_ton'];
                    $totals[$name]['target_known']++;
                }
                foreach (['target_missing', 'harvest_ton', 'harvest_area_ha', 'harvest_count', 'harvest_missing'] as $field) {
                    $totals[$name][$field] += $crop[$field];
                }
            }
        }
        ksort($totals, SORT_NATURAL | SORT_FLAG_CASE);
        $commodities = [];
        foreach ($totals as $name => $crop) {
            $commodities[] = ['name' => $name, 'target_ton' => $crop['target_known'] === 0 ? null : $crop['target_ton'],
                'target_missing' => $crop['target_missing'], 'harvest_ton' => $crop['harvest_ton'],
                'harvest_area_ha' => $crop['harvest_area_ha'], 'harvest_count' => $crop['harvest_count'],
                'harvest_missing' => $crop['harvest_missing']];
        }

        return ['commodities' => $commodities, 'unidentified_batches' => $unidentified];
    }

    /**
     * @param  list<array<string, mixed>>  $farms
     * @return list<string>
     */
    private function harvestLines(array $farms): array
    {
        $summary = $this->harvestSummary($farms);
        $lines = [];
        $harvestCount = 0;
        foreach ($summary['commodities'] as $crop) {
            $target = $crop['target_ton'] === null ? 'belum tersedia' : $this->number($crop['target_ton']).' ton';
            if ($crop['target_missing'] > 0 && $crop['target_ton'] !== null) {
                $target .= ' (sebagian target belum tersedia)';
            }
            $harvest = $crop['harvest_count'] > 0 && $crop['harvest_count'] === $crop['harvest_missing']
                ? 'berat belum tercatat' : $this->number($crop['harvest_ton']).' ton';
            if ($crop['harvest_missing'] > 0 && $crop['harvest_count'] > $crop['harvest_missing']) {
                $harvest .= ' ('.$crop['harvest_missing'].' laporan belum memiliki berat)';
            }
            if ($crop['harvest_area_ha'] > 0) {
                $harvest .= '; luas tercatat '.$this->number($crop['harvest_area_ha'], 4).' ha';
            }
            $lines[] = $crop['name'].' — target per cycle: '.$target.'; panen minggu ini: '.$harvest.'.';
            $harvestCount += $crop['harvest_count'];
        }
        if ($harvestCount === 0) {
            $lines[] = 'Tidak ada panen yang terjadi minggu ini.';
        }
        if ($summary['unidentified_batches'] > 0) {
            $lines[] = $summary['unidentified_batches'].' siklus aktif belum memiliki data komoditas untuk proyeksi.';
        }

        return $lines;
    }

    private function number(float $value, int $precision = 3): string
    {
        return rtrim(rtrim(number_format($value, $precision, ',', '.'), '0'), ',');
    }

    private function date(string $date): string
    {
        return CarbonImmutable::parse($date, config('app.timezone'))->settings(['locale' => 'id'])->translatedFormat('j F Y');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }
}

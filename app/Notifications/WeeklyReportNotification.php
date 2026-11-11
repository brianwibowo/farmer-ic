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

    /** @return array{title: string, message: string} */
    public function toInApp(): array
    {
        $owner = $this->data['audience'] === 'owner';
        $lines = $owner ? $this->ownerLines($this->data['farms'], true) : $this->adminLines($this->data['farms']);

        return ['title' => $owner ? 'Laporan Weekly Panen' : 'Laporan Weekly Aktivitas Petugas',
            'message' => $this->header()."\n\n".implode("\n", $lines)];
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
     * @return list<string>
     */
    private function harvestLines(array $farms): array
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
        $lines = [];
        $harvestCount = 0;
        foreach ($totals as $name => $crop) {
            $target = $crop['target_known'] === 0 ? 'belum tersedia' : $this->number($crop['target_ton']).' ton';
            if ($crop['target_missing'] > 0 && $crop['target_known'] > 0) {
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
            $lines[] = $name.' — target per cycle: '.$target.'; panen minggu ini: '.$harvest.'.';
            $harvestCount += $crop['harvest_count'];
        }
        if ($harvestCount === 0) {
            $lines[] = 'Tidak ada panen yang terjadi minggu ini.';
        }
        if ($unidentified > 0) {
            $lines[] = $unidentified.' siklus aktif belum memiliki data komoditas untuk proyeksi.';
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

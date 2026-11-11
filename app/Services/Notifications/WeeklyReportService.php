<?php

namespace App\Services\Notifications;

use App\Enums\IncidentSeverity;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\HarvestDetail;
use App\Models\IncidentDetail;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\Owner\HarvestProjectionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class WeeklyReportService
{
    public function __construct(private readonly HarvestProjectionService $projections) {}

    /**
     * @param  Collection<int, Farm>  $farms
     * @return list<array<string, mixed>>
     */
    public function adminFarms(User $actor, Team $team, Collection $farms, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $blocks = FarmBlock::visibleTo($actor, $team)->whereIn('farm_id', $farms->modelKeys())->orderBy('name')->orderBy('id')->get();
        $cycles = PlantingCycle::visibleTo($actor, $team)->whereIn('farm_id', $farms->modelKeys())->get()->keyBy('id');
        $activities = FarmActivity::visibleTo($actor, $team)->whereIn('farm_id', $farms->modelKeys())
            ->whereBetween('activity_date', [$start->toIso8601String(), $end->toIso8601String()])
            ->whereHas('performer', fn ($users) => $users->withTrashed()->whereHas('role', fn ($roles) => $roles->where('name', 'petugas')))
            ->with(['activityType' => fn ($types) => $types->visibleTo($actor, $team)])
            ->orderBy('activity_date')->orderBy('id')->get();
        $incidents = IncidentDetail::visibleTo($actor, $team)->whereIn('activity_id', $activities->modelKeys())->get()->keyBy('activity_id');

        return array_values($farms->map(function (Farm $farm) use ($team, $blocks, $cycles, $activities, $incidents): array {
            $farmActivities = $activities->where('farm_id', $farm->id);
            $farmBlocks = $blocks->where('farm_id', $farm->id)->keyBy('id');
            $groups = $farmBlocks->map(fn (FarmBlock $block): array => [
                'id' => $block->id, 'name' => $block->getAttribute('name'), 'activity_counts' => [], 'obstacle_count' => 0, 'obstacles' => [], 'incidents' => [],
            ])->all();
            foreach ($farmActivities as $activity) {
                $blockId = $activity->getAttribute('block_id') ?? $cycles->get($activity->planting_cycle_id)?->block_id;
                $key = $blockId ?? 'unassigned';
                if ($blockId !== null && ! $farmBlocks->has($blockId)) {
                    $key = 'unassigned';
                }
                $groups[$key] ??= ['id' => null, 'name' => 'Tanpa blok', 'activity_counts' => [], 'obstacle_count' => 0, 'obstacles' => [], 'incidents' => []];
                $name = $activity->activityType->name ?? 'Aktivitas lainnya';
                $groups[$key]['activity_counts'][$name] = ($groups[$key]['activity_counts'][$name] ?? 0) + 1;
                if (filled($activity->obstacles)) {
                    $groups[$key]['obstacle_count']++;
                    $summary = Str::limit(Str::squish($activity->obstacles), 100);
                    $groups[$key]['obstacles'][$summary] = ($groups[$key]['obstacles'][$summary] ?? 0) + 1;
                }
                $incident = $incidents->get($activity->id);
                if ($incident !== null) {
                    $severity = IncidentSeverity::fromStored($incident->severity)?->label() ?? $incident->severity ?? 'Belum ditentukan';
                    $label = ($incident->incident_category ?: 'Insiden lainnya').' ('.$severity.')';
                    $groups[$key]['incidents'][$label] = ($groups[$key]['incidents'][$label] ?? 0) + 1;
                }
            }
            foreach ($groups as &$group) {
                ksort($group['activity_counts'], SORT_NATURAL | SORT_FLAG_CASE);
                ksort($group['incidents'], SORT_NATURAL | SORT_FLAG_CASE);
                arsort($group['obstacles']);
                $group['obstacle_summary'] = array_key_first($group['obstacles']);
                unset($group['obstacles']);
            }
            unset($group);

            return [
                'id' => $farm->id, 'name' => $farm->name, 'team_id' => $team->id,
                'activity_count' => $farmActivities->count(), 'blocks' => array_values($groups),
            ];
        })->all());
    }

    /**
     * @param  Collection<int, Farm>  $farms
     * @return list<array<string, mixed>>
     */
    public function ownerFarms(User $actor, Team $team, Collection $farms, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $projection = $this->projections->summary($actor, $team, 'cycle');
        $rows = $projection['rows'];
        $defaults = array_column($projection['cards'], 'name');
        $cycles = PlantingCycle::visibleTo($actor, $team)->whereIn('farm_id', $farms->modelKeys())
            ->with(['variety' => fn ($varieties) => $varieties->visibleTo($actor, $team)
                ->with(['commodity' => fn ($commodities) => $commodities->visibleTo($actor, $team)])])->get()->keyBy('id');
        $farmCommodities = Commodity::visibleTo($actor, $team)->whereKey($farms->pluck('commodity_id')->filter())->get()->keyBy('id');
        $activities = FarmActivity::visibleTo($actor, $team)->whereIn('farm_id', $farms->modelKeys())
            ->whereBetween('activity_date', [$start->toIso8601String(), $end->toIso8601String()])
            ->whereHas('activityType', fn ($types) => $types->visibleTo($actor, $team)->where('code', 'harvest'))->get();
        $harvests = HarvestDetail::visibleTo($actor, $team)->whereIn('activity_id', $activities->modelKeys())->get()->keyBy('activity_id');

        return array_values($farms->map(function (Farm $farm) use ($team, $rows, $defaults, $cycles, $farmCommodities, $activities, $harvests): array {
            $crops = [];
            foreach ($defaults as $name) {
                $crops[$name] = $this->crop($name);
            }
            foreach ($rows as $row) {
                if ($row['farm_id'] === $farm->id && $row['crop_name'] !== null) {
                    $name = $this->cropName($row['crop_name'], $defaults);
                    $crops[$name] = [...$this->crop($name), 'target_ton' => $row['projection_ton'],
                        'target_missing' => $row['missing_count']];
                }
            }
            foreach ($activities->where('farm_id', $farm->id) as $activity) {
                $detail = $harvests->get($activity->id);
                $name = $cycles->get($activity->planting_cycle_id)?->variety?->commodity->name
                    ?? $farmCommodities->get($farm->getAttribute('commodity_id'))->name ?? 'Komoditas belum diisi';
                $name = $this->cropName($name, $defaults);
                $crops[$name] ??= [...$this->crop($name), 'target_ton' => null, 'target_missing' => 1];
                $crops[$name]['harvest_count']++;
                $crops[$name]['harvest_ton'] += (float) ($detail->gross_weight_ton ?? 0);
                $crops[$name]['harvest_area_ha'] += (float) ($detail->harvested_area_ha ?? 0);
                $crops[$name]['harvest_missing'] += $detail?->gross_weight_ton === null ? 1 : 0;
            }
            ksort($crops, SORT_NATURAL | SORT_FLAG_CASE);
            $region = $this->region($farm);

            return [
                'id' => $farm->id, 'team_id' => $team->id, 'region_key' => $region['key'],
                'region_name' => $region['name'],
                'crops' => array_values($crops),
                'unidentified_batches' => $cycles->where('farm_id', $farm->id)->where('status', 'active')
                    ->filter(fn (PlantingCycle $cycle): bool => $cycle->variety?->commodity === null)->count(),
            ];
        })->all());
    }

    /** @param list<string> $defaults */
    private function cropName(string $name, array $defaults): string
    {
        foreach ($defaults as $canonical) {
            if (mb_strtolower(trim($name)) === mb_strtolower($canonical)) {
                return $canonical;
            }
        }

        return trim($name);
    }

    /** @return array{key: string, name: string} */
    public function region(Farm $farm): array
    {
        $city = preg_replace('/\s+/u', ' ', trim($farm->city ?? '')) ?? '';
        $province = preg_replace('/\s+/u', ' ', trim($farm->province ?? '')) ?? '';
        $code = $farm->getAttribute('city_code');

        return [
            // The saved province/city names also let coded and legacy farms
            // in the same county merge, without merging equal names across provinces.
            'key' => $city !== '' && $province !== '' ? 'name:'.mb_strtolower($province.'|'.$city)
                : (filled($code) ? 'code:'.$code : ($city === '' ? 'unassigned' : 'name:'.mb_strtolower($city))),
            'name' => $city === '' ? 'Kabupaten belum diisi' : $city,
        ];
    }

    /** @return array<string, mixed> */
    private function crop(string $name): array
    {
        return ['name' => $name, 'target_ton' => 0.0, 'target_missing' => 0,
            'harvest_ton' => 0.0, 'harvest_area_ha' => 0.0, 'harvest_count' => 0, 'harvest_missing' => 0];
    }
}

<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\HarvestDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantObservation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Optional local-only data for visually testing Owner dashboard groups.
 * It is intentionally not called by DatabaseSeeder, keeping the six-farm
 * presentation baseline and its integration tests stable.
 */
class DemoOwnerDashboardCoverageSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Data cakupan dashboard hanya dapat diisi pada environment local atau testing.');
        }

        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])
            ->where('is_active', true)->first();
        $team = $actor?->teams()->where('slug', 'farm-ic-demo')->where('is_personal', false)->first();
        if (! $actor instanceof User || ! $team instanceof Team) {
            throw new RuntimeException('Seed dasar belum tersedia. Jalankan DatabaseSeeder terlebih dahulu.');
        }

        $owner = User::visibleTo($actor, $team)
            ->where('email', DemoIdentitySeeder::ACCOUNTS['owner']['email'])->first();
        if (! $owner instanceof User) {
            throw new RuntimeException('Owner demo belum tersedia. Jalankan DatabaseSeeder terlebih dahulu.');
        }

        Gate::forUser($actor)->authorize('view', $team);
        DB::transaction(function () use ($actor, $owner, $team): void {
            $activityTypes = $this->activityTypes();
            foreach (DemoOwnerDashboardCoverageData::farms() as $site) {
                $farm = $this->farm($actor, $owner, $team, $site);
                $cycle = $this->cycle($actor, $team, $farm, $site);
                $this->stages($actor, $team, $cycle, $site);
                $this->activity($actor, $team, $farm, $cycle, $activityTypes[(string) $site['status']], $site);
            }
        });
    }

    /** @return array<string, ActivityType> */
    private function activityTypes(): array
    {
        return [
            'active' => ActivityType::query()->firstOrCreate(['code' => 'growth_check'], ['name' => 'Pengamatan pertumbuhan']),
            'harvested' => ActivityType::query()->firstOrCreate(['code' => 'harvest'], ['name' => 'Panen']),
        ];
    }

    /** @param array<string, string|int|null> $site */
    private function farm(User $actor, User $owner, Team $team, array $site): Farm
    {
        $id = DemoOwnerDashboardCoverageData::id($team, 'farm:'.$site['key']);
        $existing = Farm::visibleTo($actor, $team)->where('team_id', $team->id)->find($id);
        if ($existing instanceof Farm) {
            if ($existing->land_tenure_party !== $site['tenure_party']) {
                $existing->forceFill(['land_tenure_party' => $site['tenure_party']])->save();
            }

            return $existing;
        }
        if (Farm::query()->whereKey($id)->exists()) {
            throw new RuntimeException('ID fixture kebun cakupan sudah digunakan di luar tenant demo.');
        }

        Gate::forUser($actor)->authorize('create', [Farm::class, $team]);
        $commodity = Commodity::query()->firstOrCreate(['name' => $site['commodity']], [
            'description' => 'Komoditas contoh untuk pengujian tampilan dashboard Owner.',
        ]);
        $farm = new Farm;
        $farm->forceFill([
            'id' => $id,
            'team_id' => $team->id,
            'owner_id' => $owner->id,
            'created_by' => $actor->id,
            'name' => $site['name'],
            'address' => 'Lahan contoh '.$site['district'].', '.$site['city'].'.',
            'city' => $site['city'],
            'district' => $site['district'],
            'province' => 'Jawa Barat',
            'total_area_ha' => $site['area'],
            'land_tenure' => $site['tenure'],
            'land_tenure_party' => $site['tenure_party'],
            'latitude' => $site['latitude'],
            'longitude' => $site['longitude'],
            'commodity_id' => $commodity->id,
            'target_volume_ton' => $site['weight'],
            'image_url' => '/images/kebun-placeholder.svg',
        ])->save();

        return $farm->refresh();
    }

    /** @param array<string, string|int|null> $site */
    private function cycle(User $actor, Team $team, Farm $farm, array $site): PlantingCycle
    {
        $id = DemoOwnerDashboardCoverageData::id($team, 'cycle:'.$site['key']);
        $existing = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->find($id);
        if ($existing instanceof PlantingCycle) {
            return $existing;
        }
        if (PlantingCycle::query()->whereKey($id)->exists()) {
            throw new RuntimeException('ID fixture siklus cakupan sudah digunakan di luar kebun yang diharapkan.');
        }

        Gate::forUser($actor)->authorize('create', [PlantingCycle::class, $farm]);
        $commodity = Commodity::query()->where('name', $site['commodity'])->firstOrFail();
        $variety = CropVariety::query()->firstOrCreate([
            'commodity_id' => $commodity->id,
            'name' => $site['variety'],
        ], ['description' => 'Varietas contoh untuk pengujian tampilan dashboard Owner.']);
        $isPlanned = $site['status'] === 'planned';
        $cycle = new PlantingCycle;
        $cycle->forceFill([
            'id' => $id,
            'farm_id' => $farm->id,
            'variety_id' => $isPlanned ? null : $variety->id,
            'planned_planting_at' => '2026-11-12',
            'planted_at' => $isPlanned ? null : '2026-10-01',
            'estimated_harvest_at' => '2027-02-15',
            'planted_area_ha' => $site['planted_area'],
            'estimated_weight_ton' => $site['weight'],
            'status' => $site['status'],
        ])->save();

        return $cycle->refresh();
    }

    /** @param array<string, string|int|null> $site */
    private function stages(User $actor, Team $team, PlantingCycle $cycle, array $site): void
    {
        Gate::forUser($actor)->authorize('create', [PlantingCycleStage::class, $cycle]);
        foreach (PlantingCycleStage::CODES as $index => $code) {
            if (PlantingCycleStage::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)->where('code', $code)->exists()) {
                continue;
            }
            $completed = $site['status'] === 'harvested' || $index < (int) $site['completed'];
            $inProgress = $site['status'] !== 'harvested' && $index === (int) $site['completed'];
            $stage = new PlantingCycleStage;
            $stage->forceFill([
                'planting_cycle_id' => $cycle->id,
                'code' => $code,
                'position' => $index + 1,
                'status' => $completed ? 'completed' : ($inProgress ? 'in_progress' : 'upcoming'),
                'started_at' => $completed || $inProgress ? '2026-10-'.str_pad((string) (2 + $index), 2, '0', STR_PAD_LEFT).' 07:00:00+07' : null,
                'completed_at' => $completed ? '2026-10-'.str_pad((string) (2 + $index), 2, '0', STR_PAD_LEFT).' 16:00:00+07' : null,
            ])->save();
        }
    }

    /** @param array<string, string|int|null> $site */
    private function activity(User $actor, Team $team, Farm $farm, PlantingCycle $cycle, ActivityType $type, array $site): void
    {
        $id = DemoOwnerDashboardCoverageData::id($team, 'activity:'.$site['key']);
        $activity = FarmActivity::query()->find($id);
        if (! $activity instanceof FarmActivity) {
            Gate::forUser($actor)->authorize('create', [FarmActivity::class, $farm]);
            $activity = new FarmActivity;
            $activity->forceFill([
                'id' => $id,
                'farm_id' => $farm->id,
                'planting_cycle_id' => $cycle->id,
                'activity_type_id' => $type->id,
                'performed_by' => $actor->id,
                'activity_date' => '2026-11-06 09:00:00+07',
                'description' => $site['status'] === 'harvested'
                    ? 'Panen contoh telah dicatat untuk '.$site['name'].'.'
                    : 'Pemeriksaan pertumbuhan dan kondisi tanaman di '.$site['name'].'.',
                'status' => 'completed',
            ])->save();
        }

        if ($site['status'] === 'harvested') {
            if (! HarvestDetail::query()->where('activity_id', $activity->id)->exists()) {
                Gate::forUser($actor)->authorize('create', [HarvestDetail::class, $activity]);
                (new HarvestDetail)->forceFill([
                    'activity_id' => $activity->id,
                    'harvested_area_ha' => $site['planted_area'],
                    'gross_weight_ton' => $site['weight'],
                    'moisture_percentage' => '18.00',
                    'color' => 'Sesuai komoditas',
                    'ripeness' => 'Matang panen',
                    'cleanliness' => 'Bersih setelah sortasi',
                ])->save();
            }

            return;
        }

        if (! PlantObservation::query()->where('activity_id', $activity->id)->exists()) {
            Gate::forUser($actor)->authorize('create', [PlantObservation::class, $cycle]);
            (new PlantObservation)->forceFill([
                'planting_cycle_id' => $cycle->id,
                'activity_id' => $activity->id,
                'observed_by' => $actor->id,
                'observed_at' => '2026-11-06 09:00:00+07',
                'growth_phase' => 'Vegetatif',
                'average_height_cm' => '72.00',
                'disease_indication' => 'Aman',
                'pest_indication' => 'Aman',
                'health_status' => 'Aman',
                'growth_percentage' => '65.00',
                'plant_condition' => 'Tumbuh merata',
                'notes' => 'Observasi contoh untuk pengujian dashboard Owner.',
            ])->save();
        }
    }
}

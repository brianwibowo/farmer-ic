<?php

namespace Database\Seeders;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FarmerModel;
use App\Models\FarmRisk;
use App\Models\HarvestDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\PlantingDetail;
use App\Models\PlantObservation;
use App\Models\Team;
use App\Models\User;
use App\Models\WeatherRecord;
use App\Services\Activity\DailyActivityHeaderService;
use App\Services\Activity\HarvestClosureService;
use App\Services\Activity\PlantingCycleStageService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;
use RuntimeException;

/** Additive, fixed-date operational detail called by DemoOwnerPortfolioSeeder. */
class DemoOwnerPortfolioActivitySeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])
            ->where('is_active', true)->whereHas('role', fn ($roles) => $roles->where('name', 'superadmin'))->first();
        $team = $actor?->teams()->where('slug', 'farm-ic-demo')->first();
        if ($actor === null || $team === null) {
            throw new RuntimeException('Identitas seed belum tersedia. Jalankan DemoIdentitySeeder dan DemoOwnerPortfolioSeeder terlebih dahulu.');
        }
        Gate::forUser($actor)->authorize('viewAny', [User::class, $team]);
        $performer = User::visibleTo($actor, $team)->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])
            ->whereHas('teams', fn ($teams) => $teams->whereKey($team->id))->firstOrFail();

        DB::transaction(function () use ($actor, $performer, $team): void {
            foreach (DemoOwnerPortfolioData::sites() as $site) {
                Gate::forUser($actor)->authorize('viewAny', [Farm::class, $team]);
                $farm = Farm::visibleTo($actor, $team)->where('team_id', $team->id)
                    ->whereKey(DemoOwnerPortfolioData::id($team, 'farm:'.$site['key']))->lockForUpdate()->firstOrFail();
                $this->weather($farm, $site['key'], $actor, $team);
                $this->blockPreparation($farm, $site, $actor, $performer, $team);
                foreach ($site['batches'] as $batch) {
                    if ($batch['status'] === 'planned') {
                        continue;
                    }
                    Gate::forUser($actor)->authorize('viewAny', [PlantingCycle::class, $team]);
                    $cycle = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)
                        ->whereIn('subblock_label', [$batch['subblock_label'], 'DEMO-'.$batch['subblock_label']])->firstOrFail();
                    $this->batchActivities($farm, $cycle, $site, $batch, $actor, $performer, $team);
                }
            }
        });
    }

    /** @param array<string, mixed> $site
     * @param  array<string, mixed>  $batch
     */
    private function batchActivities(Farm $farm, PlantingCycle $cycle, array $site, array $batch, User $actor, User $performer, Team $team): void
    {
        $key = $site['key'].':'.$batch['key'];
        $planted = CarbonImmutable::parse($batch['planted_at'], 'Asia/Jakarta');
        $area = (float) $batch['planted_area_ha'];
        $seedRate = match ($site['commodity']) {
            'Jagung' => 20, 'Sorgum' => 10, 'Rumput' => null,
            default => throw new LogicException('Komoditas portofolio tidak didukung.'),
        };
        $this->activity($farm, $cycle, $key, 'planting', 'planting', $planted->toDateString(),
            'Penanaman '.$site['commodity'].' '.$batch['subblock_label'], [
                'seed_name' => ($site['commodity'] === 'Rumput' ? 'Stek rumput ' : $site['commodity'].' ').$site['variety'],
                'quantity_kg' => $seedRate === null ? null : $area * $seedRate,
                'area_ha' => $area,
            ], $actor, $performer, $team);
        $fertilized = $site['key'] === 'cibogo' ? '2026-11-04' : $planted->addDays(14)->toDateString();
        $this->activity($farm, $cycle, $key, 'maintenance', 'follow_up_fertilization', $fertilized,
            'Pemupukan susulan '.$site['commodity'].' '.$batch['subblock_label'], [
                'area_ha' => $area,
            ], $actor, $performer, $team);
        if ($batch['status'] === 'active') {
            $this->activity($farm, $cycle, $key, 'maintenance', 'irrigation', '2026-11-03',
                'Pengairan '.$site['commodity'].' '.$batch['subblock_label'], [
                    'area_ha' => $area,
                ], $actor, $performer, $team);
        }
        if ($site['key'] === 'mekarsari') {
            $this->activity($farm, $cycle, $key, 'maintenance', 'pest_control', '2026-11-04',
                'Pengendalian kutu daun jagung '.$batch['subblock_label'], [
                    'area_ha' => $area,
                ], $actor, $performer, $team);
        }
        $observed = $batch['status'] === 'harvested'
            ? CarbonImmutable::parse($batch['harvested_at'], 'Asia/Jakarta')->subDay()->toDateString()
            : match ($site['key']) {
                'cibogo' => '2026-11-03', 'cikeas' => '2026-11-04', default => '2026-11-05'
            };
        $observation = $this->activity($farm, $cycle, $key, 'maintenance', 'growth_check', $observed,
            'Observasi '.$site['commodity'].' '.$batch['subblock_label'], [
                'growth_phase' => $batch['status'] === 'harvested' ? 'Pematangan' : ($site['key'] === 'mekarsari' ? 'Pembungaan' : 'Vegetatif'),
                'average_height_cm' => match ($site['key']) {
                    'sukamaju' => 52, 'cibogo' => 18, 'mekarsari' => 65, 'cikeas' => 145, default => 90
                },
                'disease_indication' => 'Tidak terindikasi', 'pest_indication' => $site['key'] === 'mekarsari' ? 'Kutu daun ringan pada tepi blok' : 'Tidak terindikasi',
                'health_status' => $site['key'] === 'mekarsari' ? 'waspada' : 'sehat',
                'growth_percentage' => $batch['status'] === 'harvested' ? 100 : match ($site['key']) {
                    'cibogo' => 28, 'mekarsari' => 68, 'cikeas' => 75, default => 52
                },
                'plant_condition' => $site['key'] === 'mekarsari' ? 'Mayoritas tanaman sehat; sebagian pucuk memerlukan pemantauan kutu daun.' : 'Pertumbuhan seragam dan daun berwarna hijau.',
                'notes' => 'Observasi contoh portofolio pada petak ukur; bukan hasil analisis AI.',
            ], $actor, $performer, $team);
        if (in_array($site['key'], ['mekarsari', 'cikeas'], true)) {
            $this->record(FarmRisk::class, DemoOwnerPortfolioData::id($team, 'risk:'.$key), [
                'planting_cycle_id' => $cycle->id, 'observation_id' => DemoOwnerPortfolioData::id($team, 'observation:'.$key),
                'risk_description' => $site['key'] === 'mekarsari' ? 'Kutu daun ringan pada tanaman tepi blok.' : 'Saluran pengairan sempat tersumbat sedimen.',
                'severity' => $site['key'] === 'mekarsari' ? 'medium' : 'low',
                'recommendation' => $site['key'] === 'mekarsari' ? 'Lanjutkan pemantauan pucuk dan catat perkembangan populasi hama.' : 'Pembersihan saluran selesai; lanjutkan inspeksi rutin.',
                'status' => $site['key'] === 'mekarsari' ? 'monitoring' : 'resolved',
                'detected_at' => $this->at($observed),
            ], $cycle, $actor, $team);
        }
        $this->record(ActivityPhoto::class, DemoOwnerPortfolioData::id($team, 'photo:'.$key), [
            'activity_id' => $observation->id, 'label' => 'Ilustrasi lahan',
            'caption' => 'Ilustrasi lokal untuk contoh portofolio '.$site['name'].'; bukan foto lapangan aktual.',
            'image_url' => '/images/kebun-placeholder.svg', 'uploaded_at' => $this->at($observed),
        ], $observation, $actor, $team);
        if ($batch['status'] === 'harvested') {
            $this->activity($farm, $cycle, $key, 'harvest', 'harvest', $batch['harvested_at'],
                'Panen '.$site['commodity'].' '.$batch['subblock_label'], [
                    'harvested_area_ha' => $area, 'gross_weight_ton' => $batch['estimated_weight_ton'],
                    'color' => $site['commodity'] === 'Rumput' ? 'Hijau segar' : 'Kuning keemasan',
                    'ripeness' => $site['commodity'] === 'Rumput' ? 'Siap potong' : 'Matang panen', 'cleanliness' => 'Bersih setelah sortasi',
                ], $actor, $performer, $team);
            if ($cycle->fresh()->status === 'active') {
                Gate::forUser($actor)->authorize('configure', $cycle);
                app(HarvestClosureService::class)->close($actor, $team, $cycle);
            }
        }
    }

    /** @param array<string, mixed> $site */
    private function blockPreparation(Farm $farm, array $site, User $actor, User $performer, Team $team): void
    {
        if (! is_array($site['batches'] ?? null)) {
            throw new LogicException('Data batch portofolio tidak valid.');
        }
        $byBlock = collect($site['batches'])->groupBy('block_code');
        foreach ($byBlock as $blockCode => $batches) {
            $block = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('code', $blockCode)->firstOrFail();
            $reference = $batches->sortBy('planned_planting_at')->first();
            $date = CarbonImmutable::parse($reference['planned_planting_at'], 'Asia/Jakarta');
            $reports = [
                ['land_cleaning', $date->subDays(15), 'landCleaningDetail', ['area_ha' => $block->area_ha]],
                ['soil_preparation', $date->subDays(12), null, ['area_ha' => $block->area_ha]],
            ];
            if ($batches->contains(fn ($batch) => $batch['status'] !== 'planned' || $batch['current_stage'] === 'basic_fertilizer')) {
                $reports[] = ['base_fertilization', $date->subDays(7), 'fertilizationDetail', [
                    'fertilization_stage' => 'kandang', 'area_ha' => $block->area_ha,
                ]];
            }
            foreach ($reports as [$code, $when, $relation, $detail]) {
                $this->blockActivity($farm, $site['key'], $block, $code, $when, $relation, $detail, $actor, $performer, $team);
            }
        }
    }

    /** @param array<string, mixed> $detail */
    private function blockActivity(Farm $farm, string $siteKey, FarmBlock $block, string $code, CarbonImmutable $when, ?string $relation, array $detail, User $actor, User $performer, Team $team): void
    {
        $type = ActivityType::visibleTo($actor, $team)->where('code', $code)->firstOrFail();
        $attributes = [
            'farm_id' => $farm->id, 'block_id' => $block->id, 'activity_type_id' => $type->id,
            'performed_by' => $performer->id, 'activity_date' => $this->at($when->toDateString()),
            'description' => $type->name.' '.$block->code, 'status' => 'completed',
        ];
        if ($relation === null) {
            $attributes['task_details'] = json_encode($detail, JSON_THROW_ON_ERROR);
        }
        $activity = $this->record(FarmActivity::class, DemoOwnerPortfolioData::id($team, 'block-activity:'.$siteKey.':'.$block->code.':'.$code), $attributes, $farm, $actor, $team);
        $created = $activity->wasRecentlyCreated;
        $this->linkDailyLog($activity, $farm, $actor, $team, $created);
        if ($created) {
            if ($relation !== null) {
                $activity->{$relation}()->create($detail);
            }
            app(PlantingCycleStageService::class)->record($actor, $team, $activity, $type);
        }
    }

    /** @param array<string, mixed> $detail */
    private function activity(Farm $farm, PlantingCycle $cycle, string $key, string $code, string $identity, string $date, string $description, array $detail, User $actor, User $performer, Team $team): FarmActivity
    {
        Gate::forUser($actor)->authorize('viewAny', [ActivityType::class, $team]);
        $type = ActivityType::visibleTo($actor, $team)->where('code', $code)->first();
        if ($type === null) {
            Gate::forUser($actor)->authorize('create', [ActivityType::class, $team]);
            $type = ActivityType::create(['code' => $code, 'name' => match ($code) {
                'planting' => 'Penanaman', 'maintenance' => 'Perawatan', 'harvest' => 'Panen',
                default => throw new LogicException('Jenis aktivitas portofolio tidak didukung: '.$code),
            }]);
        }
        $activityId = DemoOwnerPortfolioData::id($team, 'activity:'.$key.':'.$identity);
        $taskData = [];
        Gate::forUser($actor)->authorize('viewAny', [FarmActivity::class, $team]);
        if ($code === 'harvest' && ! FarmActivity::visibleTo($actor, $team)->whereKey($activityId)->exists()) {
            Gate::forUser($actor)->authorize('viewAny', [PlantingCycleTask::class, $team]);
            $task = PlantingCycleTask::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
                ->where('activity_category', 'harvest')->orderBy('scheduled_date')->orderBy('id')->firstOrFail();
            $taskData = [
                'planting_cycle_task_id' => $task->id,
                'task_details' => json_encode([
                    'area_ha' => $detail['harvested_area_ha'], 'weight_ton' => $detail['gross_weight_ton'],
                ], JSON_THROW_ON_ERROR),
            ];
        } elseif ($code === 'maintenance' && $identity !== 'growth_check') {
            $taskData = [
                'task_details' => json_encode($detail, JSON_THROW_ON_ERROR),
            ];
        }
        $activity = $this->record(FarmActivity::class, $activityId, [
            'farm_id' => $farm->id, 'planting_cycle_id' => $cycle->id, 'block_id' => $cycle->block_id, 'activity_type_id' => $type->id,
            'performed_by' => $performer->id, 'activity_date' => $this->at($date),
            'description' => $description, 'status' => 'completed', 'created_at' => $this->at($date), 'updated_at' => $this->at($date), ...$taskData,
        ], $farm, $actor, $team);
        $created = $activity->wasRecentlyCreated;
        $this->linkDailyLog($activity, $farm, $actor, $team, $created);
        if ($created) {
            if ($code === 'maintenance' && $identity !== 'growth_check') {
                app(PlantingCycleStageService::class)->record($actor, $team, $activity, $type);

                return $activity;
            }
            $model = match (true) {
                $code === 'planting' => PlantingDetail::class,
                $code === 'harvest' => HarvestDetail::class,
                $identity === 'growth_check' => PlantObservation::class,
                default => throw new LogicException('Jenis detail portofolio tidak didukung: '.$code),
            };
            Gate::forUser($actor)->authorize('create', [$model, $identity === 'growth_check' ? $cycle : $activity]);
            $payload = ['activity_id' => $activity->id, ...$detail];
            if ($identity === 'growth_check') {
                $payload += ['id' => DemoOwnerPortfolioData::id($team, 'observation:'.$key),
                    'planting_cycle_id' => $cycle->id, 'observed_by' => $performer->id, 'observed_at' => $this->at($date)];
            }
            (new $model)->forceFill($payload)->save();
            app(PlantingCycleStageService::class)->record($actor, $team, $activity, $type);
        }

        return $activity;
    }

    private function linkDailyLog(FarmActivity $activity, Farm $farm, User $actor, Team $team, bool $created): void
    {
        if ($activity->daily_activity_log_id !== null) {
            return;
        }

        // The caller holds the farm lock for the surrounding portfolio transaction.
        // Only deterministic portfolio activities reach this method; retain any
        // existing report details, user edits and shared daily header unchanged.
        Gate::forUser($actor)->authorize('update', $activity);
        Gate::forUser($actor)->authorize('viewAny', [DailyActivityLog::class, $team]);
        Gate::forUser($actor)->authorize('create', [DailyActivityLog::class, $farm]);
        $headers = app(DailyActivityHeaderService::class);
        $date = $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString();
        if (! $created && $headers->forDate($farm, $actor, $date) === null) {
            // Existing unlinked reports stay unlinked instead of inventing a daily log.
            return;
        }
        $log = $headers->resolve($farm, $actor, $date);
        $activity->forceFill([
            'daily_activity_log_id' => $log->id,
            'position' => $headers->nextPosition($log, $actor),
        ])->save();
    }

    private function weather(Farm $farm, string $key, User $actor, Team $team): void
    {
        [$condition, $temperature, $humidity] = match ($key) {
            'sukamaju' => ['Cerah berawan', 27.5, 76], 'cibogo' => ['Cerah', 28.2, 71],
            'mekarsari' => ['Hujan ringan', 25.8, 84], 'cikeas' => ['Berawan', 27.1, 79], default => ['Berkabut tipis', 22.4, 86],
        };
        $this->record(WeatherRecord::class, DemoOwnerPortfolioData::id($team, 'weather:'.$key), [
            'farm_id' => $farm->id, 'recorded_at' => $this->at(DemoOwnerPortfolioData::SNAPSHOT_DATE),
            'condition' => $condition, 'temperature_c' => $temperature, 'humidity_percentage' => $humidity,
            'data_source' => 'Stasiun contoh portofolio',
        ], $farm, $actor, $team);
    }

    /** @template TModel of FarmerModel
     *
     * @param  class-string<TModel>  $class
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    private function record(string $class, string $id, array $data, Model $parent, User $actor, Team $team): FarmerModel
    {
        Gate::forUser($actor)->authorize('viewAny', [$class, $team]);
        $existing = $class::visibleTo($actor, $team)->whereKey($id)->first();
        if ($existing !== null) {
            if (! $existing instanceof $class) {
                throw new LogicException('Jenis data portofolio tidak sesuai dengan model yang diminta.');
            }
            foreach (['farm_id', 'block_id', 'planting_cycle_id', 'activity_id', 'observation_id'] as $foreignKey) {
                if (isset($data[$foreignKey]) && (string) $existing->getAttribute($foreignKey) !== (string) $data[$foreignKey]) {
                    throw new RuntimeException('ID portofolio sudah digunakan oleh relasi lain.');
                }
            }

            return $existing;
        }
        Gate::forUser($actor)->authorize('create', [$class, $parent]);
        $record = new $class;
        $record->forceFill(['id' => $id, ...$data])->save();

        return $record;
    }

    private function at(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date.' 09:00:00', 'Asia/Jakarta');
    }
}

<?php

namespace Database\Seeders;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmerModel;
use App\Models\FarmRisk;
use App\Models\FertilizationDetail;
use App\Models\HarvestDetail;
use App\Models\IrrigationDetail;
use App\Models\PesticideDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingDetail;
use App\Models\PlantObservation;
use App\Models\Team;
use App\Models\User;
use App\Models\WeatherRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;
use RuntimeException;

/** Additive, fixed-date operational detail for the optional Owner portfolio. */
class DemoOwnerPortfolioActivitySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Portofolio hanya dapat diisi pada environment local atau testing.');
        }
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
        $seedRate = match ($site['key']) {
            'sukamaju' => 25, 'cibogo' => 45, 'cikeas' => 20, 'mekarsari' => 0.075, default => 0.06,
        };
        $this->activity($farm, $cycle, $key, 'planting', $planted->toDateString(),
            'Penanaman '.$site['commodity'].' '.$batch['subblock_label'], [
                'seed_name' => $site['commodity'].' '.$site['variety'], 'quantity_kg' => $area * $seedRate,
                'planting_pattern' => $site['key'] === 'sukamaju' ? 'Jajar legowo 2:1' : 'Barisan teratur mengikuti bedengan', 'area_ha' => $area,
            ], $actor, $performer, $team);
        $fertilized = $site['key'] === 'cibogo' ? '2026-11-04' : $planted->addDays(14)->toDateString();
        $this->activity($farm, $cycle, $key, 'fertilization', $fertilized,
            'Pemupukan susulan '.$site['commodity'].' '.$batch['subblock_label'], [
                'fertilization_stage' => 'Susulan vegetatif', 'fertilizer_type' => 'NPK 15-15-15',
                'brand_name' => 'NPK contoh portofolio', 'subsidy_status' => 'non_subsidized',
                'application_method' => 'Aplikasi larikan di sisi barisan', 'quantity_kg' => $area * 150,
            ], $actor, $performer, $team);
        if ($batch['status'] === 'active') {
            $this->activity($farm, $cycle, $key, 'irrigation', '2026-11-03',
                'Pengairan '.$site['commodity'].' '.$batch['subblock_label'], [
                    'water_source' => $site['key'] === 'sukamaju' ? 'Saluran irigasi sawah' : 'Embung kebun',
                    'irrigation_method' => $site['key'] === 'mekarsari' ? 'Irigasi tetes' : 'Pengairan saluran terkontrol',
                    'machine_type' => 'Pompa listrik', 'duration_hours' => $site['key'] === 'sukamaju' ? 3 : 1.5,
                ], $actor, $performer, $team);
        }
        if ($site['key'] === 'mekarsari') {
            $this->activity($farm, $cycle, $key, 'pesticide', '2026-11-04',
                'Pengendalian kutu daun cabai '.$batch['subblock_label'], [
                    'pesticide_category' => 'Biologis', 'purpose' => 'Pengendalian kutu daun',
                    'brand_name' => 'Agens hayati contoh', 'active_ingredient' => 'Beauveria bassiana',
                    'dosage_per_tank' => 'Catatan aplikasi contoh, bukan rekomendasi dosis', 'tank_size_liters' => 16, 'tank_count' => 4,
                ], $actor, $performer, $team);
        }
        $observed = $batch['status'] === 'harvested'
            ? CarbonImmutable::parse($batch['harvested_at'], 'Asia/Jakarta')->subDay()->toDateString()
            : match ($site['key']) {
                'cibogo' => '2026-11-03', 'cikeas' => '2026-11-04', default => '2026-11-05'
            };
        $observation = $this->activity($farm, $cycle, $key, 'growth_check', $observed,
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
            $this->activity($farm, $cycle, $key, 'harvest', $batch['harvested_at'],
                'Panen '.$site['commodity'].' '.$batch['subblock_label'], [
                    'harvested_area_ha' => $area, 'gross_weight_ton' => $batch['estimated_weight_ton'],
                    'moisture_percentage' => $site['key'] === 'sukamaju' ? 22 : null,
                    'color' => $site['key'] === 'sukamaju' ? 'Kuning keemasan' : 'Merah merata',
                    'ripeness' => 'Matang panen', 'cleanliness' => 'Bersih setelah sortasi',
                ], $actor, $performer, $team);
        }
    }

    /** @param array<string, mixed> $detail */
    private function activity(Farm $farm, PlantingCycle $cycle, string $key, string $code, string $date, string $description, array $detail, User $actor, User $performer, Team $team): FarmActivity
    {
        Gate::forUser($actor)->authorize('viewAny', [ActivityType::class, $team]);
        $type = ActivityType::visibleTo($actor, $team)->where('code', $code)->first();
        if ($type === null) {
            Gate::forUser($actor)->authorize('create', [ActivityType::class, $team]);
            $type = ActivityType::create(['code' => $code, 'name' => match ($code) {
                'planting' => 'Penanaman', 'fertilization' => 'Pemupukan', 'irrigation' => 'Irigasi',
                'growth_check' => 'Pengecekan Pertumbuhan', 'pesticide' => 'Pengendalian Hama', 'harvest' => 'Panen',
                default => throw new LogicException('Jenis aktivitas portofolio tidak didukung: '.$code),
            }]);
        }
        /** @var FarmActivity $activity */
        $activity = $this->record(FarmActivity::class, DemoOwnerPortfolioData::id($team, 'activity:'.$key.':'.$code), [
            'farm_id' => $farm->id, 'planting_cycle_id' => $cycle->id, 'activity_type_id' => $type->id,
            'performed_by' => $performer->id, 'activity_date' => $this->at($date),
            'description' => $description, 'status' => 'completed', 'created_at' => $this->at($date), 'updated_at' => $this->at($date),
        ], $farm, $actor, $team);
        if ($activity->wasRecentlyCreated) {
            $model = match ($code) {
                'planting' => PlantingDetail::class, 'fertilization' => FertilizationDetail::class,
                'irrigation' => IrrigationDetail::class, 'pesticide' => PesticideDetail::class,
                'harvest' => HarvestDetail::class, 'growth_check' => PlantObservation::class,
                default => throw new LogicException('Jenis detail portofolio tidak didukung: '.$code),
            };
            Gate::forUser($actor)->authorize('create', [$model, $code === 'growth_check' ? $cycle : $activity]);
            $payload = ['activity_id' => $activity->id, ...$detail];
            if ($code === 'growth_check') {
                $payload += ['id' => DemoOwnerPortfolioData::id($team, 'observation:'.$key),
                    'planting_cycle_id' => $cycle->id, 'observed_by' => $performer->id, 'observed_at' => $this->at($date)];
            }
            (new $model)->forceFill($payload)->save();
        }

        return $activity;
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

    /** @param class-string<FarmerModel> $class
     * @param  array<string, mixed>  $data
     */
    private function record(string $class, string $id, array $data, Model $parent, User $actor, Team $team): FarmerModel
    {
        Gate::forUser($actor)->authorize('viewAny', [$class, $team]);
        $existing = $class::visibleTo($actor, $team)->whereKey($id)->first();
        if ($existing !== null) {
            foreach (['farm_id', 'planting_cycle_id', 'activity_id', 'observation_id'] as $foreignKey) {
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

<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantingCycleTask;
use App\Models\SopTemplate;
use App\Models\SopTemplateTask;
use App\Models\SopTemplateVersion;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\PlantingCycleTaskService;
use App\Services\SopTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/** Additive Owner scenario included in DatabaseSeeder; also runnable independently. */
class DemoOwnerPortfolioSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Portofolio Owner hanya dapat diisi pada environment local atau testing.');
        }
        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])
            ->where('is_active', true)->whereHas('role', fn ($roles) => $roles->where('name', 'superadmin'))->first();
        $team = $actor?->teams()->where('slug', 'farm-ic-demo')->where('is_personal', false)->first();
        if (! $actor instanceof User || ! $team instanceof Team) {
            throw new RuntimeException('Identitas Superadmin dan tenant farm-ic-demo belum tersedia. Jalankan seed dasar terlebih dahulu.');
        }
        Gate::forUser($actor)->authorize('view', $team);
        Gate::forUser($actor)->authorize('viewAny', [User::class, $team]);
        $owner = User::visibleTo($actor, $team)->where('email', DemoIdentitySeeder::ACCOUNTS['owner']['email'])
            ->where('is_active', true)->whereHas('role', fn ($roles) => $roles->where('name', 'owner'))
            ->whereHas('teams', fn ($teams) => $teams->whereKey($team->id))->first();
        if (! $owner instanceof User) {
            throw new RuntimeException('Owner harus sudah menjadi anggota tenant farm-ic-demo. Identitas tidak diubah oleh seeder portofolio.');
        }

        DB::transaction(function () use ($actor, $owner, $team): void {
            Team::visibleTo($actor, $team)->whereKey($team->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('viewAny', [Farm::class, $team]);
            foreach (DemoOwnerPortfolioData::sites() as $site) {
                $id = DemoOwnerPortfolioData::id($team, 'farm:'.$site['key']);
                $existing = Farm::visibleTo($actor, $team)->where('team_id', $team->id)->find($id);
                if ($existing instanceof Farm) {
                    // Stable IDs make renamed/edited presentation records recognizable.
                    // Do not reset their fields, stages, task dates or latest user edits.
                    continue;
                }
                $this->assertUnusedId(Farm::class, $id);
                $commodity = $this->commodity($actor, $team, $site);
                $variety = $this->variety($actor, $team, $commodity, $site);
                $template = $this->template($actor, $team, $commodity, $site);
                Gate::forUser($actor)->authorize('create', [Farm::class, $team]);
                // FarmService allocates random UUIDs; fixture identity is deliberate here.
                $farm = new Farm;
                $farm->forceFill([
                    'id' => $id, 'team_id' => $team->id, 'owner_id' => $owner->id, 'created_by' => $actor->id,
                    'name' => $site['name'], 'land_tenure' => $site['land_tenure'], 'address' => $site['address'],
                    'city' => $site['city'], 'district' => $site['district'], 'province' => $site['province'],
                    'total_area_ha' => $site['area_ha'], 'latitude' => $site['latitude'], 'longitude' => $site['longitude'],
                    'commodity_id' => $commodity->id, 'target_volume_ton' => $site['target_volume_ton'],
                    'default_sop_template_id' => $template->id, 'image_url' => '/images/kebun-placeholder.svg',
                ])->save();
                $this->blocksAndBatches($actor, $team, $farm, $variety, $site);
            }
            $this->call(DemoOwnerPortfolioActivitySeeder::class);
        });
    }

    /** @param array<string, mixed> $site */
    private function commodity(User $actor, Team $team, array $site): Commodity
    {
        Gate::forUser($actor)->authorize('viewAny', [Commodity::class, $team]);
        $existing = Commodity::visibleTo($actor, $team)->where('name', $site['commodity'])->first();
        if ($existing instanceof Commodity) {
            return $existing;
        }
        $id = DemoOwnerPortfolioData::id($team, 'commodity:'.$site['key']);
        $this->assertUnusedId(Commodity::class, $id);
        Gate::forUser($actor)->authorize('create', [Commodity::class, $team]);
        $commodity = new Commodity;
        $commodity->forceFill(['id' => $id, 'name' => $site['commodity'], 'description' => 'Komoditas contoh pada skenario presentasi portofolio Owner.'])->save();

        return $commodity;
    }

    /** @param array<string, mixed> $site */
    private function variety(User $actor, Team $team, Commodity $commodity, array $site): CropVariety
    {
        Gate::forUser($actor)->authorize('viewAny', [CropVariety::class, $team]);
        $existing = CropVariety::visibleTo($actor, $team)->where('commodity_id', $commodity->id)->where('name', $site['variety'])->first();
        if ($existing instanceof CropVariety) {
            return $existing;
        }
        $id = DemoOwnerPortfolioData::id($team, 'variety:'.$site['key']);
        $this->assertUnusedId(CropVariety::class, $id);
        Gate::forUser($actor)->authorize('create', [CropVariety::class, $commodity]);
        $variety = new CropVariety;
        $variety->forceFill(['id' => $id, 'commodity_id' => $commodity->id, 'name' => $site['variety'], 'description' => 'Varietas contoh untuk demonstrasi riwayat tanam dan monitoring.'])->save();

        return $variety;
    }

    /** @param array<string, mixed> $site */
    private function template(User $actor, Team $team, Commodity $commodity, array $site): SopTemplate
    {
        $id = DemoOwnerPortfolioData::id($team, 'sop:'.$site['key']);
        Gate::forUser($actor)->authorize('viewAny', [SopTemplate::class, $team]);
        $existing = SopTemplate::visibleTo($actor, $team)->where('team_id', $team->id)->where('commodity_id', $commodity->id)->find($id);
        if ($existing instanceof SopTemplate) {
            return $existing;
        }
        $this->assertUnusedId(SopTemplate::class, $id);
        Gate::forUser($actor)->authorize('create', [SopTemplate::class, $team]);
        $template = new SopTemplate;
        $template->forceFill([
            'id' => $id, 'team_id' => $team->id, 'commodity_id' => $commodity->id,
            'name' => 'SOP Presentasi '.$site['commodity'], 'is_active' => true, 'created_by' => $actor->id,
            'description' => 'Contoh alur budidaya '.$site['commodity'].' untuk presentasi. Jadwal operasional nyata mengikuti SOP kebun yang disetujui.',
        ])->save();
        Gate::forUser($actor)->authorize('create', [SopTemplateVersion::class, $template]);
        $version = $template->versions()->create(['version' => 1, 'status' => 'draft', 'notes' => 'Snapshot presentasi 5 November 2026.', 'created_by' => $actor->id]);
        $tasks = [
            // Planting is recorded through the planting activity, not authored
            // as an SOP step (see 2026_11_03_000014).
            ['maintenance', 'irrigation', 'Pemeriksaan pengairan '.$site['commodity'], 7, 'Periksa saluran dan kelembapan tanah; catat kondisi pengairan pada laporan lapangan.'],
            ['maintenance', 'weeding', 'Penyiangan area tanam '.$site['commodity'], 14, 'Bersihkan gulma pada area tanam tanpa merusak perakaran; dokumentasikan area yang ditangani.'],
            ['maintenance', 'growth_check', 'Pengamatan pertumbuhan '.$site['commodity'], 21, 'Catat fase tumbuh, tinggi sampel dan kondisi kesehatan tanaman.'],
            ['maintenance', 'follow_up_fertilization', 'Pemupukan susulan '.$site['commodity'], 28, 'Ikuti dosis yang disetujui pengelola kebun; catat jenis, jumlah dan metode aplikasi.'],
            ['maintenance', 'pest_control', 'Pemantauan hama '.$site['commodity'], 35, 'Lakukan pengamatan hama dan penyakit, lalu catat hasil serta tindak lanjut yang disetujui.'],
            ['harvest', null, 'Pencatatan panen '.$site['commodity'], $site['harvest_offset'], 'Periksa kesiapan panen dan catat luas, berat serta kualitas hasil panen.'],
        ];
        foreach ($tasks as [$category, $subcategory, $name, $offset, $instructions]) {
            Gate::forUser($actor)->authorize('create', [SopTemplateTask::class, $version]);
            app(SopTemplateService::class)->createTask($version, [
                'activity_category' => $category, 'activity_subcategory' => $subcategory, 'name' => $name,
                'offset_days' => $offset, 'tolerance_days' => 2, 'is_required' => true, 'instructions' => $instructions,
            ]);
        }
        Gate::forUser($actor)->authorize('update', $version);
        app(SopTemplateService::class)->publish($version, $actor);

        return $template;
    }

    /** @param array<string, mixed> $site */
    private function blocksAndBatches(User $actor, Team $team, Farm $farm, CropVariety $variety, array $site): void
    {
        $blocks = [];
        foreach ($site['blocks'] as $data) {
            Gate::forUser($actor)->authorize('create', [FarmBlock::class, $farm]);
            $block = app(FarmBlockService::class)->create($farm, $actor, $team, [
                ...$data, 'description' => 'Blok fisik pada skenario portofolio '.$site['name'].', snapshot 5 November 2026.', 'is_active' => true,
            ]);
            $batchData = null;
            foreach ($site['batches'] as $candidate) {
                if ($candidate['block_code'] === $data['code']) {
                    $batchData = $candidate;
                    break;
                }
            }
            $clearing = $batchData === null
                ? ['completed', '2026-08-01 07:00:00+07', '2026-08-03 16:00:00+07']
                : $this->stageDates($batchData)['land_clearing'];
            Gate::forUser($actor)->authorize('update', $block);
            $block->forceFill(['land_clearing_started_at' => $clearing[1], 'land_clearing_completed_at' => $clearing[2]])->save();
            $blocks[$data['code']] = $block;
        }
        foreach ($site['batches'] as $data) {
            $block = $blocks[$data['block_code']];
            Gate::forUser($actor)->authorize('create', [PlantingCycle::class, $block]);
            $batch = app(FarmBlockService::class)->createBatch($block, $actor, $team, [
                'reserved_area_ha' => $data['reserved_area_ha'], 'subblock_label' => $data['subblock_label'],
                'planned_planting_at' => $data['planned_planting_at'],
            ]);
            Gate::forUser($actor)->authorize('update', $batch);
            // Generate historical tasks while the batch is still active. The
            // task service deliberately refuses to build tasks for closed cycles.
            $generationStatus = $data['status'] === 'harvested' ? 'active' : $data['status'];
            $batch->forceFill([
                'status' => $generationStatus, 'variety_id' => $data['status'] === 'planned' ? null : $variety->id,
                'planted_at' => $data['planted_at'], 'planted_area_ha' => $data['planted_area_ha'],
                'estimated_harvest_at' => $data['estimated_harvest_at'], 'estimated_weight_ton' => $data['estimated_weight_ton'],
            ])->save();
            $dates = $this->stageDates($data);
            foreach (PlantingCycleStage::visibleTo($actor, $team)->where('planting_cycle_id', $batch->id)->orderBy('position')->get() as $stage) {
                Gate::forUser($actor)->authorize('update', $stage);
                [$status, $started, $completed] = $dates[$stage->getAttribute('code')];
                $stage->forceFill(['status' => $status, 'started_at' => $started, 'completed_at' => $completed])->save();
            }
            Gate::forUser($actor)->authorize('create', [PlantingCycleTask::class, $batch]);
            app(PlantingCycleTaskService::class)->generateForSubblok($batch, $actor);
            if ($data['status'] === 'harvested') {
                $batch->forceFill(['status' => 'harvested'])->save();
            }
            $this->taskStatuses($actor, $team, $batch, $data['status']);
        }
    }

    /** @param array<string, mixed> $batch
     * @return array<string, array{string, string|null, string|null}>
     */
    private function stageDates(array $batch): array
    {
        if ($batch['status'] === 'planned') {
            $basic = $batch['current_stage'] === 'basic_fertilizer';

            return [
                'land_clearing' => ['completed', '2026-10-20 07:00:00+07', '2026-10-22 16:00:00+07'],
                'soil_preparation' => [$basic ? 'completed' : 'in_progress', '2026-10-23 07:00:00+07', $basic ? '2026-10-28 16:00:00+07' : null],
                'basic_fertilizer' => [$basic ? 'in_progress' : 'upcoming', $basic ? '2026-10-29 07:00:00+07' : null, null],
                'planting' => ['upcoming', null, null], 'maintenance' => ['upcoming', null, null], 'harvest' => ['upcoming', null, null],
            ];
        }
        $planted = CarbonImmutable::parse($batch['planted_at'], 'Asia/Jakarta');
        $stamp = fn (int $offset, string $time): string => $planted->addDays($offset)->format('Y-m-d').' '.$time.'+07';
        $harvested = $batch['harvested_at'];

        return [
            'land_clearing' => ['completed', $stamp(-15, '07:00:00'), $stamp(-13, '16:00:00')],
            'soil_preparation' => ['completed', $stamp(-12, '07:00:00'), $stamp(-8, '16:00:00')],
            'basic_fertilizer' => ['completed', $stamp(-7, '07:00:00'), $stamp(-2, '16:00:00')],
            'planting' => ['completed', $stamp(0, '07:00:00'), $stamp(0, '16:00:00')],
            'maintenance' => [$harvested === null ? 'in_progress' : 'completed', $stamp(1, '07:00:00'), $harvested === null ? null : CarbonImmutable::parse($harvested)->subDay()->format('Y-m-d').' 16:00:00+07'],
            'harvest' => [$harvested === null ? 'upcoming' : 'completed', $harvested === null ? null : $harvested.' 07:00:00+07', $harvested === null ? null : $harvested.' 16:00:00+07'],
        ];
    }

    private function taskStatuses(User $actor, Team $team, PlantingCycle $batch, string $batchStatus): void
    {
        foreach (PlantingCycleTask::visibleTo($actor, $team)->where('planting_cycle_id', $batch->id)->get() as $task) {
            $date = $task->scheduled_date?->toDateString();
            $completed = $batchStatus === 'harvested' || ($batchStatus === 'active' && $date !== null && $date < DemoOwnerPortfolioData::SNAPSHOT_DATE);
            $inProgress = $batchStatus === 'active' && $date === DemoOwnerPortfolioData::SNAPSHOT_DATE;
            Gate::forUser($actor)->authorize('update', $task);
            $task->forceFill([
                'status' => $completed ? 'completed' : ($inProgress ? 'in_progress' : 'pending'),
                'completed_at' => $completed && $date !== null ? $date.' 16:00:00+07' : null,
            ])->save();
        }
    }

    /** @param class-string<Model> $model */
    private function assertUnusedId(string $model, string $id): void
    {
        // Integrity collision detection deliberately does not expose or reuse a
        // row hidden by the trusted tenant/parent query above.
        if ($model::query()->whereKey($id)->exists()) {
            throw new RuntimeException('ID fixture portofolio sudah digunakan di luar parent fixture yang diharapkan. Tidak ada data yang dipindahkan atau ditimpa.');
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Role;
use App\Models\SopTemplate;
use App\Models\SopTemplateTask;
use App\Models\SopTemplateVersion;
use App\Models\Team;
use App\Models\User;
use App\Services\Activity\HarvestClosureService;
use App\Services\Activity\PlantingActivityService;
use App\Services\Activity\PlantingCycleStageService;
use App\Services\Activity\TaskActivityService;
use App\Services\FarmBlockService;
use App\Services\PlantingCycleTaskManagementService;
use App\Services\SopTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/** One-time, additive local-demo import. Existing fixture and user work is never overwritten. */
class CariuBaruSeeder extends Seeder
{
    public const EMAIL = 'petugas.cariu@farmer-ic.test';

    public const PASSWORD = 'CariuDemo123!';

    private const MARKER = 'SIMULASI CARIU BARU';

    public static function farmId(Team $team): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'farmer-ic:cariu-baru:v1:'.$team->id)->toString();
    }

    public function run(): void
    {
        $this->guardDatabase();
        $rows = CariuBaruLogbook::rows();
        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])
            ->where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', 'superadmin'))->first();
        $team = $actor?->teams()->where('slug', 'farm-ic-demo')->where('is_personal', false)->first();
        if (! $actor instanceof User || ! $team instanceof Team) {
            throw new RuntimeException('Superadmin dan tenant farm-ic-demo harus tersedia sebelum impor Cariu.');
        }
        Gate::forUser($actor)->authorize('view', $team);

        DB::transaction(function () use ($actor, $team, $rows): void {
            Team::visibleTo($actor, $team)->whereKey($team->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('viewAny', [Farm::class, $team]);
            $id = self::farmId($team);
            $existing = Farm::visibleTo($actor, $team)->whereKey($id)->first();
            if ($existing !== null) {
                if ((int) $existing->team_id !== (int) $team->id
                    || ! User::withTrashed()->where('email', self::EMAIL)->where('assigned_farm_id', $id)->exists()) {
                    throw new RuntimeException('ID kebun tidak dapat dibuktikan sebagai fixture Cariu yang utuh; impor dibatalkan.');
                }

                return; // Preserve all subsequent edits and history, including renamed fixtures.
            }
            if (Farm::query()->whereKey($id)->exists()
                || Farm::visibleTo($actor, $team)->where('team_id', $team->id)->where('name', 'Kebun Cariu Baru')->exists()
                || User::withTrashed()->where('email', self::EMAIL)->exists()) {
                throw new RuntimeException('Nama, ID kebun, atau email Petugas sudah digunakan; tidak ada record yang ditimpa.');
            }

            Gate::forUser($actor)->authorize('viewAny', [User::class, $team]);
            $owner = User::visibleTo($actor, $team)->where('email', DemoIdentitySeeder::ACCOUNTS['owner']['email'])
                ->where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', 'owner'))
                ->whereHas('teams', fn ($q) => $q->whereKey($team->id))->firstOrFail();
            Gate::forUser($actor)->authorize('viewAny', [Commodity::class, $team]);
            $commodity = Commodity::visibleTo($actor, $team)->where('name', 'Jagung')->firstOrFail();
            $template = $this->template($actor, $team, $commodity);
            $variety = $this->variety($actor, $team, $commodity);

            Gate::forUser($actor)->authorize('create', [Farm::class, $team]);
            $farm = new Farm;
            $farm->forceFill([
                'id' => $id, 'team_id' => $team->id, 'owner_id' => $owner->id, 'created_by' => $actor->id,
                'name' => 'Kebun Cariu Baru', 'total_area_ha' => '1.3900', 'commodity_id' => $commodity->id,
                'default_sop_template_id' => $template->id, 'city' => 'Kabupaten Bogor', 'district' => 'Cariu',
                'province' => 'Jawa Barat', 'address' => 'Lahan PM, Cariu — luas poligon sementara; subblok dan riwayat lanjutan SIMULASI.',
                'image_url' => '/images/kebun-placeholder.svg',
            ])->save();
            $performer = $this->performer($actor, $team, $farm);
            Gate::forUser($actor)->authorize('create', [FarmBlock::class, $farm]);
            $block = app(FarmBlockService::class)->create($farm, $actor, $team, [
                'code' => 'PM-DEMO', 'name' => 'Lahan PM — blok agregat SIMULASI', 'area_ha' => '1.3900',
                'description' => 'Blok agregat untuk skenario; bukan batas 13 atau 6 blok asli yang disebutkan pada logbook.', 'is_active' => true,
            ]);
            $cycles = $this->cycles($actor, $team, $block);
            $this->sourceReports($actor, $performer, $team, $farm, $block, $cycles, $variety, $rows);
            $this->simulatedTasks($actor, $performer, $team, $farm, $cycles);
        });
    }

    /** Never seed an arbitrary database just because APP_ENV was set to local. */
    public static function supportsCurrentDatabase(): bool
    {
        if (! app()->environment('local', 'testing')) {
            return false;
        }
        $connection = DB::connection();
        $name = (string) $connection->getDatabaseName();
        $local = app()->environment('local') && $connection->getConfig('host') === '127.0.0.1' && $name === 'farmer_ic_dev';
        $testing = app()->environment('testing') && config('database.default') === 'pgsql_testing'
            && preg_match('/^farmer_ic_(?:crud|schema)_test_[a-z0-9_]+$/', $name) === 1;

        return $connection->getDriverName() === 'pgsql' && ($local || $testing)
            && $connection->scalar('SELECT current_database()') === $name;
    }

    private function guardDatabase(): void
    {
        if (! self::supportsCurrentDatabase()) {
            throw new RuntimeException('Impor Cariu hanya diizinkan pada database demo lokal atau database test PostgreSQL terisolasi.');
        }
    }

    private function template(User $actor, Team $team, Commodity $commodity): SopTemplate
    {
        Gate::forUser($actor)->authorize('create', [SopTemplate::class, $team]);
        $template = SopTemplate::create([
            'team_id' => $team->id, 'commodity_id' => $commodity->id, 'created_by' => $actor->id,
            'name' => 'SIMULASI Cariu Baru — SOP Jagung', 'description' => 'Skenario demo, bukan SOP lapangan yang terverifikasi.', 'is_active' => true,
        ]);
        Gate::forUser($actor)->authorize('create', [SopTemplateVersion::class, $template]);
        $version = $template->versions()->create(['version' => 1, 'status' => 'draft', 'notes' => 'SIMULASI; spreadsheet tidak memuat rincian SOP.', 'created_by' => $actor->id]);
        foreach ([
            ['irrigation', 'Pengairan', 7], ['weeding', 'Penyiangan', 14], ['growth_check', 'Pengamatan vegetatif', 21],
            ['follow_up_fertilization', 'Pemupukan susulan', 30], ['growth_check', 'Pengamatan pemasakan', 60],
            [null, 'Panen', 90],
        ] as [$subcategory, $name, $offset]) {
            Gate::forUser($actor)->authorize('create', [SopTemplateTask::class, $version]);
            app(SopTemplateService::class)->createTask($version, [
                'activity_category' => $subcategory === null ? 'harvest' : 'maintenance', 'activity_subcategory' => $subcategory,
                'name' => 'SIMULASI — '.$name, 'offset_days' => $offset, 'tolerance_days' => 2, 'is_required' => true,
                'instructions' => 'Skenario demonstrasi; bukan catatan atau rekomendasi budidaya aktual.',
            ]);
        }
        Gate::forUser($actor)->authorize('update', $version);
        app(SopTemplateService::class)->publish($version, $actor);

        return $template;
    }

    private function variety(User $actor, Team $team, Commodity $commodity): CropVariety
    {
        Gate::forUser($actor)->authorize('viewAny', [CropVariety::class, $team]);
        $name = 'SIMULASI Cariu — varietas tidak diketahui';
        if (CropVariety::visibleTo($actor, $team)->where('commodity_id', $commodity->id)->where('name', $name)->exists()) {
            throw new RuntimeException('Varietas simulasi sudah digunakan; impor dibatalkan tanpa mengubahnya.');
        }
        Gate::forUser($actor)->authorize('create', [CropVariety::class, $commodity]);

        return CropVariety::create(['commodity_id' => $commodity->id, 'name' => $name, 'description' => 'Varietas asli tidak tercantum di spreadsheet.']);
    }

    private function performer(User $actor, Team $team, Farm $farm): User
    {
        Gate::forUser($actor)->authorize('viewAny', [Role::class, $team]);
        $role = Role::visibleTo($actor, $team)->where('name', 'petugas')->firstOrFail();
        Gate::forUser($actor)->authorize('create', [User::class, $team, $role, new \Illuminate\Database\Eloquent\Collection([$team]), $farm]);
        $user = app(\App\Services\UserManagementService::class)->save($actor, [
            'name' => 'Petugas SIMULASI Cariu Baru', 'email' => self::EMAIL, 'password' => self::PASSWORD,
            'role' => 'petugas', 'team_ids' => [$team->id], 'assigned_farm_id' => $farm->id,
        ]);
        Gate::forUser($actor)->authorize('update', $user);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user->refresh()->load(['role', 'teams', 'assignedFarm']);
    }

    /** @return array<string, PlantingCycle> */
    private function cycles(User $actor, Team $team, FarmBlock $block): array
    {
        $cycles = [];
        foreach ([
            'A' => ['0.1800', '2026-10-29'], 'B' => ['0.2940', '2026-11-01'],
            'C' => ['0.3930', '2026-11-04'], 'D' => ['0.2356', '2026-11-07'],
        ] as $label => [$area, $date]) {
            Gate::forUser($actor)->authorize('create', [PlantingCycle::class, $block]);
            $cycles[$label] = app(FarmBlockService::class)->createBatch($block, $actor, $team, [
                'reserved_area_ha' => $area, 'subblock_label' => 'SIMULASI-'.$label, 'planned_planting_at' => $date,
            ]);
        }

        return $cycles;
    }

    /** @param array<string, PlantingCycle> $cycles
     * @param  list<array<string, mixed>>  $rows
     */
    private function sourceReports(User $actor, User $performer, Team $team, Farm $farm, FarmBlock $block, array $cycles, CropVariety $variety, array $rows): void
    {
        $grouped = collect($rows)->groupBy('date');
        foreach ($grouped as $date => $dayRows) {
            Gate::forUser($actor)->authorize('create', [DailyActivityLog::class, $farm]);
            $log = DailyActivityLog::create([
                'farm_id' => $farm->id, 'recorded_by' => $performer->id, 'work_date' => $date,
                'start_time' => $dayRows->min('start'), 'end_time' => $dayRows->max('end'),
                'notes' => 'SPREADSHEET: rentang gabungan harian; waktu tiap kegiatan ada pada catatan. Petugas adalah identitas SIMULASI.',
            ]);
            foreach ($dayRows as $row) {
                $spec = $this->sourceSpec($row);
                $notes = CariuBaruLogbook::notes($row);
                if ($spec['code'] === 'planting') {
                    $cycle = $cycles[$spec['cycle']];
                    $notes .= "\nSIMULASI: pengelompokan subblok dan varietas tidak berasal dari spreadsheet.";
                    if ($date === '2026-11-07') {
                        $notes .= "\nSIMULASI: luas 2.356 m² diasumsikan dari sisa 2.356,45 m² pada 5 November; luas 7 November pada sumber kosong.";
                    }
                    Gate::forUser($performer)->authorize('recordPlanting', [FarmActivity::class, $cycle]);
                    Gate::forUser($performer)->authorize('synchronize', [PlantingCycleTask::class, $cycle]);
                    $result = app(PlantingActivityService::class)->record($performer, $cycle, [
                        'work_date' => $date, 'start_time' => $row['start'], 'end_time' => $row['end'],
                        'worker_count' => $row['workers'], 'notes' => $notes, 'variety_id' => $variety->id,
                        'area_ha' => $spec['area'], 'seed_name' => null, 'quantity_kg' => null,
                        'planting_pattern' => in_array($date, ['2026-10-29', '2026-10-31'], true) ? '70 x 20 cm' : null, 'photos' => [],
                    ]);
                    if (! in_array($result['status'], ['generated', 'already_generated'], true)) {
                        throw new RuntimeException('Tugas SOP gagal dibentuk untuk '.$row['source'].': '.$result['status']);
                    }
                    $activity = FarmActivity::visibleTo($performer, $team)->where('daily_activity_log_id', $log->id)
                        ->orderByDesc('position')->firstOrFail();
                    Gate::forUser($actor)->authorize('update', $activity);
                    $activity->forceFill($this->sourceFields($row))->save();
                } else {
                    $this->sourceActivity($actor, $performer, $team, $farm, $block, $log, $row, $spec, $notes, false);
                    if (isset($spec['secondary'])) {
                        $this->sourceActivity($actor, $performer, $team, $farm, $block, $log, $row, $spec['secondary'],
                            "TURUNAN TIPE dari {$row['source']}; kegiatan, SDM, kendala, solusi, dan tindakan sama dengan laporan utama, bukan kejadian baru.\n{$row['activity']}\nHasil sumber: {$row['result']}", true);
                    }
                }
            }
        }
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function sourceSpec(array $row): array
    {
        $date = $row['date'];
        $planting = [
            '2026-10-29' => ['A', '0.0637'], '2026-10-31' => ['A', '0.1163'],
            '2026-11-01' => ['B', '0.1316'], '2026-11-02' => ['B', '0.0666'], '2026-11-03' => ['B', '0.0958'],
            '2026-11-04' => ['C', '0.2140'], '2026-11-05' => ['C', '0.1790'], '2026-11-07' => ['D', '0.2356'],
        ];
        if (str_contains(mb_strtolower($row['activity']), 'penanaman jagung') && isset($planting[$date])) {
            return ['code' => 'planting', 'cycle' => $planting[$date][0], 'area' => $planting[$date][1]];
        }
        if ($row['activity'] === 'Pengendalian gulma (potong rumput)') {
            return ['code' => 'land_cleaning', 'detail' => ['area_ha' => $date === '2026-10-06' ? '0.0717' : '0.0338', 'method' => 'Manual']];
        }
        if ($row['activity'] === 'Penyemprotan herbisida') {
            return ['code' => 'land_cleaning', 'detail' => ['area_ha' => '0.4526', 'method' => 'Herbisida']];
        }
        $soil = [
            '2026-10-10' => ['0.1440', '0.4781'], '2026-10-11' => ['0.1154', '0.2163'],
            '2026-10-12' => ['0.1711', '0.1082'], '2026-10-13' => ['0.1553', null],
            '2026-10-14' => ['0.2167', null], '2026-10-15' => ['0.3000', '0.3000'],
        ];
        if (str_contains(mb_strtolower($row['activity']), 'pengolahan tanah') && isset($soil[$date])) {
            $spec = ['code' => 'loosening', 'detail' => ['area_ha' => $soil[$date][0], 'method' => 'Rotary']];
            if ($soil[$date][1] !== null) {
                $spec['secondary'] = ['code' => 'base_fertilization', 'detail' => ['area_ha' => $soil[$date][1], 'fertilization_stage' => 'dasar']];
            }

            return $spec;
        }

        return ['code' => 'other', 'detail' => ['description' => $row['activity']]];
    }

    /** @param array<string, mixed> $row
     * @param  array<string, mixed>  $spec
     */
    private function sourceActivity(User $actor, User $performer, Team $team, Farm $farm, FarmBlock $block, DailyActivityLog $log, array $row, array $spec, string $notes, bool $secondary): void
    {
        Gate::forUser($actor)->authorize('viewAny', [ActivityType::class, $team]);
        $type = ActivityType::visibleTo($actor, $team)->where('code', $spec['code'])->where('is_active', true)->firstOrFail();
        Gate::forUser($actor)->authorize('create', [FarmActivity::class, $farm]);
        $position = (int) FarmActivity::visibleTo($actor, $team)->where('daily_activity_log_id', $log->id)->max('position') + 1;
        $activity = new FarmActivity;
        $activity->forceFill([
            'farm_id' => $farm->id, 'block_id' => $spec['code'] === 'other' ? null : $block->id,
            'activity_type_id' => $type->id, 'performed_by' => $performer->id, 'daily_activity_log_id' => $log->id,
            'position' => $position, 'description' => $notes.($spec['code'] === 'other' ? '' : "\nSIMULASI: asosiasi ke blok agregat adalah asumsi."),
            ...$this->sourceFields($row),
            ...($secondary ? ['worker_count' => null, 'obstacle_status' => 'not_recorded', 'obstacles' => null, 'proposed_solution' => null, 'action_taken' => null] : []),
        ])->save();
        $model = \App\Support\ActivityDetailMap::model($type->detail_key);
        if ($model === null) {
            throw new RuntimeException('Tipe aktivitas tidak memiliki detail yang sesuai: '.$type->code);
        }
        Gate::forUser($actor)->authorize('create', [$model, $activity]);
        (new $model)->forceFill(['activity_id' => $activity->id, ...$spec['detail']])->save();
        app(PlantingCycleStageService::class)->record($actor, $team, $activity, $type);
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function sourceFields(array $row): array
    {
        $obstacle = $row['obstacle'];

        return [
            'activity_date' => CarbonImmutable::parse($row['date'].' '.$row['start'], 'Asia/Jakarta'),
            'worker_count' => $row['workers'], 'obstacle_status' => $obstacle === '' ? 'not_recorded' : ($obstacle === '-' ? 'none' : 'reported'),
            'obstacles' => in_array($obstacle, ['', '-'], true) ? null : $obstacle,
            'proposed_solution' => in_array($row['solution'], ['', '-'], true) ? null : $row['solution'],
            'action_taken' => $row['action'] === '' ? null : $row['action'],
        ];
    }

    /** @param array<string, PlantingCycle> $cycles */
    private function simulatedTasks(User $actor, User $performer, Team $team, Farm $farm, array $cycles): void
    {
        foreach ($cycles as $cycle) {
            $cycle = PlantingCycle::visibleTo($actor, $team)->findOrFail($cycle->id);
            $tasks = PlantingCycleTask::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
                ->orderBy('offset_days')->get();
            if ($tasks->count() !== 6) {
                throw new RuntimeException('Jumlah tugas simulasi Cariu tidak sesuai SOP.');
            }
            foreach ($tasks as $task) {
                $date = $task->scheduled_date?->toDateString();
                if ($date === null) {
                    throw new RuntimeException('Tanggal tugas simulasi tidak tersedia.');
                }
                $area = $cycle->planted_area_ha;
                $details = match ($task->activity_subcategory ?? $task->activity_category) {
                    'irrigation' => ['area_ha' => $area, 'water_source' => 'Irigasi (simulasi)', 'method' => 'Irigasi parit', 'duration_hours' => 1],
                    'weeding' => ['area_ha' => $area, 'method' => 'Manual'],
                    'growth_check' => ['phase' => $task->offset_days === 21 ? 'Vegetatif' : 'Pemasakan', 'height_cm' => $task->offset_days === 21 ? 35 : 180, 'sample_count' => 10, 'pest' => 'Aman', 'disease' => 'Aman'],
                    'follow_up_fertilization' => ['area_ha' => $area, 'fertilizer' => 'NPK (simulasi)', 'quantity_kg' => round((float) $area * 100, 3), 'method' => 'Tabur'],
                    'harvest' => ['area_ha' => $area, 'weight_ton' => round((float) $area * 5, 3), 'moisture' => null, 'method' => 'Manual'],
                    default => throw new RuntimeException('Tugas simulasi tidak didukung.'),
                };
                $note = self::MARKER.' — kegiatan, Petugas, hasil, luas dan angka lanjutan adalah skenario, bukan laporan spreadsheet atau kejadian terverifikasi. '.$cycle->subblock_label.'.';
                Gate::forUser($performer)->authorize('recordTask', [FarmActivity::class, $task]);
                app(TaskActivityService::class)->create($performer, $task, [
                    'work_date' => $date, 'start_time' => '08:00', 'end_time' => '16:00', 'worker_count' => 2,
                    'daily_notes' => 'SIMULASI Cariu Baru — bukan laporan aktual.', 'notes' => $note, 'details' => $details, 'photos' => [],
                ]);
                if ($task->activity_category === 'harvest') {
                    Gate::forUser($actor)->authorize('configure', $cycle);
                    app(HarvestClosureService::class)->close($actor, $team, $cycle);
                    $completedBy = $actor->id;
                } else {
                    Gate::forUser($performer)->authorize('complete', $task);
                    app(PlantingCycleTaskManagementService::class)->complete($task, $performer, self::MARKER.' — penyelesaian skenario.');
                    $completedBy = $performer->id;
                }
                // Services use now(); fixture completion must instead match the simulated report date.
                $task = PlantingCycleTask::visibleTo($actor, $team)->findOrFail($task->id);
                Gate::forUser($actor)->authorize('update', $task);
                $task->forceFill(['completed_at' => CarbonImmutable::parse($date.' 16:00', 'Asia/Jakarta'),
                    'completed_by' => $completedBy, 'completion_note' => self::MARKER.' — penyelesaian skenario.'])->save();
            }
        }
    }
}

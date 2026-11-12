<?php

namespace App\Services\Owner;

use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FarmRisk;
use App\Models\HarvestDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantObservation;
use App\Models\Role;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use App\Models\WeatherRecord;
use App\Services\AdminDashboardService;
use App\Services\FarmLegalityService;
use App\Services\FarmService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OwnerReadService
{
    public function __construct(
        private readonly FarmService $farms,
        private readonly AdminDashboardService $dashboard,
        /*
            kedua service dibawah masih belum tau digunakan untuk apa:
            1. FarmLegalityService $legalities -> dibuat oleh abdul
            2. HarvestProjectionService $harvestProjections -> dibuat oleh okta
        */
        private readonly FarmLegalityService $legalities,
        private readonly HarvestProjectionService $harvestProjections,
    ) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function dashboard(User $actor, Team $team, array $filters = []): array
    {
        // A pie chart must include the full scoped distribution, not Admin's top-three cards.
        $dashboard = $this->dashboard->summary($actor, $team, $filters, commodityLimit: null);
        $farmId = $dashboard['filters']['farm_id'];
        $landTable = $this->dashboardLandTable($actor, $team, $farmId);
        $dashboard['landMap']['farms'] = $this->dashboardMapFarms($actor, $team, $dashboard['landMap']['farms'], $landTable['rows']);

        return [
            'dashboard' => $dashboard,
            'landTable' => $landTable,
        ];
    }

    /**
     * Reuse the table's crop definition and Owner links. PIC information is a
     * separate scoped batch read: an assignment alone never makes someone PIC.
     *
     * @param  list<array<string, mixed>>  $mapFarms
     * @param  list<array<string, mixed>>  $landRows
     * @return list<array<string, mixed>>
     */
    private function dashboardMapFarms(User $actor, Team $team, array $mapFarms, array $landRows): array
    {
        $rows = collect($landRows)->keyBy('id');
        $farmIds = array_column($mapFarms, 'id');
        $farms = Farm::visibleTo($actor, $team)->where('team_id', $team->id)
            ->whereIn('id', $farmIds)->get(['id', 'pic_user_id'])->keyBy('id');
        $pics = User::visibleTo($actor, $team)
            ->whereIn('id', $farms->pluck('pic_user_id')->filter())
            ->whereIn('assigned_farm_id', $farmIds)->where('is_active', true)
            ->whereHas('role', fn ($roles) => $roles->visibleTo($actor, $team)->where('name', 'petugas'))
            ->whereHas('teams', fn ($teams) => $teams->visibleTo($actor, $team)
                ->whereKey($team->id)->where('is_personal', false))
            ->get(['id', 'name', 'assigned_farm_id'])->keyBy('id');

        return array_map(function (array $farm) use ($rows, $farms, $pics): array {
            $row = $rows->get($farm['id']);
            $persisted = $farms->get($farm['id']);
            $pic = $persisted?->pic_user_id === null ? null : $pics->get($persisted->pic_user_id);
            if ($pic !== null && (string) $pic->getAttribute('assigned_farm_id') !== $farm['id']) {
                $pic = null;
            }

            return [
                ...$farm,
                'crops' => $row['crops'] ?? [],
                'pic' => $pic?->only(['id', 'name']),
                'detail_url' => $row['detail_url'] ?? '/kebun/'.$farm['id'],
            ];
        }, $mapFarms);
    }

    /**
     * The table uses the dashboard's resolved farm filter, not a client-supplied
     * tenant. The period affects the activity chart only; resume is the latest
     * persisted report for each farm and carries its own timestamp.
     *
     * @return array<string, mixed>
     */
    private function dashboardLandTable(User $actor, Team $team, ?string $farmId): array
    {
        $query = Farm::visibleTo($actor, $team)->where('team_id', $team->id)
            ->when($farmId !== null, fn (Builder $farms) => $farms->whereKey($farmId));
        $totalArea = (string) (clone $query)->sum('total_area_ha');
        $farms = $query->with([
            // Laravel's eager-loading limit selects one report per parent,
            // rather than one report for the entire farm collection.
            'activities' => fn ($activities) => $activities->visibleTo($actor, $team)
                ->orderByDesc('activity_date')->orderByDesc('created_at')->orderByDesc('id')->limit(1)
                ->with(['activityType' => fn ($types) => $types->visibleTo($actor, $team)]),
        ])->orderBy('name')->orderBy('id')->get(['id', 'name', 'city', 'land_tenure', 'land_tenure_party', 'total_area_ha']);
        $cyclesByFarm = PlantingCycle::visibleTo($actor, $team)
            ->whereIn('farm_id', $farms->modelKeys())
            ->whereIn('status', ['planned', 'active', 'harvested'])
            ->with([
                'variety' => fn ($varieties) => $varieties->visibleTo($actor, $team)
                    ->with(['commodity' => fn ($commodities) => $commodities->visibleTo($actor, $team)]),
                'stages' => fn ($stages) => $stages->visibleTo($actor, $team)->orderBy('position')->orderBy('id'),
            ])->get()->groupBy('farm_id');

        $rows = [];
        foreach ($farms as $farm) {
            $cycles = $cyclesByFarm->get($farm->id, collect());
            $running = $cycles->whereIn('status', ['planned', 'active']);
            $stages = $running->flatMap(fn (PlantingCycle $cycle) => $cycle->stages);
            $currentStages = $stages->filter(fn (PlantingCycleStage $stage): bool => $stage->getAttribute('status') === 'in_progress')
                ->map(fn (PlantingCycleStage $stage): string => $this->stageName((string) $stage->getAttribute('code')))
                ->unique()->values()->all();
            $crops = [];
            // Match Admin's "Komoditas tertanam": planned/cancelled cycles
            // never become evidence that a crop has already been planted.
            foreach ($cycles->whereIn('status', ['active', 'harvested']) as $cycle) {
                $variety = $cycle->variety;
                $commodity = $variety?->commodity;
                if ($commodity === null) {
                    continue;
                }
                $key = (string) $commodity->id;
                $crops[$key] ??= ['id' => $key, 'name' => $commodity->name, 'varieties' => []];
                if (! in_array($variety->name, $crops[$key]['varieties'], true)) {
                    $crops[$key]['varieties'][] = $variety->name;
                }
            }
            $crops = array_values($crops);
            usort($crops, fn (array $first, array $second): int => strcmp($first['name'], $second['name']));
            $latest = $farm->activities->first();
            $rows[] = [
                'id' => (string) $farm->id,
                'name' => $farm->name,
                'detail_url' => '/kebun/'.$farm->id,
                'legality_url' => '/kebun/'.$farm->id.'/legalitas',
                'area_ha' => $farm->total_area_ha,
                'legality' => match ($farm->land_tenure) {
                    'owned' => 'Milik sendiri', 'rented' => 'Sewa', 'partnership' => 'Kerja sama',
                    default => $farm->land_tenure,
                },
                'land_tenure_party' => in_array($farm->land_tenure, ['rented', 'partnership'], true) ? $farm->getAttribute('land_tenure_party') : null,
                'requires_land_tenure_party' => in_array($farm->land_tenure, ['rented', 'partnership'], true),
                'crops' => $crops,
                'progress' => [
                    'completed_stages' => $stages->filter(fn (PlantingCycleStage $stage): bool => $stage->getAttribute('status') === 'completed')->count(),
                    'total_stages' => $stages->count(),
                    'current_stages' => $currentStages,
                    'running_batches' => $running->count(),
                ],
                'resume' => $latest === null ? null : [
                    'id' => (string) $latest->id,
                    'title' => $latest->activityType?->name,
                    'description' => $latest->description,
                    'date' => $latest->activity_date->toIso8601String(),
                    'status' => $latest->status,
                    'detail_url' => '/aktivitas/'.$latest->id,
                ],
            ];
        }

        // Flat rows remain the data source for the map and existing summary
        // cards. The regional table is an additional presentation of the same
        // scoped records, not a separate data source.
        /** @var list<array{
         *     key: string,
         *     farm_id: string,
         *     farm_name: string,
         *     crop_name: string|null,
         *     projection_ton: float|null,
         *     missing_count: int,
         *     batch_count: int
         * }> $projectionRowData
         */
        $projectionRowData = $this->harvestProjections
            ->summary($actor, $team, 'cycle', $farmId)['rows'];
        $projectionRows = collect($projectionRowData);
        $projectionsByFarm = $projectionRows->groupBy('farm_id')->map(fn ($farmProjections) => $farmProjections
            ->filter(fn (array $projection): bool => $projection['crop_name'] !== null)
            ->map(fn (array $projection): array => [
                'id' => (string) $projection['key'],
                'name' => (string) $projection['crop_name'],
                'ton' => $projection['projection_ton'],
            ])->values()->all());
        foreach ($rows as &$row) {
            $row['projections'] = $projectionsByFarm->get($row['id'], []);
        }
        unset($row);
        $citiesByFarm = $farms->mapWithKeys(
            fn (Farm $farm): array => [(string) $farm->id => trim((string) $farm->city)],
        );
        $groups = collect($rows)
            // Farms without a city still count in the global cards and map,
            // but must not appear as an artificial "unknown" region row.
            ->filter(fn (array $row): bool => $citiesByFarm->get($row['id'], '') !== '')
            ->groupBy(fn (array $row): string => (string) $citiesByFarm->get($row['id']))
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(function ($farmRows, string $city) use ($projectionRows): array {
                $farmRows = $farmRows->values();
                $farmIds = $farmRows->pluck('id')->all();
                $completedStages = $farmRows->sum(fn (array $row): int => $row['progress']['completed_stages']);
                $totalStages = $farmRows->sum(fn (array $row): int => $row['progress']['total_stages']);
                $knownProjectionRows = $projectionRows->whereIn('farm_id', $farmIds)
                    ->filter(fn (array $row): bool => $row['crop_name'] !== null && $row['projection_ton'] !== null);
                $projectionCrops = $knownProjectionRows->groupBy('crop_name')
                    ->map(fn ($cropRows, string $name): array => [
                        'id' => $name,
                        'name' => $name,
                        'ton' => round((float) $cropRows->sum('projection_ton'), 3),
                    ])
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();

                return [
                    'id' => 'city:'.sha1(mb_strtolower($city)),
                    'name' => $city,
                    'farm_count' => $farmRows->count(),
                    'area_ha' => number_format((float) $farmRows->sum(fn (array $row): float => (float) $row['area_ha']), 4, '.', ''),
                    'crops' => $farmRows->flatMap(fn (array $row) => $row['crops'])
                        ->unique('id')
                        ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                        ->map(fn (array $crop): array => ['id' => $crop['id'], 'name' => $crop['name']])
                        ->values()
                        ->all(),
                    'progress' => [
                        'percentage' => $totalStages === 0 ? null : round(($completedStages / $totalStages) * 100, 1),
                        'completed_stages' => $completedStages,
                        'total_stages' => $totalStages,
                    ],
                    'projection' => [
                        'total_ton' => round((float) $knownProjectionRows->sum('projection_ton'), 3),
                        'has_data' => $knownProjectionRows->isNotEmpty(),
                        'commodities' => $projectionCrops,
                    ],
                    'rows' => $farmRows->all(),
                ];
            })
            ->values()
            ->all();

        return ['rows' => $rows, 'groups' => $groups, 'total_area_ha' => $totalArea, 'total' => $farms->count()];
    }

    private function stageName(string $code): string
    {
        return match ($code) {
            'land_clearing' => 'Pembersihan lahan',
            'soil_preparation' => 'Persiapan tanah',
            'basic_fertilizer' => 'Pupuk dasar',
            'planting' => 'Penanaman',
            'maintenance' => 'Pemeliharaan',
            'harvest' => 'Panen',
            default => 'Tahap lainnya',
        };
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function farms(User $actor, Team $team, array $filters): array
    {
        return [
            'farms' => $this->farms->paginate($actor, $team, $filters)->through(fn (Farm $farm): array => $farm->only([
                'id',
                'name',
                'city',
                'district',
                'province',
                'address',
                'land_tenure',
                'land_tenure_party',
                'total_area_ha',
                'latitude',
                'longitude',
                'image_url',
            ])),
            'summary' => $this->farms->summary($actor, $team),
            'locationOptions' => $this->farms->locationOptions($actor, $team),
            'filters' => $filters,
        ];
    }

    /** @return array<string, mixed> */
    public function farm(User $actor, Team $team, Farm $farm): array
    {
        $farm = Farm::visibleTo($actor, $team)->findOrFail($farm->id);
        $cycles = $this->cycleQuery($actor, $team)->where('farm_id', $farm->id);
        $active = (clone $cycles)->where('status', 'active');
        $defaultSop = SopTemplate::visibleTo($actor, $team)->whereKey($farm->default_sop_template_id)->first();
        $blocks = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)
            ->orderBy('code')->get();
        $cycleCounts = PlantingCycle::visibleTo($actor, $team)
            ->where('farm_id', $farm->id)
            ->whereNotNull('block_id')
            ->whereHas('block', fn ($blocks) => $blocks->visibleTo($actor, $team))
            ->with(['block' => fn ($blocks) => $blocks->visibleTo($actor, $team)])
            ->get()->groupBy(fn (PlantingCycle $cycle) => $cycle->block_id ?? '');
        $observations = PlantObservation::visibleTo($actor, $team)
            ->whereHas('plantingCycle', fn ($cycles) => $cycles->visibleTo($actor, $team)->where('farm_id', $farm->id))
            ->with([
                'plantingCycle' => fn ($cycles) => $cycles->visibleTo($actor, $team)
                    ->with(['variety' => fn ($varieties) => $varieties->visibleTo($actor, $team)]),
            ])
            ->latest('observed_at')->limit(5)->get();
        $weather = WeatherRecord::visibleTo($actor, $team)->where('farm_id', $farm->id)->latest('recorded_at')->first();
        $risks = FarmRisk::visibleTo($actor, $team)
            ->whereHas('plantingCycle', fn ($cycles) => $cycles->visibleTo($actor, $team)->where('farm_id', $farm->id))
            ->latest('detected_at')->get();
        $harvest = HarvestDetail::visibleTo($actor, $team)
            ->whereHas('activity', fn ($activities) => $activities->visibleTo($actor, $team)->where('farm_id', $farm->id))
            ->sum('gross_weight_ton');
        $owner = User::visibleTo($actor, $team)->whereKey($farm->owner_id)->first(['id', 'name']);

        return [
            'farm' => [
                ...$farm->only(['id', 'name', 'land_tenure', 'land_tenure_party', 'address', 'city', 'district', 'province', 'total_area_ha', 'latitude', 'longitude', 'image_url']),
                'legality_url' => '/kebun/'.$farm->id.'/legalitas',
                'owner_name' => $owner?->name,
                'team_name' => $team->name,
                'capacity' => $this->farms->capacity($farm, $actor, $team),
                'active_cycles' => (clone $active)->count(),
                'active_area_ha' => (string) (clone $active)->sum('planted_area_ha'),
                'harvest_weight_ton' => (string) $harvest,
                'default_sop' => $defaultSop?->only(['id', 'name', 'is_active']),
            ],
            'blocks' => $blocks->map(fn (FarmBlock $block): array => [
                ...$block->only(['id', 'code', 'name', 'area_ha', 'is_active', 'description']),
                'cycles_count' => $cycleCounts->get($block->id, collect())->count(),
            ])->values()->all(),
            'legacyCycles' => (clone $cycles)->whereNull('block_id')->orderByDesc('created_at')
                ->get()->map(fn (PlantingCycle $cycle): array => $this->cyclePayload($cycle))->all(),
            'observations' => $observations->map(fn (PlantObservation $observation): array => [
                ...$observation->only(['id', 'planting_cycle_id', 'growth_phase', 'health_status', 'growth_percentage', 'average_height_cm', 'disease_indication', 'pest_indication', 'plant_condition', 'notes']),
                'observed_at' => $observation->observed_at->toIso8601String(),
                'cycle' => $observation->plantingCycle === null ? null : [
                    'id' => $observation->plantingCycle->id,
                    'label' => $observation->plantingCycle->getAttribute('batch_number') === null ? 'Siklus tanam' : 'Batch '.$observation->plantingCycle->getAttribute('batch_number'),
                    'variety' => $observation->plantingCycle->variety?->name,
                ],
            ])->all(),
            'weather' => $weather === null ? null : [
                ...$weather->only(['condition', 'temperature_c', 'humidity_percentage', 'data_source']),
                'recorded_at' => $weather->recorded_at->toIso8601String(),
            ],
            'risks' => $risks->map(fn (FarmRisk $risk): array => [
                ...$risk->only(['id', 'risk_description', 'severity', 'recommendation', 'status']),
                'detected_at' => $risk->detected_at->toIso8601String(),
            ])->all(),
            'officers' => User::visibleTo($actor, $team)->where('assigned_farm_id', $farm->id)
                ->whereHas('role', fn ($roles) => $roles->visibleTo($actor, $team)->where('name', 'petugas'))
                ->orderBy('name')->get(['id', 'name', 'is_active'])->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function legality(User $actor, Team $team, Farm $farm): array
    {
        $farm = Farm::visibleTo($actor, $team)->where('team_id', $team->id)->findOrFail($farm->id);

        return [
            'farm' => $farm->only(['id', 'name', 'land_tenure', 'land_tenure_party', 'total_area_ha', 'address', 'city', 'district', 'province']),
            'legality' => $this->legalities->payload($actor, $team, $farm),
            'urls' => [
                'farm' => route('owner.farms.show', $farm, false),
                'home' => route('home', [], false),
                'farms' => route('kebun', [], false),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function block(User $actor, Team $team, Farm $farm, FarmBlock $block): array
    {
        $farm = Farm::visibleTo($actor, $team)->findOrFail($farm->id);
        $block = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->findOrFail($block->id);
        $clearing = app(\App\Services\Activity\BlockPreparationTimeline::class)->clearingForBlocks($actor, $team, collect([$block->id]));
        $block->setAttribute('land_clearing_started_at', $clearing[$block->id]['started_at'] ?? null);
        $block->setAttribute('land_clearing_completed_at', $clearing[$block->id]['completed_at'] ?? null);
        $cycles = $this->cycleQuery($actor, $team)->where('farm_id', $farm->id)
            ->where('block_id', $block->id)
            ->orderByDesc('created_at')->orderBy('batch_number')->get();

        return [
            'farm' => $farm->only(['id', 'name']),
            'block' => [
                ...$block->only(['id', 'code', 'name', 'area_ha', 'description', 'is_active']),
                'land_clearing_started_at' => $this->timestamp($block->getAttribute('land_clearing_started_at')),
                'land_clearing_completed_at' => $this->timestamp($block->getAttribute('land_clearing_completed_at')),
            ],
            'cycles' => $cycles->map(fn (PlantingCycle $cycle): array => $this->cyclePayload($cycle))->all(),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function activities(User $actor, Team $team, array $filters): array
    {
        $query = $this->activityQuery($actor, $team);
        foreach (['farm_id', 'performed_by', 'activity_type_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['from_date'])) {
            $query->whereDate('activity_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('activity_date', '<=', $filters['to_date']);
        }
        $this->search($query, $filters['search'] ?? '', ['description']);

        return [
            'activities' => $query->orderByDesc('activity_date')->orderBy('id')
                ->paginate($filters['per_page'] ?? 15)->withQueryString()
                ->through(fn (FarmActivity $activity): array => $this->activityPayload($activity)),
            'filters' => $filters,
            'farms' => Farm::visibleTo($actor, $team)->orderBy('name')->get(['id', 'name']),
            'activityTypes' => ActivityType::visibleTo($actor, $team)->orderBy('name')->get(['id', 'code', 'name']),
            'officers' => User::visibleTo($actor, $team)
                ->whereHas('activities', fn ($activities) => $activities->visibleTo($actor, $team))
                ->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    public function activity(User $actor, Team $team, FarmActivity $activity): array
    {
        $relations = ['photos', 'dailyActivityLog', 'blockMappingDetail', 'plantingDetail', 'fertilizationDetail',
            'irrigationDetail', 'pesticideDetail', 'incidentDetail', 'harvestDetail', 'observation',
            'landCleaningDetail', 'looseningDetail', 'bedPreparationDetail', 'limingDetail', 'weedingDetail', 'otherDetail'];
        $query = $this->activityQuery($actor, $team);
        foreach ($relations as $relation) {
            $query->with([$relation => fn ($related) => $related->visibleTo($actor, $team)]);
        }
        $activity = $query->findOrFail($activity->id);
        $fields = [
            'blockMappingDetail' => ['block_name' => 'Nama blok', 'length_m' => 'Panjang (m)', 'width_m' => 'Lebar (m)', 'row_spacing_cm' => 'Jarak baris (cm)', 'plant_spacing_cm' => 'Jarak tanaman (cm)', 'rows_per_bed' => 'Baris per bedeng'],
            'plantingDetail' => ['seed_name' => 'Benih', 'quantity_kg' => 'Jumlah benih (kg)', 'planting_pattern' => 'Pola tanam', 'area_ha' => 'Luas tanam (ha)'],
            'fertilizationDetail' => ['fertilization_stage' => 'Tahap pemupukan', 'fertilizer_type' => 'Jenis pupuk', 'brand_name' => 'Merek pupuk', 'subsidy_status' => 'Status subsidi', 'application_method' => 'Cara aplikasi', 'quantity_kg' => 'Jumlah pupuk (kg)'],
            'irrigationDetail' => ['water_source' => 'Sumber air', 'irrigation_method' => 'Metode pengairan', 'machine_type' => 'Jenis mesin', 'duration_hours' => 'Durasi (jam)'],
            'pesticideDetail' => ['pesticide_category' => 'Kategori pestisida', 'purpose' => 'Tujuan', 'brand_name' => 'Merek pestisida', 'active_ingredient' => 'Bahan aktif', 'dosage_per_tank' => 'Dosis per tangki', 'tank_size_liters' => 'Ukuran tangki (liter)', 'tank_count' => 'Jumlah tangki'],
            'incidentDetail' => ['incident_category' => 'Kategori insiden', 'severity' => 'Tingkat keparahan', 'affected_area_ha' => 'Luas terdampak (ha)', 'proposed_solution' => 'Usulan penanganan'],
            'harvestDetail' => ['harvested_area_ha' => 'Luas panen (ha)', 'gross_weight_ton' => 'Berat panen (ton)', 'moisture_percentage' => 'Kadar air (%)', 'color' => 'Warna', 'ripeness' => 'Kematangan', 'cleanliness' => 'Kebersihan'],
            'observation' => ['growth_phase' => 'Fase pertumbuhan', 'average_height_cm' => 'Tinggi rata-rata (cm)', 'health_status' => 'Kondisi tanaman', 'growth_percentage' => 'Pertumbuhan tercatat (%)', 'plant_condition' => 'Keadaan tanaman', 'disease_indication' => 'Indikasi penyakit', 'pest_indication' => 'Indikasi hama', 'notes' => 'Catatan pengamatan'],
            'landCleaningDetail' => ['area_ha' => 'Luas (ha)', 'method' => 'Metode', 'product_name' => 'Produk'],
            'looseningDetail' => ['area_ha' => 'Luas (ha)', 'method' => 'Metode', 'depth_cm' => 'Kedalaman (cm)'],
            'bedPreparationDetail' => ['bed_count' => 'Jumlah bedeng', 'length_m' => 'Panjang (m)', 'width_m' => 'Lebar (m)', 'height_cm' => 'Tinggi (cm)', 'bed_spacing_cm' => 'Jarak bedeng (cm)'],
            'limingDetail' => ['area_ha' => 'Luas (ha)', 'material_type' => 'Material', 'quantity_value' => 'Jumlah', 'quantity_unit' => 'Satuan', 'application_method' => 'Metode'],
            'weedingDetail' => ['area_ha' => 'Luas (ha)', 'method' => 'Metode', 'depth_cm' => 'Kedalaman (cm)'],
            'otherDetail' => ['description' => 'Deskripsi pekerjaan', 'area_ha' => 'Luas (ha)'],
        ];
        $details = [];
        foreach ($fields as $relation => $labels) {
            $record = $activity->getRelation($relation);
            if ($record === null) {
                continue;
            }
            foreach ($labels as $field => $label) {
                $value = $record->getAttribute($field);
                if ($value !== null && $value !== '') {
                    $details[] = ['label' => $label, 'value' => $this->detailValue($record, $field, $value)];
                }
            }
        }
        $log = $activity->dailyActivityLog;

        return [
            ...$this->activityPayload($activity),
            ...$activity->only(['obstacle_status', 'obstacles', 'proposed_solution', 'action_taken']),
            'worker_count' => $activity->worker_count,
            'photos' => $activity->photos->map(fn ($photo): array => [...$photo->only(['id', 'caption', 'label']), 'image_url' => route('daily-logs.photos', ['farm' => $activity->farm_id, 'activity' => $activity->id, 'photo' => $photo->id])])->all(),
            'details' => $details,
            'log' => $log === null ? null : [
                ...$log->only(['id', 'start_time', 'end_time', 'notes']),
                'work_date' => $log->work_date->toDateString(),
            ],
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function sops(User $actor, Team $team, array $filters): array
    {
        $query = $this->sopQuery($actor, $team);
        $this->search($query, $filters['search'] ?? '', ['name', 'description']);

        return [
            'sops' => $query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 15)->withQueryString()
                ->through(fn (SopTemplate $sop): array => $this->sopPayload($sop)),
            'filters' => $filters,
        ];
    }

    /** @return array<string, mixed> */
    public function sop(User $actor, Team $team, SopTemplate $sop): array
    {
        $sop = $this->sopQuery($actor, $team)
            ->with([
                'versions' => fn ($versions) => $versions->visibleTo($actor, $team)->orderByDesc('version')
                    ->with(['tasks' => fn ($tasks) => $tasks->visibleTo($actor, $team)]),
            ])
            ->findOrFail($sop->id);

        return [
            ...$this->sopPayload($sop),
            'versions' => $sop->versions->map(fn ($version): array => [
                ...$version->only(['id', 'version', 'status', 'notes']),
                'published_at' => $version->published_at?->toIso8601String(),
                'tasks' => $version->tasks->map(fn ($task): array => $task->only([
                    'id',
                    'name',
                    'instructions',
                    'activity_category',
                    'activity_subcategory',
                    'offset_days',
                    'tolerance_days',
                    'is_required',
                ]))->all(),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function users(User $actor, Team $team, array $filters): array
    {
        $query = $this->userQuery($actor, $team);
        $this->search($query, $filters['search'] ?? '', ['name', 'email']);
        if (! empty($filters['role'])) {
            $query->whereHas('role', fn ($roles) => $roles->visibleTo($actor, $team)->where('name', $filters['role']));
        }

        return [
            'users' => $query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 15)->withQueryString()
                ->through(fn (User $user): array => $this->userPayload($user)),
            'filters' => $filters,
        ];
    }

    /** @return array<string, mixed> */
    public function user(User $actor, Team $team, User $target): array
    {
        $user = $this->userQuery($actor, $team)
            ->with(['teams' => fn ($teams) => $teams->visibleTo($actor, $team)->where('is_personal', false)])
            ->findOrFail($target->id);

        return [...$this->userPayload($user), 'teams' => $user->teams->map(fn ($item): array => $item->only(['id', 'name']))->all()];
    }

    /** @return Builder<PlantingCycle> */
    private function cycleQuery(User $actor, Team $team): Builder
    {
        return PlantingCycle::visibleTo($actor, $team)->with([
            'variety' => fn ($varieties) => $varieties->visibleTo($actor, $team),
            'variety.commodity' => fn ($commodities) => $commodities->visibleTo($actor, $team),
            'stages' => fn ($stages) => $stages->visibleTo($actor, $team)->orderBy('position'),
            'tasks' => fn ($tasks) => $tasks->visibleTo($actor, $team),
            'sopTemplateVersion' => fn ($versions) => $versions->visibleTo($actor, $team),
            'sopTemplateVersion.sopTemplate' => fn ($sops) => $sops->visibleTo($actor, $team),
        ]);
    }

    /** @return array<string, mixed> */
    private function cyclePayload(PlantingCycle $cycle): array
    {
        $variety = $cycle->variety;
        $version = $cycle->sopTemplateVersion;
        $sop = $version?->sopTemplate;

        return [
            ...$cycle->only(['id', 'batch_number', 'subblock_label', 'status', 'reserved_area_ha', 'planted_area_ha']),
            'label' => $cycle->getAttribute('batch_number') === null ? 'Siklus tanam' : 'Batch '.$cycle->getAttribute('batch_number'),
            'planned_planting_at' => $cycle->planned_planting_at?->toDateString(),
            'planted_at' => $cycle->planted_at?->toDateString(),
            'estimated_harvest_at' => $cycle->estimated_harvest_at?->toDateString(),
            'variety' => $variety === null ? null : ['name' => $variety->name, 'commodity' => $variety->commodity?->name],
            'sop' => $sop === null ? null : ['id' => $sop->id, 'name' => $sop->name, 'version' => $version->version],
            'stages' => $cycle->stages->map(fn (PlantingCycleStage $stage): array => [
                ...$stage->only(['id', 'code', 'position', 'status']),
                'name' => match ($stage->getAttribute('code')) {
                    'land_clearing' => 'Pembersihan lahan',
                    'soil_preparation' => 'Persiapan tanah',
                    'basic_fertilizer' => 'Pupuk dasar',
                    'planting' => 'Penanaman',
                    'maintenance' => 'Pemeliharaan',
                    'harvest' => 'Panen',
                    default => 'Tahap lainnya',
                },
                'started_at' => $this->timestamp($stage->getAttribute('started_at')),
                'completed_at' => $this->timestamp($stage->getAttribute('completed_at')),
            ])->all(),
            'tasks' => $cycle->tasks->map(fn ($task): array => [
                ...$task->only(['id', 'name', 'status', 'instructions', 'offset_days', 'tolerance_days', 'is_required', 'activity_category', 'activity_subcategory']),
                'scheduled_date' => $task->scheduled_date?->toDateString(),
                'due_date' => $task->due_date?->toDateString(),
                'completed_at' => $task->completed_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /** @return Builder<FarmActivity> */
    private function activityQuery(User $actor, Team $team): Builder
    {
        return FarmActivity::visibleTo($actor, $team)->addSelect('farm_activities.*')
            ->selectSub(FarmBlock::visibleTo($actor, $team)->select('name')->whereColumn('farm_blocks.id', 'farm_activities.block_id')->whereColumn('farm_blocks.farm_id', 'farm_activities.farm_id'), 'block_name')->with([
                'farm' => fn ($farms) => $farms->visibleTo($actor, $team),
                'performer' => fn ($users) => $users->visibleTo($actor, $team),
                'activityType' => fn ($types) => $types->visibleTo($actor, $team),
                'plantingCycle' => fn ($cycles) => $cycles->visibleTo($actor, $team),
            ]);
    }

    /** @return array<string, mixed> */
    private function activityPayload(FarmActivity $activity): array
    {
        $cycle = $activity->plantingCycle;

        return [
            ...$activity->only(['id', 'description', 'status']),
            'activity_date' => $activity->activity_date->toIso8601String(),
            'farm' => $activity->farm?->only(['id', 'name']),
            'block_name' => $activity->getAttribute('block_name'),
            'performer' => $activity->performer?->only(['id', 'name']),
            'type' => $activity->activityType?->only(['code', 'name']),
            'cycle' => $cycle === null ? null : ['id' => $cycle->id, 'label' => $cycle->getAttribute('batch_number') === null ? 'Siklus tanam' : 'Batch '.$cycle->getAttribute('batch_number')],
        ];
    }

    /** @return Builder<SopTemplate> */
    private function sopQuery(User $actor, Team $team): Builder
    {
        return SopTemplate::visibleTo($actor, $team)->with([
            'commodity' => fn ($commodities) => $commodities->visibleTo($actor, $team),
            'versions' => fn ($versions) => $versions->visibleTo($actor, $team)->orderByDesc('version'),
        ]);
    }

    /** @return array<string, mixed> */
    private function sopPayload(SopTemplate $sop): array
    {
        return [
            ...$sop->only(['id', 'name', 'description', 'is_active']),
            'commodity' => $sop->commodity?->only(['id', 'name']),
            'versions_count' => $sop->versions->count(),
            'published_version' => $sop->versions->firstWhere('status', 'published')?->version,
        ];
    }

    /** @return Builder<User> */
    private function userQuery(User $actor, Team $team): Builder
    {
        return User::visibleTo($actor, $team)->with([
            'role' => fn ($roles) => $roles->visibleTo($actor, $team),
            'assignedFarm' => fn ($farms) => $farms->visibleTo($actor, $team),
        ]);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $role = $user->getRelation('role');

        return [
            ...$user->only(['id', 'name', 'email', 'phone_number', 'is_active']),
            'role' => $role instanceof Role ? $role->name : null,
            'assigned_farm' => $user->assignedFarm?->only(['id', 'name']),
        ];
    }

    private function detailValue(Model $record, string $field, mixed $value): string
    {
        $cast = $record->getCasts()[$field] ?? '';
        [$type, $precision] = array_pad(explode(':', $cast, 2), 2, null);
        if (! in_array($type, ['decimal', 'integer', 'int', 'float', 'double', 'real'], true) || ! is_numeric($value)) {
            return (string) $value;
        }

        $parts = explode('e', strtolower((string) $value), 2);
        $fractionLength = strlen(explode('.', $parts[0], 2)[1] ?? '');
        $decimals = match ($type) {
            'decimal' => (int) $precision,
            'integer', 'int' => 0,
            default => max(0, $fractionLength - (int) ($parts[1] ?? 0)),
        };
        $formatted = number_format((float) $value, $decimals, ',', '.');

        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->toIso8601String() : null;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     */
    private function search(Builder $query, ?string $search, array $columns): void
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return;
        }
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
        $query->where(function ($query) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'ilike', $pattern);
            }
        });
    }
}

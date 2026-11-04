<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FarmService
{
    public function __construct(private readonly SopTemplateService $sops) {}

    /**
     * @param  array<string, mixed>  $filters  Validated list parameters.
     * @return LengthAwarePaginator<int, Farm>
     */
    public function paginate(User $user, Team $team, array $filters): LengthAwarePaginator
    {
        $query = Farm::visibleTo($user, $team)->with('team');
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn ($query) => $query->where('name', 'ilike', $pattern)
                ->orWhere('address', 'ilike', $pattern)->orWhere('city', 'ilike', $pattern)
                ->orWhere('district', 'ilike', $pattern)->orWhere('province', 'ilike', $pattern));
        }
        foreach (['city', 'province'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        // Keep the SQL column allowlist even when this service is called outside HTTP.
        $sort = in_array($filters['sort'] ?? '', ['name', 'created_at', 'total_area_ha'], true) ? $filters['sort'] : 'name';
        $direction = ($filters['direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction)->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)->appends($filters);
    }

    /** @return array{count: int, area_ha: string, cities: int} */
    public function summary(User $user, Team $team): array
    {
        $query = Farm::visibleTo($user, $team);

        return [
            'count' => (clone $query)->count(),
            'area_ha' => (string) (clone $query)->sum('total_area_ha'),
            'cities' => (clone $query)->whereNotNull('city')->where('city', '<>', '')->distinct()->count('city'),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array{id: string, name: string}> */
    public function owners(User $user, Team $team): \Illuminate\Support\Collection
    {
        return User::visibleTo($user, $team)
            ->whereHas('teams', fn ($teams) => $teams->whereKey($team->id))
            ->whereHas('role', fn ($roles) => $roles->where('name', 'owner'))
            ->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $owner): array => ['id' => $owner->id, 'name' => $owner->name]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    public function picOptions(User $actor, Team $team, ?Farm $farm = null): \Illuminate\Database\Eloquent\Collection
    {
        return User::visibleTo($actor, $team)->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('name', 'petugas'))
            ->whereHas('teams', fn ($q) => $q->whereKey($team->id))
            ->where(fn ($q) => $q->whereNull('assigned_farm_id')->when($farm, fn ($q) => $q->orWhere('assigned_farm_id', $farm->id)))
            ->orderBy('name')->get(['id', 'name']);
    }

    /** @return array{block_area_ha: string, reserved_area_ha: string, minimum_area_ha: string} */
    public function capacity(Farm $farm, User $user, Team $team): array
    {
        $blocks = (string) \App\Models\FarmBlock::visibleTo($user, $team)->where('farm_id', $farm->id)->where('is_active', true)->sum('area_ha');
        $usage = PlantingCycle::visibleTo($user, $team)->where('farm_id', $farm->id)->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN planting_round_id IS NULL AND status = 'active' THEN planted_area_ha WHEN planting_round_id IS NOT NULL AND status IN ('planned', 'active') THEN reserved_area_ha ELSE 0 END), 0) AS reserved")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'active' THEN planted_area_ha ELSE 0 END), 0) AS planted")->first();

        return ['block_area_ha' => $blocks, 'reserved_area_ha' => (string) $usage->reserved,
            'minimum_area_ha' => \App\Support\LandArea::hectares(max(1, \App\Support\LandArea::squareMetres($blocks), \App\Support\LandArea::squareMetres($usage->reserved), \App\Support\LandArea::squareMetres($usage->planted)))];
    }

    /** @return array{cities: list<string>, provinces: list<string>} */
    public function locationOptions(User $user, Team $team): array
    {
        $farms = Farm::visibleTo($user, $team);

        $values = fn (string $field): array => array_values((clone $farms)->whereNotNull($field)->where($field, '<>', '')->distinct()->orderBy($field)->pluck($field)->map(fn ($value): string => (string) $value)->all());

        return ['cities' => $values('city'), 'provinces' => $values('province')];
    }

    /** @return list<string> */
    public function deletionBlockers(Farm $farm, User $user, Team $team): array
    {
        Farm::visibleTo($user, $team)->findOrFail($farm->id);
        $blockers = [];
        foreach ([\App\Models\FarmBlock::class => 'blok kebun', PlantingCycle::class => 'siklus tanam', \App\Models\DailyActivityLog::class => 'logbook', \App\Models\FarmActivity::class => 'aktivitas', \App\Models\WeatherRecord::class => 'riwayat cuaca', \App\Models\FinancialTransaction::class => 'transaksi', \App\Models\FinancialProjection::class => 'proyeksi', \App\Models\FinancialBudget::class => 'anggaran', \App\Models\OperationalTarget::class => 'target operasional'] as $model => $label) {
            if ($model::visibleTo($user, $team)->where('farm_id', $farm->id)->exists()) {
                $blockers[] = $label;
            }
        }
        if (User::visibleTo($user, $team)->where('assigned_farm_id', $farm->id)->exists()) {
            $blockers[] = 'penugasan Petugas';
        }

        return $blockers;
    }

    /** @return array<string, mixed> */
    public function detail(Farm $farm, User $user): array
    {
        $team = $farm->team;
        abort_unless($team instanceof Team, 403);
        $visible = Farm::visibleTo($user, $team)->findOrFail($farm->id);
        $cycles = PlantingCycle::visibleTo($user, $team)->where('farm_id', $visible->id)->where('status', 'active');
        $owner = User::visibleTo($user, $team)->whereKey($visible->owner_id)->first(['id', 'name']);
        $pic = User::visibleTo($user, $team)->whereKey($visible->getAttribute('pic_user_id'))
            ->where('is_active', true)->where('assigned_farm_id', $visible->id)
            ->whereHas('role', fn ($q) => $q->where('name', 'petugas'))
            ->whereHas('teams', fn ($q) => $q->whereKey($team->id))->first(['id', 'name', 'phone_number']);

        return [
            ...$visible->only(['id', 'name', 'land_tenure', 'owner_id', 'address', 'city', 'district', 'province', 'district_code', 'total_area_ha', 'latitude', 'longitude', 'image_url', 'default_sop_template_id', 'commodity_id', 'target_volume_ton']),
            'commodity' => \App\Models\Commodity::visibleTo($user, $team)->find($visible->getAttribute('commodity_id'))?->only(['id', 'name']),
            'region' => $visible->getAttribute('district_code') ? app(AdministrativeRegionService::class)->district($visible->getAttribute('district_code'), $user, $team) : null,
            'owner_restricted' => $visible->owner_id !== null && $owner === null,
            'capacity' => $this->capacity($visible, $user, $team),
            'owner_name' => $owner?->name,
            'pic_user_id' => $pic?->id,
            'pic_name' => $pic?->name,
            'pic_phone_number' => $pic?->phone_number,
            'team_name' => $team->name,
            'progress_overview' => $this->progressOverview($visible, $user, $team),
            'active_cycles' => (clone $cycles)->count(),
            'active_area_ha' => (string) (clone $cycles)->sum('planted_area_ha'),
            'default_sop' => $this->sops->defaultForFarm($visible),
            'created_at' => $visible->created_at?->toISOString(),
        ];
    }

    /** @return array{total: int, stages: list<array{code: string, count: int}>} */
    public function progressOverview(Farm $farm, User $actor, Team $team): array
    {
        $blocks = \App\Models\FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->select('id');
        $rounds = \App\Models\PlantingRound::visibleTo($actor, $team)->whereIn('block_id', $blocks)->where('status', 'active')->select('id');
        $batches = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereIn('planting_round_id', $rounds)
            ->whereIn('status', ['planned', 'active', 'harvested'])->get(['id', 'status']);
        $stages = \App\Models\PlantingCycleStage::visibleTo($actor, $team)->whereIn('planting_cycle_id', $batches->modelKeys())
            ->where('status', 'in_progress')->orderBy('position')->get(['planting_cycle_id', 'code'])->keyBy('planting_cycle_id');
        $counts = array_fill_keys([...\App\Models\PlantingCycleStage::CODES, 'not_started'], 0);
        foreach ($batches as $batch) {
            $code = $batch->status === 'harvested' ? 'harvest' : ($stages->get($batch->id)?->getAttribute('code') ?? 'not_started');
            $counts[$code]++;
        }

        return ['total' => $batches->count(), 'stages' => array_values(collect($counts)->map(fn (int $count, string $code): array => ['code' => $code, 'count' => $count])->all())];
    }

    /** @param array<string, mixed> $data Validated farm attributes. */
    public function create(array $data, User|string $user, ?Team $team = null): Farm
    {
        $actor = is_string($user) ? User::query()->findOrFail($user) : $user;

        return DB::transaction(function () use ($data, $actor, $team): Farm {
            $farm = new Farm(\Illuminate\Support\Arr::except($data, ['commodity_id', 'target_volume_ton', 'pic_user_id']));
            $farm->forceFill(\Illuminate\Support\Arr::only($data, ['commodity_id', 'target_volume_ton']));
            if (array_key_exists('district_code', $data)) {
                $farm->forceFill(['district_code' => $data['district_code']]);
            }
            if ($team instanceof Team) {
                $farm->team()->associate($team);
            }
            $farm->created_by = $actor->getKey();
            $farm->save();

            $this->savePic($farm, $data);

            return $farm->refresh();
        });
    }

    public function find(string $id): Farm
    {
        return Farm::query()->findOrFail($id);
    }

    /** @param array<string, mixed> $data Validated farm attributes. */
    public function update(Farm $farm, array $data): Farm
    {
        return DB::transaction(function () use ($farm, $data): Farm {
            $locked = Farm::query()->lockForUpdate()->findOrFail($farm->id);
            if (isset($data['total_area_ha'])) {
                $blockCapacity = DB::table('farm_blocks')->where('farm_id', $locked->id)->where('is_active', true)
                    ->selectRaw('COALESCE(SUM(area_ha), 0) > CAST(? AS numeric) AS exceeds', [$data['total_area_ha']])->value('exceeds');
                if ($blockCapacity) {
                    throw ValidationException::withMessages(['total_area_ha' => 'Luas kebun tidak boleh kurang dari total luas blok aktif.']);
                }
                $reservationCapacity = DB::table('planting_cycles')->where('farm_id', $locked->id)
                    ->where(fn ($query) => $query->where(fn ($legacy) => $legacy->whereNull('planting_round_id')->where('status', 'active'))
                        ->orWhere(fn ($batch) => $batch->whereNotNull('planting_round_id')->whereIn('status', ['planned', 'active'])))
                    ->selectRaw('COALESCE(SUM(CASE WHEN planting_round_id IS NULL THEN planted_area_ha ELSE reserved_area_ha END), 0) > CAST(? AS numeric) AS exceeds', [$data['total_area_ha']])->value('exceeds');
                if ($reservationCapacity) {
                    throw ValidationException::withMessages(['total_area_ha' => 'Luas kebun tidak boleh kurang dari total alokasi tanam berjalan.']);
                }
                $exceedsArea = DB::table('planting_cycles')->where('farm_id', $locked->id)
                    ->where('status', 'active')
                    ->selectRaw('COALESCE(SUM(planted_area_ha), 0) > CAST(? AS numeric) AS exceeds_area', [$data['total_area_ha']])
                    ->value('exceeds_area');
                if ($exceedsArea) {
                    throw ValidationException::withMessages(['total_area_ha' => 'Luas kebun tidak boleh lebih kecil dari total luas tanam aktif.']);
                }
            }
            $locked->fill(\Illuminate\Support\Arr::except($data, ['commodity_id', 'target_volume_ton']));
            $locked->forceFill(\Illuminate\Support\Arr::only($data, ['commodity_id', 'target_volume_ton']));
            if (array_key_exists('district_code', $data)) {
                $locked->forceFill(['district_code' => $data['district_code']]);
            }
            $locked->save();

            $this->savePic($locked, $data);

            return $locked->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function savePic(Farm $farm, array $data): void
    {
        if (! array_key_exists('pic_user_id', $data)) {
            return;
        }
        $id = $data['pic_user_id'] ?: null;
        if ($id !== null) {
            $pic = User::query()->whereKey($id)->lockForUpdate()->first();
            if (! $pic || ! $pic->is_active || ! $pic->role()->where('name', 'petugas')->exists()
                || ! $pic->teams()->whereKey($farm->team_id)->exists()
                || ($pic->assignedFarmId() !== null && $pic->assignedFarmId() !== $farm->id)) {
                throw ValidationException::withMessages(['pic_user_id' => 'Pilih petugas aktif dalam tenant ini yang belum ditugaskan atau sudah ditugaskan ke kebun ini.']);
            }
            $pic->forceFill(['assigned_farm_id' => $farm->id])->save();
        }
        $farm->forceFill(['pic_user_id' => $id])->save();
    }

    public function delete(Farm $farm): void
    {
        try {
            DB::transaction(function () use ($farm): void {
                $locked = Farm::query()->lockForUpdate()->findOrFail($farm->id);
                $blockers = [];
                // Integrity checks include inactive/soft-deleted assignments. They must
                // not disappear through user visibility or soft-delete scopes.
                $hasAssignments = DB::table('users')->where('assigned_farm_id', $locked->id)->exists();
                if ($hasAssignments) {
                    $blockers[] = 'penugasan Petugas';
                }
                foreach ([
                    'farm_blocks' => 'blok kebun',
                    'planting_cycles' => 'siklus tanam',
                    'daily_activity_logs' => 'logbook harian',
                    'farm_activities' => 'aktivitas kebun',
                    'weather_records' => 'riwayat cuaca',
                    'financial_transactions' => 'transaksi keuangan',
                    'financial_projections' => 'proyeksi keuangan',
                    'financial_budgets' => 'anggaran keuangan',
                    'operational_targets' => 'target operasional',
                ] as $table => $label) {
                    if (DB::table($table)->where('farm_id', $locked->id)->exists()) {
                        $blockers[] = $label;
                    }
                }
                if ($blockers !== []) {
                    $message = 'Kebun tidak dapat dihapus karena masih memiliki: '.implode(', ', $blockers).'.';
                    if ($hasAssignments) {
                        $message .= ' Pindahkan atau lepaskan penugasan Petugas terlebih dahulu.';
                    }
                    if (count($blockers) > ($hasAssignments ? 1 : 0)) {
                        $message .= ' Histori operasional tetap dipertahankan.';
                    }
                    throw new ConflictHttpException($message);
                }
                $locked->delete();
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23503') {
                throw $exception;
            }

            throw new ConflictHttpException('Kebun masih memiliki data terkait dan tidak dapat dihapus.', $exception);
        }
    }
}

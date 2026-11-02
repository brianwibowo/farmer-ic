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
    /**
     * @param  array<string, mixed>  $filters  Validated list parameters.
     * @return LengthAwarePaginator<int, Farm>
     */
    public function paginate(User $user, Team $team, array $filters): LengthAwarePaginator
    {
        $query = Farm::visibleTo($user, $team);
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

    /** @return array<string, mixed> */
    public function detail(Farm $farm, User $user): array
    {
        $team = $farm->team;
        abort_unless($team instanceof Team, 403);
        $visible = Farm::visibleTo($user, $team)->findOrFail($farm->id);
        $cycles = PlantingCycle::visibleTo($user, $team)->where('farm_id', $visible->id)->where('status', 'active');
        $owner = User::visibleTo($user, $team)->whereKey($visible->owner_id)->first(['id', 'name']);

        return [
            ...$visible->only(['id', 'name', 'land_tenure', 'owner_id', 'address', 'city', 'district', 'province', 'total_area_ha', 'latitude', 'longitude', 'image_url']),
            'owner_name' => $owner?->name,
            'team_name' => $team->name,
            'active_cycles' => (clone $cycles)->count(),
            'active_area_ha' => (string) (clone $cycles)->sum('planted_area_ha'),
            'created_at' => $visible->created_at?->toISOString(),
        ];
    }

    /** @param array<string, mixed> $data Validated farm attributes. */
    public function create(array $data, User|string $user, ?Team $team = null): Farm
    {
        $actor = is_string($user) ? User::query()->findOrFail($user) : $user;
        $farm = new Farm($data);
        if ($team instanceof Team) {
            $farm->team()->associate($team);
        }
        $farm->created_by = $actor->getKey();
        $farm->save();

        return $farm->refresh();
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
            $locked->fill($data)->save();

            return $locked->refresh();
        });
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

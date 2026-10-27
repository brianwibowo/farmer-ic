<?php

namespace App\Services;

use App\Models\Farm;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FarmService
{
    /**
     * @param  array<string, mixed>  $filters  Validated list parameters.
     * @return LengthAwarePaginator<int, Farm>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Farm::query();
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

    /** @param array<string, mixed> $data Validated farm attributes. */
    public function create(array $data, string $createdBy): Farm
    {
        $farm = new Farm($data);
        $farm->created_by = $createdBy;
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
                foreach (['planting_cycles', 'farm_activities', 'weather_records', 'financial_transactions', 'financial_projections', 'financial_budgets', 'operational_targets'] as $table) {
                    if (DB::table($table)->where('farm_id', $locked->id)->exists()) {
                        throw new ConflictHttpException('Kebun masih memiliki data terkait dan tidak dapat dihapus.');
                    }
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

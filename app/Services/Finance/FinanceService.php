<?php

namespace App\Services\Finance;

use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FinancialBudget;
use App\Models\FinancialCategory;
use App\Models\FinancialProjection;
use App\Models\FinancialTransaction;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinanceService
{
    /** @param array<string, mixed> $filters
     * @return Builder<FinancialTransaction>
     */
    public function transactions(User $user, Team $team, array $filters): Builder
    {
        $query = FinancialTransaction::visibleTo($user, $team)
            ->whereHas('farm', fn ($farms) => $farms->where('team_id', $team->id));
        foreach (['farm_id', 'category_id', 'planting_cycle_id', 'transaction_type'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['commodity'])) {
            $query->whereHas('plantingCycle', fn ($cycles) => $cycles->visibleTo($user, $team)
                ->whereHas('variety', fn ($varieties) => $varieties->visibleTo($user, $team)
                    ->whereHas('commodity', fn ($commodities) => $commodities->visibleTo($user, $team)
                        ->where('name', $filters['commodity']))));
        }
        if (! empty($filters['from_date'])) {
            $query->where('transaction_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->where('transaction_date', '<=', $filters['to_date']);
        }
        $this->search($query, $filters, 'description');

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, FinancialTransaction>
     */
    public function paginateTransactions(User $user, Team $team, array $filters): LengthAwarePaginator
    {
        return $this->ordered($this->transactions($user, $team, $filters), $filters,
            ['transaction_date', 'amount', 'created_at'], 'transaction_date')
            ->paginate($filters['per_page'] ?? 15)->appends($filters);
    }

    public function transaction(User $user, Team $team, FinancialTransaction $transaction): FinancialTransaction
    {
        return $this->transactions($user, $team, [])->findOrFail($transaction->id);
    }

    /** @return Collection<int, Farm> */
    public function farms(User $user, Team $team): Collection
    {
        return Farm::visibleTo($user, $team)->where('team_id', $team->id)->orderBy('name')->get();
    }

    /** @return array<string, mixed> */
    public function formOptions(User $user, Team $team): array
    {
        return [
            'categories' => FinancialCategory::visibleTo($user, $team)->orderBy('name')->get(['id', 'name', 'transaction_type']),
            'cycles' => PlantingCycle::visibleTo($user, $team)->whereHas('farm', fn ($farms) => $farms->where('team_id', $team->id))
                ->with(['variety' => fn ($varieties) => $varieties->visibleTo($user, $team)])
                ->orderByDesc('planted_at')->get()->map(function (PlantingCycle $cycle): array {
                    $variety = $cycle->getRelation('variety');

                    return [
                        'id' => $cycle->id, 'farm_id' => $cycle->farm_id,
                        'label' => ($variety instanceof CropVariety ? $variety->name : 'Siklus tanam').' · '.$cycle->planted_at->toDateString(),
                    ];
                }),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function cashflow(User $user, Team $team, array $filters): array
    {
        $today = CarbonImmutable::now('Asia/Jakarta');
        $year = (int) ($filters['year'] ?? $today->year);
        $period = $filters['period'] ?? 'YTD';
        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta');
        $end = $start->endOfYear();
        if ($period !== 'YTD') {
            $start = $start->addMonths(((int) substr($period, 1) - 1) * 3);
            $end = $start->endOfQuarter();
        } elseif ($year === $today->year) {
            $end = $today;
        }
        $filters = array_replace($filters, [
            'year' => $year, 'period' => $period,
            'from_date' => $start->toDateString(), 'to_date' => $end->toDateString(),
            'tab' => $filters['tab'] ?? 'chart',
        ]);

        $transactions = $this->ordered($this->transactions($user, $team, $filters), $filters,
            ['transaction_date', 'amount', 'created_at'], 'transaction_date')
            ->with([
                'farm' => fn ($farms) => $farms->visibleTo($user, $team),
                'category' => fn ($categories) => $categories->visibleTo($user, $team),
                'plantingCycle' => fn ($cycles) => $cycles->visibleTo($user, $team),
                'plantingCycle.variety' => fn ($varieties) => $varieties->visibleTo($user, $team),
                'plantingCycle.variety.commodity' => fn ($commodities) => $commodities->visibleTo($user, $team),
            ])->paginate($filters['per_page'] ?? 15)->appends($filters)
            ->through(fn (FinancialTransaction $transaction): array => [
                'id' => $transaction->id,
                'description' => $transaction->description ?? '',
                'category' => $transaction->getRelation('category') instanceof FinancialCategory ? $transaction->getRelation('category')->name : 'Kategori tidak tersedia',
                'commodity' => $transaction->plantingCycle?->variety?->commodity?->name,
                'farm_id' => $transaction->farm_id,
                'farm_name' => $transaction->farm?->name,
                'transaction_date' => $transaction->transaction_date->toDateString(),
                'date' => [
                    'day' => $transaction->transaction_date->format('d'),
                    'month' => $transaction->transaction_date->settings(['locale' => 'id'])->translatedFormat('M'),
                    'year' => $transaction->transaction_date->format('Y'),
                ],
                'amount' => $transaction->amount,
                'type' => $transaction->transaction_type,
            ]);

        return [
            'summary' => $this->summary($user, $team, $filters),
            'transactions' => $transactions,
            'farms' => $this->farms($user, $team)->map(fn (Farm $farm): array => $farm->only(['id', 'name', 'city'])),
            'commodities' => Commodity::visibleTo($user, $team)->whereHas('varieties', fn ($varieties) => $varieties
                ->visibleTo($user, $team)->whereHas('plantingCycles', fn ($cycles) => $cycles
                ->visibleTo($user, $team)->whereHas('farm', fn ($farms) => $farms->where('team_id', $team->id))))
                ->orderBy('name')->pluck('name'),
            'filters' => $filters,
            'periodLabel' => $start->settings(['locale' => 'id'])->translatedFormat('j F').'–'.$end->settings(['locale' => 'id'])->translatedFormat('j F Y'),
            'exportUrl' => route('finance.transactions.export', $filters),
        ];
    }

    /** @param array<string, mixed> $data */
    public function saveTransaction(User $user, Team $team, Farm $farm, array $data, ?FinancialTransaction $transaction = null): FinancialTransaction
    {
        return DB::transaction(function () use ($user, $team, $farm, $data, $transaction): FinancialTransaction {
            $record = $transaction === null ? new FinancialTransaction : $this->transactions($user, $team, [])->lockForUpdate()->findOrFail($transaction->id);
            $this->cycle($user, $team, $farm, array_key_exists('planting_cycle_id', $data) ? $data['planting_cycle_id'] : $record->planting_cycle_id);
            $categoryId = $data['category_id'] ?? $record->category_id;
            $type = $data['transaction_type'] ?? $record->transaction_type;
            $this->category($user, $team, $categoryId, $type);
            $record->fill($data);
            $record->farm_id = $farm->id;
            if (! $record->exists) {
                $record->recorded_by = $user->id;
            }
            $record->save();

            return $record->refresh();
        });
    }

    public function deleteTransaction(User $user, Team $team, FinancialTransaction $transaction): void
    {
        DB::transaction(fn () => $this->transactions($user, $team, [])->lockForUpdate()->findOrFail($transaction->id)->delete());
    }

    /** @param array<string, mixed> $filters
     * @return Builder<FinancialCategory>
     */
    private function categories(User $user, Team $team, array $filters = []): Builder
    {
        $query = FinancialCategory::visibleTo($user, $team);
        if (! empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }
        $this->search($query, $filters, 'name');

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, FinancialCategory>
     */
    public function paginateCategories(User $user, Team $team, array $filters): LengthAwarePaginator
    {
        return $this->ordered($this->categories($user, $team, $filters), $filters,
            ['name', 'transaction_type'], 'name', 'asc')->paginate($filters['per_page'] ?? 15)->appends($filters);
    }

    public function findCategory(User $user, Team $team, FinancialCategory $category): FinancialCategory
    {
        return $this->categories($user, $team)->findOrFail($category->id);
    }

    /** @param array<string, mixed> $data */
    public function saveCategory(User $user, Team $team, array $data, ?FinancialCategory $category = null): FinancialCategory
    {
        try {
            return DB::transaction(function () use ($user, $team, $data, $category): FinancialCategory {
                $record = $category === null ? new FinancialCategory : $this->categories($user, $team)->lockForUpdate()->findOrFail($category->id);
                if ($record->exists && isset($data['transaction_type']) && $data['transaction_type'] !== $record->transaction_type
                    && FinancialTransaction::query()->where('category_id', $record->id)->exists()) {
                    throw ValidationException::withMessages(['transaction_type' => 'Jenis kategori yang sudah digunakan transaksi tidak dapat diubah.']);
                }
                $record->fill($data)->save();

                return $record->refresh();
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }
            throw ValidationException::withMessages(['name' => 'Kategori dengan nama dan jenis transaksi tersebut sudah tersedia.']);
        }
    }

    public function deleteCategory(User $user, Team $team, FinancialCategory $category): void
    {
        try {
            DB::transaction(function () use ($user, $team, $category): void {
                $record = $this->categories($user, $team)->lockForUpdate()->findOrFail($category->id);
                // Shared catalog: preserve references in every team's history.
                if (FinancialTransaction::query()->where('category_id', $record->id)->exists()
                    || FinancialBudget::query()->where('category_id', $record->id)->exists()) {
                    throw new ConflictHttpException('Kategori masih digunakan oleh transaksi atau anggaran.');
                }
                $record->delete();
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23503') {
                throw $exception;
            }
            throw new ConflictHttpException('Kategori masih digunakan oleh data keuangan.', $exception);
        }
    }

    /** @param array<string, mixed> $filters
     * @return Builder<FinancialBudget>
     */
    private function budgets(User $user, Team $team, array $filters = []): Builder
    {
        $query = FinancialBudget::visibleTo($user, $team)
            ->whereHas('farm', fn ($farms) => $farms->where('team_id', $team->id));
        $this->periodFilters($query, $filters);
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, FinancialBudget>
     */
    public function paginateBudgets(User $user, Team $team, array $filters): LengthAwarePaginator
    {
        return $this->ordered($this->budgets($user, $team, $filters), $filters,
            ['period_start', 'period_end', 'budget_amount', 'created_at'], 'period_start')
            ->paginate($filters['per_page'] ?? 15)->appends($filters);
    }

    public function budget(User $user, Team $team, FinancialBudget $budget): FinancialBudget
    {
        return $this->budgets($user, $team)->findOrFail($budget->id);
    }

    /** @param array<string, mixed> $data */
    public function saveBudget(User $user, Team $team, Farm $farm, array $data, ?FinancialBudget $budget = null): FinancialBudget
    {
        return DB::transaction(function () use ($user, $team, $farm, $data, $budget): FinancialBudget {
            $record = $budget === null ? new FinancialBudget : $this->budgets($user, $team)->lockForUpdate()->findOrFail($budget->id);
            $this->period($data, $record);
            $categoryId = array_key_exists('category_id', $data) ? $data['category_id'] : $record->category_id;
            if ($categoryId !== null) {
                $this->category($user, $team, $categoryId);
            }
            $record->fill($data);
            $record->farm_id = $farm->id;
            if (! $record->exists) {
                $record->created_by = $user->id;
            }
            $record->save();

            return $record->refresh();
        });
    }

    public function deleteBudget(User $user, Team $team, FinancialBudget $budget): void
    {
        DB::transaction(fn () => $this->budgets($user, $team)->lockForUpdate()->findOrFail($budget->id)->delete());
    }

    /** @param array<string, mixed> $filters
     * @return Builder<FinancialProjection>
     */
    private function projections(User $user, Team $team, array $filters = []): Builder
    {
        $query = FinancialProjection::visibleTo($user, $team)
            ->whereHas('farm', fn ($farms) => $farms->where('team_id', $team->id));
        $this->periodFilters($query, $filters);
        if (! empty($filters['planting_cycle_id'])) {
            $query->where('planting_cycle_id', $filters['planting_cycle_id']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, FinancialProjection>
     */
    public function paginateProjections(User $user, Team $team, array $filters): LengthAwarePaginator
    {
        return $this->ordered($this->projections($user, $team, $filters), $filters,
            ['period_start', 'period_end', 'projected_income', 'projected_expense', 'calculated_at'], 'period_start')
            ->paginate($filters['per_page'] ?? 15)->appends($filters);
    }

    public function projection(User $user, Team $team, FinancialProjection $projection): FinancialProjection
    {
        return $this->projections($user, $team)->findOrFail($projection->id);
    }

    /** @param array<string, mixed> $data */
    public function saveProjection(User $user, Team $team, Farm $farm, array $data, ?FinancialProjection $projection = null): FinancialProjection
    {
        return DB::transaction(function () use ($user, $team, $farm, $data, $projection): FinancialProjection {
            $record = $projection === null ? new FinancialProjection : $this->projections($user, $team)->lockForUpdate()->findOrFail($projection->id);
            $this->period($data, $record);
            $this->cycle($user, $team, $farm, array_key_exists('planting_cycle_id', $data) ? $data['planting_cycle_id'] : $record->planting_cycle_id);
            $record->fill($data);
            if ($record->projected_income === null && $record->projected_expense === null) {
                throw ValidationException::withMessages(['projected_income' => 'Isi sedikitnya satu nilai proyeksi pemasukan atau pengeluaran.']);
            }
            $record->farm_id = $farm->id;
            $record->calculated_at = now();
            $record->save();

            return $record->refresh();
        });
    }

    public function deleteProjection(User $user, Team $team, FinancialProjection $projection): void
    {
        DB::transaction(fn () => $this->projections($user, $team)->lockForUpdate()->findOrFail($projection->id)->delete());
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summary(User $user, Team $team, array $filters): array
    {
        // SUM and subtraction stay in PostgreSQL numeric arithmetic, including values > 2^53.
        $query = $this->transactions($user, $team, $filters);
        $totals = (clone $query)->selectRaw("COUNT(*) AS transaction_count,
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0)::numeric(38,2)::text AS income,
            COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0)::numeric(38,2)::text AS expense,
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END), 0)::numeric(38,2)::text AS net")->first();
        $monthly = (clone $query)->selectRaw("TO_CHAR(transaction_date, 'YYYY-MM') AS month,
            COUNT(*) AS transaction_count,
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0)::numeric(38,2)::text AS income,
            COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0)::numeric(38,2)::text AS expense,
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END), 0)::numeric(38,2)::text AS net")
            ->groupByRaw("TO_CHAR(transaction_date, 'YYYY-MM')")->orderBy('month')->get();

        return [
            'source' => 'database',
            'currency' => 'IDR',
            'transaction_count' => (int) $totals->getAttribute('transaction_count'),
            'income' => $totals->getAttribute('income'),
            'expense' => $totals->getAttribute('expense'),
            'net' => $totals->getAttribute('net'),
            'monthly' => $monthly->map(fn (FinancialTransaction $row): array => [
                'month' => $row->getAttribute('month'),
                'transaction_count' => (int) $row->getAttribute('transaction_count'),
                'income' => $row->getAttribute('income'),
                'expense' => $row->getAttribute('expense'),
                'net' => $row->getAttribute('net'),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return \Generator<int, array<mixed>>
     */
    public function transactionExport(User $user, Team $team, array $filters): \Generator
    {
        $query = $this->ordered($this->transactions($user, $team, $filters), $filters,
            ['transaction_date', 'amount', 'created_at'], 'transaction_date');
        foreach ($query->cursor() as $transaction) {
            $description = $transaction->description ?? '';
            if (preg_match('/^[\s]*[=+@-]/u', $description)) {
                $description = "'".$description;
            }
            yield [$transaction->id, $transaction->farm_id, $transaction->planting_cycle_id,
                $transaction->category_id, $transaction->transaction_date->toDateString(),
                $transaction->transaction_type, $transaction->amount, $description];
        }
    }

    private function category(User $user, Team $team, string $id, ?string $type = null): void
    {
        $query = $this->categories($user, $team)->whereKey($id);
        if ($type !== null) {
            $query->where('transaction_type', $type);
        }
        if ($query->sharedLock()->first() === null) {
            throw ValidationException::withMessages(['category_id' => 'Kategori tidak tersedia atau jenisnya tidak sesuai transaksi.']);
        }
    }

    private function cycle(User $user, Team $team, Farm $farm, ?string $id): void
    {
        if ($id !== null && PlantingCycle::visibleTo($user, $team)->where('farm_id', $farm->id)->whereKey($id)->sharedLock()->first() === null) {
            throw ValidationException::withMessages(['planting_cycle_id' => 'Siklus tanam harus tersedia dan berasal dari kebun yang sama.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function period(array $data, FinancialBudget|FinancialProjection $record): void
    {
        $start = $data['period_start'] ?? ($record->exists ? $record->period_start->toDateString() : null);
        $end = $data['period_end'] ?? ($record->exists ? $record->period_end->toDateString() : null);
        if ($start === null || $end === null || $end < $start) {
            throw ValidationException::withMessages(['period_end' => 'Akhir periode harus sama dengan atau setelah awal periode.']);
        }
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @param  array<string, mixed>  $filters
     */
    private function periodFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['farm_id'])) {
            $query->where('farm_id', $filters['farm_id']);
        }
        // Plans are included when their period overlaps the requested range.
        if (! empty($filters['from_date'])) {
            $query->where('period_end', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->where('period_start', '<=', $filters['to_date']);
        }
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @param  array<string, mixed>  $filters
     */
    private function search(Builder $query, array $filters, string $column): void
    {
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where($column, 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%');
        }
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     * @return Builder<T>
     */
    private function ordered(Builder $query, array $filters, array $columns, string $default, string $direction = 'desc'): Builder
    {
        $sort = in_array($filters['sort'] ?? '', $columns, true) ? $filters['sort'] : $default;

        $order = ($filters['direction'] ?? $direction) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $order)->orderBy('id');
    }
}

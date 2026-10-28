<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\FinancialBudget;
use App\Models\FinancialCategory;
use App\Models\FinancialProjection;
use App\Models\FinancialTransaction;
use App\Models\PlantingCycle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class DemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Data finance demo hanya dapat diisi pada environment local atau testing.');
        }

        $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->first();
        $team = $admin?->teams()->where('slug', 'farm-ic-demo')->first();
        if ($admin === null || $team === null) {
            throw new RuntimeException('Identitas demo belum tersedia. Jalankan DemoIdentitySeeder lalu DemoFarmSeeder terlebih dahulu.');
        }

        DB::transaction(function () use ($admin, $team): void {
            // Serialize reruns against this named demo farm, never another team's farm.
            $farm = Farm::visibleTo($admin, $team)->where('team_id', $team->id)
                ->where('name', 'Kebun Cileuksa Demo')->lockForUpdate()->first();
            $cycle = $farm === null ? null : PlantingCycle::visibleTo($admin, $team)
                ->where('farm_id', $farm->id)->whereDate('planted_at', '2026-10-01')->orderBy('id')->first();
            if ($farm === null || $cycle === null) {
                throw new RuntimeException('Kebun atau siklus tanam demo belum tersedia. Jalankan DemoFarmSeeder terlebih dahulu.');
            }

            // A seeder is a non-HTTP entry point: authorize the persisted actor/parents.
            foreach ([FinancialTransaction::class, FinancialBudget::class, FinancialProjection::class, FinancialCategory::class] as $model) {
                Gate::forUser($admin)->authorize('viewAny', [$model, $team]);
                Gate::forUser($admin)->authorize('create', [$model, $model === FinancialCategory::class ? $team : $farm]);
            }

            $categories = [];
            foreach ($this->categories() as $name => $type) {
                $categories[$name] = FinancialCategory::visibleTo($admin, $team)->firstOrCreate([
                    'name' => $name, 'transaction_type' => $type,
                ]);
            }

            foreach ($this->transactions() as [$date, $category, $amount, $description]) {
                $catalog = $categories[$category];
                // Natural keys retain the three original fixtures and preserve edits on reruns.
                FinancialTransaction::visibleTo($admin, $team)->firstOrCreate([
                    'farm_id' => $farm->id,
                    'category_id' => $catalog->id,
                    'transaction_date' => $date,
                    'description' => $description,
                ], [
                    // Historical farm-level records do not claim to belong to October's crop.
                    'planting_cycle_id' => $date >= '2026-10-01' && $catalog->transaction_type === 'expense' ? $cycle->id : null,
                    'recorded_by' => $admin->id,
                    'transaction_type' => $catalog->transaction_type,
                    'amount' => $amount,
                    'created_at' => CarbonImmutable::parse($date.' 09:00:00', 'Asia/Jakarta'),
                ]);
            }

            foreach ($this->budgets() as [$category, $start, $end, $amount]) {
                FinancialBudget::visibleTo($admin, $team)->firstOrCreate([
                    'farm_id' => $farm->id,
                    'category_id' => $categories[$category]->id,
                    'period_start' => $start,
                    'period_end' => $end,
                ], [
                    'budget_amount' => $amount,
                    'created_by' => $admin->id,
                    'created_at' => CarbonImmutable::parse($start.' 08:00:00', 'Asia/Jakarta'),
                ]);
            }

            foreach ($this->projections() as [$start, $end, $income, $expense]) {
                FinancialProjection::visibleTo($admin, $team)->firstOrCreate([
                    'farm_id' => $farm->id,
                    'planting_cycle_id' => $start === '2026-10-01' ? $cycle->id : null,
                    'period_start' => $start,
                    'period_end' => $end,
                ], [
                    'projected_income' => $income,
                    'projected_expense' => $expense,
                    'calculated_at' => CarbonImmutable::parse($start.' 08:00:00', 'Asia/Jakarta'),
                ]);
            }
        });
    }

    /** @return array<string, string> */
    private function categories(): array
    {
        return [
            'Benih' => 'expense',
            'Pupuk' => 'expense',
            'Tenaga kerja' => 'expense',
            'Pengendalian hama' => 'expense',
            'Irigasi' => 'expense',
            'Transportasi' => 'expense',
            'Sewa alat' => 'expense',
            'Pemeliharaan' => 'expense',
            'Penjualan hasil panen' => 'income',
            'Penjualan bibit' => 'income',
        ];
    }

    /** @return list<array{string, string, string, string}> */
    private function transactions(): array
    {
        return [
            ['2026-08-02', 'Benih', '1500000.00', 'Pembelian benih untuk persemaian musim sebelumnya'],
            ['2026-08-05', 'Pupuk', '2800000.00', 'Pembelian pupuk kandang dan NPK periode Agustus'],
            ['2026-08-08', 'Tenaga kerja', '2100000.00', 'Upah penyiangan dan perawatan lahan periode Agustus'],
            ['2026-08-12', 'Irigasi', '450000.00', 'Pembelian bahan bakar pompa irigasi periode Agustus'],
            ['2026-08-18', 'Pengendalian hama', '675000.00', 'Pembelian perangkap dan pestisida untuk perawatan lahan'],
            ['2026-08-22', 'Transportasi', '850000.00', 'Angkutan hasil panen ke pengepul wilayah Bogor'],
            ['2026-08-25', 'Penjualan hasil panen', '18750000.00', 'Penjualan jagung musim sebelumnya kepada pengepul Bogor'],
            ['2026-08-28', 'Penjualan bibit', '1250000.00', 'Penjualan bibit surplus persemaian kepada kelompok tani'],
            ['2026-09-03', 'Benih', '1650000.00', 'Pembelian benih untuk persemaian periode September'],
            ['2026-09-06', 'Pupuk', '3050000.00', 'Pembelian pupuk organik dan pupuk susulan September'],
            ['2026-09-10', 'Tenaga kerja', '2400000.00', 'Upah pemeliharaan lahan dan sortasi hasil panen September'],
            ['2026-09-14', 'Sewa alat', '1200000.00', 'Sewa traktor untuk persiapan lahan tanam berikutnya'],
            ['2026-09-18', 'Irigasi', '525000.00', 'Operasional pompa dan pembelian sambungan selang irigasi'],
            ['2026-09-23', 'Pemeliharaan', '350000.00', 'Perbaikan cangkul dan perawatan alat semprot kebun'],
            ['2026-09-25', 'Penjualan hasil panen', '22500000.00', 'Pelunasan penjualan jagung musim sebelumnya periode September'],
            ['2026-09-29', 'Penjualan bibit', '1675000.00', 'Penjualan bibit siap tanam kepada petani sekitar Cileuksa'],
            ['2026-10-01', 'Benih', '1850000.00', 'Pembelian benih jagung Bisi 18 untuk siklus demo'],
            ['2026-10-03', 'Sewa alat', '1450000.00', 'Sewa alat pengolahan lahan dan pembentukan bedengan jagung'],
            ['2026-10-07', 'Tenaga kerja', '2700000.00', 'Upah penanaman dan penyulaman jagung Bisi 18'],
            ['2026-10-12', 'Irigasi', '600000.00', 'Operasional penyiraman awal siklus jagung Oktober'],
            ['2026-10-15', 'Pupuk', '3250000.00', 'Pembelian pupuk dasar NPK'],
            ['2026-10-19', 'Pengendalian hama', '725000.00', 'Pembelian perangkap ulat dan bahan pengendalian hama jagung'],
            ['2026-10-24', 'Transportasi', '950000.00', 'Angkutan benih dan sarana produksi ke Kebun Cileuksa'],
            ['2026-10-26', 'Penjualan hasil panen', '7250000.00', 'Penjualan hasil panen percobaan'],
        ];
    }

    /** @return list<array{string, string, string, string}> */
    private function budgets(): array
    {
        return [
            ['Pupuk', '2026-10-01', '2026-12-31', '6500000.00'],
            ['Benih', '2026-10-01', '2026-12-31', '2500000.00'],
            ['Tenaga kerja', '2026-10-01', '2026-12-31', '6000000.00'],
            ['Irigasi', '2026-10-01', '2026-12-31', '1500000.00'],
            ['Pengendalian hama', '2026-10-01', '2026-12-31', '1800000.00'],
            ['Transportasi', '2026-10-01', '2026-12-31', '2000000.00'],
        ];
    }

    /** @return list<array{string, string, string, string}> */
    private function projections(): array
    {
        return [
            ['2026-08-01', '2026-08-31', '21000000.00', '9000000.00'],
            ['2026-09-01', '2026-09-30', '25000000.00', '10000000.00'],
            ['2026-10-01', '2027-01-31', '48500000.00', '17800000.00'],
        ];
    }
}

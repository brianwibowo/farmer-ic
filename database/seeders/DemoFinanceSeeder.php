<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\FinancialBudget;
use App\Models\FinancialCategory;
use App\Models\FinancialProjection;
use App\Models\FinancialTransaction;
use App\Models\PlantingCycle;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $farm = Farm::query()->where('name', 'Kebun Cileuksa Demo')->firstOrFail();
        $cycle = PlantingCycle::query()->where('farm_id', $farm->id)->where('status', 'active')->firstOrFail();
        $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();

        $seed = FinancialCategory::query()->firstOrCreate(['name' => 'Benih', 'transaction_type' => 'expense']);
        $fertilizer = FinancialCategory::query()->firstOrCreate(['name' => 'Pupuk', 'transaction_type' => 'expense']);
        $sales = FinancialCategory::query()->firstOrCreate(['name' => 'Penjualan hasil panen', 'transaction_type' => 'income']);

        FinancialTransaction::query()->firstOrCreate([
            'farm_id' => $farm->id,
            'category_id' => $seed->id,
            'transaction_date' => '2026-10-01',
            'description' => 'Pembelian benih jagung Bisi 18 untuk siklus demo',
        ], [
            'planting_cycle_id' => $cycle->id,
            'recorded_by' => $admin->id,
            'transaction_type' => 'expense',
            'amount' => 1850000,
            'created_at' => now(),
        ]);
        FinancialTransaction::query()->firstOrCreate([
            'farm_id' => $farm->id,
            'category_id' => $fertilizer->id,
            'transaction_date' => '2026-10-15',
            'description' => 'Pembelian pupuk dasar NPK',
        ], [
            'planting_cycle_id' => $cycle->id,
            'recorded_by' => $admin->id,
            'transaction_type' => 'expense',
            'amount' => 3250000,
            'created_at' => now(),
        ]);
        FinancialTransaction::query()->firstOrCreate([
            'farm_id' => $farm->id,
            'category_id' => $sales->id,
            'transaction_date' => '2026-10-26',
            'description' => 'Penjualan hasil panen percobaan',
        ], [
            'recorded_by' => $admin->id,
            'transaction_type' => 'income',
            'amount' => 7250000,
            'created_at' => now(),
        ]);

        FinancialProjection::query()->firstOrCreate([
            'farm_id' => $farm->id,
            'planting_cycle_id' => $cycle->id,
            'period_start' => '2026-10-01',
            'period_end' => '2027-01-31',
        ], [
            'projected_income' => 48500000,
            'projected_expense' => 17800000,
            'calculated_at' => now(),
        ]);
        FinancialBudget::query()->firstOrCreate([
            'farm_id' => $farm->id,
            'category_id' => $fertilizer->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-12-31',
        ], [
            'budget_amount' => 6500000,
            'created_by' => $admin->id,
            'created_at' => now(),
        ]);
    }
}

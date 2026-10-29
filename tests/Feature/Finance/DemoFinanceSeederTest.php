<?php

namespace Tests\Feature\Finance;

use App\Models\Farm;
use App\Models\FinancialBudget;
use App\Models\FinancialCategory;
use App\Models\FinancialProjection;
use App\Models\FinancialTransaction;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoFinanceSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/** Real PostgreSQL fixtures in the separate, guarded test database. */
class DemoFinanceSeederTest extends TestCase
{
    use RefreshDatabase;

    private function prerequisites(): void
    {
        $this->seed([DemoIdentitySeeder::class, DemoFarmSeeder::class]);
    }

    public function test_complete_demo_seed_provides_finance_data_for_lists_and_summaries(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('financial_categories', 10);
        $this->assertDatabaseCount('financial_transactions', 24);
        $this->assertDatabaseCount('financial_budgets', 6);
        $this->assertDatabaseCount('financial_projections', 3);

        $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
        $this->actingAs($admin);
        $this->get('/finance/transactions?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farmer/FinanceRecords')->has('records.data', 10)->where('records.total', 24)->where('records.last_page', 3));
        $this->get('/finance?year=2026&period=YTD')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farmer/Finance')->where('summary.income', '51425000.00')->where('summary.expense', '29075000.00')
            ->where('summary.net', '22350000.00')->has('summary.monthly', 3));
        $this->get('/finance?year=2026&period=Q4')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.transaction_count', 8)->where('summary.income', '7250000.00'));
        $this->assertSame(7, FinancialTransaction::query()->whereNotNull('planting_cycle_id')->count());
        $this->assertSame(0, FinancialTransaction::query()->whereNotNull('planting_cycle_id')->where('transaction_date', '<', '2026-10-01')->count());
        foreach (FinancialTransaction::with('category')->get() as $transaction) {
            $this->assertSame($transaction->category->transaction_type, $transaction->transaction_type);
            $this->assertSame($admin->id, $transaction->recorded_by);
        }
    }

    public function test_rerunning_finance_preserves_existing_seed_ids_edits_and_unrelated_records(): void
    {
        $this->prerequisites();
        $farm = Farm::query()->where('name', 'Kebun Cileuksa Demo')->firstOrFail();
        $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
        $category = FinancialCategory::create(['name' => 'Benih', 'transaction_type' => 'expense']);
        // A row created by the earlier three-transaction seeder must be reused.
        $legacy = FinancialTransaction::create([
            'farm_id' => $farm->id, 'category_id' => $category->id, 'recorded_by' => $admin->id,
            'transaction_date' => '2026-10-01', 'description' => 'Pembelian benih jagung Bisi 18 untuk siklus demo',
            'transaction_type' => 'expense', 'amount' => '1900000.00',
        ]);
        $external = FinancialTransaction::create([
            'farm_id' => $farm->id, 'category_id' => $category->id, 'recorded_by' => $admin->id,
            'transaction_date' => '2026-10-28', 'description' => 'Pembelian tambahan dari pemasok lokal',
            'transaction_type' => 'expense', 'amount' => '275000.00',
        ]);
        $this->seed(DemoFinanceSeeder::class);
        $budget = FinancialBudget::query()->firstOrFail();
        $budget->update(['budget_amount' => '1234567.89']);
        $projection = FinancialProjection::query()->firstOrFail();
        $projection->update(['projected_income' => '2345678.90']);
        $ids = FinancialTransaction::query()->orderBy('id')->pluck('id')->all();

        $this->seed(DemoFinanceSeeder::class);
        $this->assertSame($ids, FinancialTransaction::query()->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('financial_transactions', 25);
        $this->assertDatabaseCount('financial_categories', 10);
        $this->assertDatabaseCount('financial_budgets', 6);
        $this->assertDatabaseCount('financial_projections', 3);
        $this->assertSame('1900000.00', $legacy->refresh()->amount);
        $this->assertSame('275000.00', $external->refresh()->amount);
        $this->assertSame('1234567.89', $budget->refresh()->budget_amount);
        $this->assertSame('2345678.90', $projection->refresh()->projected_income);
    }

    public function test_missing_prerequisites_fail_before_creating_finance_records(): void
    {
        try {
            $this->seed(DemoFinanceSeeder::class);
            $this->fail('Seeder should require persisted demo identity and farm.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('DemoIdentitySeeder', $exception->getMessage());
        }
        $this->assertDatabaseCount('financial_categories', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_same_named_farm_in_another_team_cannot_be_used_as_the_demo_parent(): void
    {
        $this->prerequisites();
        $farm = Farm::query()->where('name', 'Kebun Cileuksa Demo')->firstOrFail();
        $farm->forceFill(['team_id' => Team::factory()->create()->id])->save();
        try {
            $this->seed(DemoFinanceSeeder::class);
            $this->fail('Seeder should require the named farm inside the demo team.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('DemoFarmSeeder', $exception->getMessage());
        }
        $this->assertDatabaseCount('financial_categories', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_finance_seed_honors_the_native_policy_before_writing(): void
    {
        $this->prerequisites();
        $roles = app(RolePermissions::class)->roles();
        unset($roles['admin']['financial_budgets.create']);
        config(['access.roles' => $roles]);
        try {
            $this->seed(DemoFinanceSeeder::class);
            $this->fail('Seeder should authorize each financial parent before writing.');
        } catch (AuthorizationException $exception) {
            $this->assertFalse($exception->toResponse()->allowed());
        }
        $this->assertDatabaseCount('financial_categories', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_failed_fixture_rolls_back_the_entire_finance_batch(): void
    {
        $this->prerequisites();
        FinancialBudget::creating(function (): void {
            throw new RuntimeException('Simulated budget persistence failure.');
        });
        try {
            $this->seed(DemoFinanceSeeder::class);
            $this->fail('Expected the simulated persistence error.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated budget persistence failure.', $exception->getMessage());
        }
        $this->assertDatabaseCount('financial_categories', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('financial_budgets', 0);
        $this->assertDatabaseCount('financial_projections', 0);
    }

    public function test_finance_demo_seed_is_disabled_in_production(): void
    {
        $this->app->instance('env', 'production');
        try {
            // Exercise the seeder guard independently of Artisan's production prompt.
            app(DemoFinanceSeeder::class)->run();
            $this->fail('Production must not accept demo finance fixtures.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local atau testing', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('financial_categories', 0);
    }
}

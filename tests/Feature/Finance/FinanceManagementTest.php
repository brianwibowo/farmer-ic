<?php

namespace Tests\Feature\Finance;

use App\Enums\TeamRole;
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
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Inertia HTTP integration against the guarded, separate PostgreSQL test schema. */
class FinanceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    private Farm $farm;

    private FinancialCategory $income;

    private FinancialCategory $expense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create();
        $this->join($this->admin, $this->team);
        $this->farm = $this->farm($this->team);
        $this->income = FinancialCategory::create(['name' => 'Penjualan panen', 'transaction_type' => 'income']);
        $this->expense = FinancialCategory::create(['name' => 'Pupuk organik', 'transaction_type' => 'expense']);
        $this->actingAs($this->admin);
    }

    private function join(User $user, Team $team): void
    {
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    private function farm(Team $team): Farm
    {
        $farm = new Farm(['name' => 'Kebun Sumber Makmur '.Str::random(6), 'total_area_ha' => '12.5000']);
        $farm->forceFill(['team_id' => $team->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['category_id' => $this->income->id, 'transaction_date' => '2026-10-28',
            'transaction_type' => 'income', 'amount' => '7250000.15', 'description' => 'Penjualan jagung'];
    }

    private function transaction(?Farm $farm = null, string $amount = '7250000.15'): FinancialTransaction
    {
        return FinancialTransaction::create([...$this->payload(), 'farm_id' => ($farm ?? $this->farm)->id,
            'recorded_by' => $this->admin->id, 'amount' => $amount]);
    }

    private function cycle(Farm $farm): PlantingCycle
    {
        $commodity = Commodity::create(['name' => 'Jagung']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);

        return PlantingCycle::create(['farm_id' => $farm->id, 'variety_id' => $variety->id,
            'planted_at' => '2026-10-01', 'planted_area_ha' => '1.0000', 'status' => 'active']);
    }

    public function test_cashflow_is_an_inertia_page_with_scoped_database_props(): void
    {
        $transaction = $this->transaction();
        $this->get('/finance?year=2026&period=Q4')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farmer/Finance')
            ->where('summary.source', 'database')->where('summary.income', '7250000.15')
            ->where('summary.monthly.0.net', '7250000.15')
            ->where('transactions.total', 1)->where('transactions.data.0.id', $transaction->id)
            ->where('farms.0.id', $this->farm->id)->where('filters.period', 'Q4')
            ->where('navigation.finance.transactions', true)
            ->missing('transactions.data.0.password'));
        $this->get('/'.$this->team->slug.'/finance/summary')->assertNotFound();
        $this->get('/'.$this->team->slug.'/finance/transactions')->assertNotFound();
    }

    public function test_transaction_form_redirects_and_persists_exact_decimals_with_server_identity(): void
    {
        $cycle = $this->cycle($this->farm);
        $this->post('/finance/farms/'.$this->farm->id.'/transactions', [
            ...$this->payload(), 'planting_cycle_id' => $cycle->id, 'amount' => '9999999999999999.99',
        ])->assertRedirect('/finance/transactions');
        $transaction = FinancialTransaction::query()->firstOrFail();
        $this->assertSame($this->admin->id, $transaction->recorded_by);
        $this->assertSame($this->farm->id, $transaction->farm_id);
        $this->get('/finance/transactions/'.$transaction->id)->assertInertia(fn (Assert $page) => $page
            ->component('farmer/FinanceRecordDetail')->where('record.amount', '9999999999999999.99'));
        $this->patch('/finance/transactions/'.$transaction->id, ['amount' => '0.01', 'planting_cycle_id' => null])
            ->assertRedirect('/finance/transactions');
        $this->assertSame('0.01', $transaction->refresh()->amount);
        $this->assertNull($transaction->planting_cycle_id);
        $this->put('/finance/transactions/'.$transaction->id, ['amount' => '12'])->assertSessionHasErrors('category_id');
        $this->put('/finance/transactions/'.$transaction->id, $this->payload())->assertRedirect('/finance/transactions');
        $this->delete('/finance/transactions/'.$transaction->id)->assertRedirect('/finance/transactions');
        $this->assertDatabaseMissing('financial_transactions', ['id' => $transaction->id]);
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function plans(): array
    {
        return [
            'budget' => ['budgets', 'budget_amount', ['budget_amount' => '6500000.25']],
            'projection' => ['projections', 'projected_income', ['projected_income' => '48500000.10', 'projected_expense' => '17800000.05']],
        ];
    }

    #[DataProvider('plans')]
    public function test_plan_forms_validate_partial_periods_and_preserve_optional_links(string $resource, string $money, array $values): void
    {
        $payload = ['period_start' => '2026-10-01', 'period_end' => '2026-12-31', ...$values];
        $this->post('/finance/farms/'.$this->farm->id.'/'.$resource, $payload)->assertRedirect('/finance/'.$resource);
        $model = $resource === 'budgets' ? FinancialBudget::class : FinancialProjection::class;
        $id = $model::query()->firstOrFail()->getKey();
        $this->get('/finance/'.$resource.'?from_date=2026-11-01&to_date=2026-11-30')->assertInertia(fn (Assert $page) => $page
            ->component('farmer/FinanceRecords')->where('records.total', 1)->where('records.data.0.'.$money, $values[$money])
            ->where('can.create', true));
        $this->patch('/finance/'.$resource.'/'.$id, ['period_start' => '2027-01-01'])->assertSessionHasErrors('period_end');
        $this->assertSame('2026-10-01', $model::findOrFail($id)->period_start->toDateString());
        $this->patch('/finance/'.$resource.'/'.$id, [$money => '0'])->assertRedirect('/finance/'.$resource);
        $this->assertSame('0.00', $model::findOrFail($id)->getAttribute($money));
        $this->put('/finance/'.$resource.'/'.$id, $payload)->assertRedirect('/finance/'.$resource);
        $this->delete('/finance/'.$resource.'/'.$id)->assertRedirect('/finance/'.$resource);
        $this->assertDatabaseMissing((new $model)->getTable(), ['id' => $id]);
    }

    public function test_category_forms_validate_duplicates_and_reject_deleting_referenced_categories(): void
    {
        $this->post('/finance/categories', ['name' => '  Sewa alat  ', 'transaction_type' => 'expense'])->assertRedirect('/finance/categories');
        $category = FinancialCategory::query()->where('name', 'Sewa alat')->firstOrFail();
        $this->patch('/finance/categories/'.$category->id, ['name' => 'Sewa traktor'])->assertRedirect('/finance/categories');
        $this->post('/finance/categories', ['name' => 'Sewa traktor', 'transaction_type' => 'expense'])->assertSessionHasErrors('name');
        $this->get('/finance/categories?search=traktor&transaction_type=expense')->assertInertia(fn (Assert $page) => $page
            ->component('farmer/FinanceRecords')->where('records.total', 1));
        $this->delete('/finance/categories/'.$category->id)->assertRedirect('/finance/categories');
        $this->transaction();
        $this->patch('/finance/categories/'.$this->income->id, ['transaction_type' => 'expense'])->assertSessionHasErrors('transaction_type');
        $this->from('/finance/categories')->delete('/finance/categories/'.$this->income->id)->assertRedirect('/finance/categories')->assertSessionHasErrors('delete');
        FinancialBudget::create(['farm_id' => $this->farm->id, 'category_id' => $this->expense->id, 'created_by' => $this->admin->id,
            'period_start' => '2026-10-01', 'period_end' => '2026-12-31', 'budget_amount' => '100']);
        $this->delete('/finance/categories/'.$this->expense->id)->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('financial_categories', ['id' => $this->expense->id]);
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function roles(): array
    {
        return ['owner' => ['owner', true, false], 'admin' => ['admin', true, true],
            'petugas' => ['petugas', false, false], 'superadmin' => ['superadmin', true, true]];
    }

    #[DataProvider('roles')]
    public function test_inertia_role_matrix_for_every_resource_and_mutation(string $role, bool $read, bool $write): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $this->join($actor, $this->team);
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $records = [
            'transactions' => [$this->transaction()->id, $this->payload()],
            'categories' => [$this->expense->id, ['name' => 'Biaya tenaga kerja', 'transaction_type' => 'expense']],
            'budgets' => [FinancialBudget::create(['farm_id' => $this->farm->id, 'created_by' => $this->admin->id,
                'period_start' => '2026-10-01', 'period_end' => '2026-12-31', 'budget_amount' => '100'])->id,
                ['period_start' => '2026-10-01', 'period_end' => '2026-12-31', 'budget_amount' => '200']],
            'projections' => [FinancialProjection::create(['farm_id' => $this->farm->id, 'period_start' => '2026-10-01',
                'period_end' => '2026-12-31', 'projected_income' => '100', 'calculated_at' => now()])->id,
                ['period_start' => '2026-10-01', 'period_end' => '2026-12-31', 'projected_income' => '200']],
        ];
        $this->actingAs($actor);
        foreach ($records as $resource => [$id, $payload]) {
            $response = $this->get('/finance/'.$resource)->assertStatus($read ? 200 : 403);
            if ($read) {
                $response->assertInertia(fn (Assert $page) => $page->component('farmer/FinanceRecords')->where('can.create', $write));
            }
            $this->get('/finance/'.$resource.'/'.$id)->assertStatus($read ? 200 : 403);
            $path = $resource === 'categories' ? 'categories' : 'farms/'.$this->farm->id.'/'.$resource;
            $this->post('/finance/'.$path, $payload)->assertStatus($write ? 302 : 403);
            $update = $resource === 'categories' ? [...$payload, 'name' => 'Biaya diperbarui'] : $payload;
            $this->patch('/finance/'.$resource.'/'.$id, $update)->assertStatus($write ? 302 : 403);
            $this->delete('/finance/'.$resource.'/'.$id)->assertStatus($write ? 302 : 403);
        }
        $this->get('/finance')->assertStatus($read ? 200 : 403);
        $this->get('/finance/transactions/export')->assertStatus($read ? 200 : 403);
    }

    public function test_cross_team_records_and_forged_parents_do_not_leak_counts_or_exports(): void
    {
        $other = $this->farm(Team::factory()->create());
        $hidden = $this->transaction($other, '999999.99');
        $local = $this->transaction();
        $this->get('/finance/transactions/'.$hidden->id)->assertForbidden();
        $this->patch('/finance/transactions/'.$hidden->id, ['amount' => '1'])->assertForbidden();
        $this->delete('/finance/transactions/'.$hidden->id)->assertForbidden();
        $this->post('/finance/farms/'.$other->id.'/transactions', $this->payload())->assertForbidden();
        $this->get('/finance?year=2026&period=Q4')->assertInertia(fn (Assert $page) => $page
            ->where('summary.income', '7250000.15')->where('transactions.total', 1)->where('transactions.data.0.id', $local->id)->has('farms', 1));
        $this->get('/finance/transactions?farm_id='.$other->id)->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
        $this->assertStringNotContainsString($hidden->id, $this->get('/finance/transactions/export')->streamedContent());
        $this->post('/finance/farms/'.$this->farm->id.'/transactions', [...$this->payload(), 'farm_id' => $other->id])->assertSessionHasErrors('farm_id');
    }

    public function test_query_scopes_and_revoked_grants_protect_pages_totals_and_mutations(): void
    {
        $local = $this->transaction();
        $hidden = $this->transaction($this->farm($this->team), '1000');
        $this->admin->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $roles = app(RolePermissions::class)->roles();
        $roles['admin']['financial_transactions.view'] = 'own';
        config(['access.roles' => $roles]);
        $this->get('/finance?year=2026&period=Q4&per_page=1')->assertInertia(fn (Assert $page) => $page->where('transactions.total', 1)->where('summary.income', '7250000.15'));
        $this->get('/finance/transactions')->assertInertia(fn (Assert $page) => $page->where('records.total', 1)->where('records.data.0.id', $local->id));
        $this->assertStringNotContainsString($hidden->id, $this->get('/finance/transactions/export')->streamedContent());
        $this->patch('/finance/transactions/'.$hidden->id, ['amount' => '1'])->assertForbidden();
        $roles['admin']['financial_transactions.view'] = 'invalid';
        config(['access.roles' => $roles]);
        $this->get('/finance')->assertForbidden();
        $this->get('/finance/transactions')->assertForbidden();
        $this->get('/finance/transactions/export')->assertForbidden();
        unset($roles['admin']['financial_transactions.view']);
        config(['access.roles' => $roles]);
        $this->get('/finance/transactions/'.$local->id)->assertForbidden();
    }

    public function test_relations_validation_rolls_back_invalid_changes_and_projection_requires_a_value(): void
    {
        $cycle = $this->cycle($this->farm($this->team));
        $path = '/finance/farms/'.$this->farm->id.'/transactions';
        $this->post($path, [...$this->payload(), 'planting_cycle_id' => $cycle->id])->assertSessionHasErrors('planting_cycle_id');
        $this->post($path, [...$this->payload(), 'category_id' => $this->expense->id])->assertSessionHasErrors('category_id');
        $transaction = $this->transaction();
        $this->patch('/finance/transactions/'.$transaction->id, ['transaction_type' => 'expense', 'amount' => '1'])->assertSessionHasErrors('category_id');
        $this->assertSame('7250000.15', $transaction->refresh()->amount);
        $period = ['period_start' => '2026-10-01', 'period_end' => '2026-12-31'];
        $this->post('/finance/farms/'.$this->farm->id.'/projections', $period)->assertSessionHasErrors('projected_income');
        $this->post('/finance/farms/'.$this->farm->id.'/projections', [...$period, 'projected_income' => '1', 'planting_cycle_id' => $cycle->id])->assertSessionHasErrors('planting_cycle_id');
        $this->post('/finance/farms/'.$this->farm->id.'/budgets', [...$period, 'budget_amount' => '1', 'category_id' => (string) Str::uuid()])->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('financial_projections', 0);
    }

    public function test_database_totals_are_exact_and_commodity_filters_do_not_invent_links(): void
    {
        $cycle = $this->cycle($this->farm);
        $first = $this->transaction(null, '9999999999999999.99');
        $first->update(['planting_cycle_id' => $cycle->id]);
        $second = $this->transaction(null, '0.01');
        $second->update(['description' => '=HYPERLINK("malicious")', 'transaction_date' => '2026-11-01']);
        FinancialTransaction::create([...$this->payload(), 'farm_id' => $this->farm->id, 'recorded_by' => $this->admin->id,
            'category_id' => $this->expense->id, 'transaction_type' => 'expense', 'amount' => '0.02']);
        $this->get('/finance?year=2026&period=Q4')->assertInertia(fn (Assert $page) => $page
            ->where('summary.income', '10000000000000000.00')->where('summary.expense', '0.02')->where('summary.net', '9999999999999999.98')->has('summary.monthly', 2));
        $this->get('/finance?year=2026&period=Q4&commodity=Jagung')->assertInertia(fn (Assert $page) => $page
            ->where('summary.income', '9999999999999999.99')->where('transactions.total', 1)->where('transactions.data.0.commodity', 'Jagung'));
        $csv = $this->get('/finance/transactions/export?from_date=2026-11-01')->streamedContent();
        $this->assertStringNotContainsString($first->id, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->get('/finance?year=2000')->assertInertia(fn (Assert $page) => $page->where('summary.income', '0.00')->where('transactions.total', 0));
        $roles = app(RolePermissions::class)->roles();
        unset($roles['admin']['crop_varieties.view']);
        config(['access.roles' => $roles]);
        $this->get('/finance?year=2026&period=Q4&commodity=Jagung')->assertInertia(fn (Assert $page) => $page
            ->where('summary.income', '0.00')->where('transactions.total', 0)->has('commodities', 0));
        $this->get('/finance?year=2026&period=Q4')->assertInertia(fn (Assert $page) => $page
            ->where('transactions.data.1.commodity', null));
    }

    public function test_form_errors_return_to_the_page_and_server_owned_fields_cannot_be_changed(): void
    {
        $path = '/finance/farms/'.$this->farm->id.'/transactions';
        foreach (['0', '-1', '1.001', '10000000000000000', '1e3'] as $amount) {
            $this->from('/finance/transactions')->post($path, [...$this->payload(), 'amount' => $amount])->assertRedirect('/finance/transactions')->assertSessionHasErrors('amount');
        }
        foreach (['farm_id', 'team_id', 'recorded_by', 'created_by', 'id', 'created_at'] as $field) {
            $this->post($path, [...$this->payload(), $field => (string) Str::uuid()])->assertSessionHasErrors($field);
        }
        $this->get('/finance?year=2200')->assertSessionHasErrors('year');
        $this->get('/finance/transactions?sort=password')->assertSessionHasErrors('sort');
        $this->get('/finance/transactions?per_page=101')->assertSessionHasErrors('per_page');
        $this->get('/finance/transactions/not-a-uuid')->assertNotFound();
        $this->get('/finance/transactions/'.Str::uuid())->assertNotFound();
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_unallocated_transactions_are_invisible_and_team_membership_cannot_be_forged(): void
    {
        $transaction = $this->transaction();
        $transaction->update(['farm_id' => null]);
        $this->get('/finance/transactions/'.$transaction->id)->assertForbidden();
        $this->get('/finance?year=2026&period=Q4')->assertInertia(fn (Assert $page) => $page->where('transactions.total', 0));
        $this->team->members()->detach($this->admin);
        $this->admin->unsetRelation('teams')->unsetRelation('currentTeam');
        $this->get('/finance')->assertForbidden();
    }

    public function test_guests_are_redirected_and_web_mutations_require_csrf(): void
    {
        auth()->logout();
        $this->get('/finance')->assertRedirect(route('login'));
        $this->get('/finance/categories')->assertRedirect(route('login'));
        $this->post('/finance/categories', ['name' => 'Benih', 'transaction_type' => 'expense'])->assertRedirect(route('login'));
        $this->actingAs($this->admin);
        $this->app->instance('env', 'local');
        try {
            $this->post('/finance/categories', ['name' => 'Benih', 'transaction_type' => 'expense'])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}

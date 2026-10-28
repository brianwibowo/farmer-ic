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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Integration tests against the guarded, dedicated PostgreSQL test database. */
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

    private function url(string $path, ?Team $team = null): string
    {
        return '/'.($team ?? $this->team)->slug.'/finance/'.$path;
    }

    /** @return array<string, mixed> */
    private function transactionPayload(): array
    {
        return ['category_id' => $this->income->id, 'transaction_date' => '2026-10-28',
            'transaction_type' => 'income', 'amount' => '7250000.15', 'description' => 'Penjualan jagung'];
    }

    private function transaction(?Farm $farm = null, string $amount = '7250000.15'): FinancialTransaction
    {
        return FinancialTransaction::create([...$this->transactionPayload(), 'farm_id' => ($farm ?? $this->farm)->id,
            'recorded_by' => $this->admin->id, 'amount' => $amount]);
    }

    private function cycle(Farm $farm): PlantingCycle
    {
        $commodity = Commodity::create(['name' => 'Jagung '.Str::random(6)]);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);

        return PlantingCycle::create(['farm_id' => $farm->id, 'variety_id' => $variety->id,
            'planted_at' => '2026-10-01', 'planted_area_ha' => '1.0000', 'status' => 'active']);
    }

    public function test_admin_transaction_crud_persists_exact_decimals_and_trusted_parent(): void
    {
        $cycle = $this->cycle($this->farm);
        $payload = [...$this->transactionPayload(), 'planting_cycle_id' => $cycle->id, 'amount' => '9999999999999999.99'];
        $created = $this->postJson($this->url('farms/'.$this->farm->id.'/transactions'), $payload)->assertCreated()
            ->assertJsonPath('data.farm_id', $this->farm->id)->assertJsonPath('data.recorded_by', $this->admin->id)
            ->assertJsonPath('data.amount', '9999999999999999.99');
        $id = $created->json('data.id');
        $this->getJson($this->url('transactions/'.$id))->assertOk()->assertJsonPath('can.update', true);
        $this->patchJson($this->url('transactions/'.$id), ['amount' => '0.01', 'planting_cycle_id' => null])
            ->assertOk()->assertJsonPath('data.planting_cycle_id', null)->assertJsonPath('data.amount', '0.01');
        $this->assertDatabaseHas('financial_transactions', ['id' => $id, 'amount' => '0.01', 'recorded_by' => $this->admin->id]);
        $this->putJson($this->url('transactions/'.$id), ['amount' => '12'])->assertUnprocessable();
        $this->putJson($this->url('transactions/'.$id), $this->transactionPayload())->assertOk();
        $this->deleteJson($this->url('transactions/'.$id))->assertNoContent();
        $this->assertDatabaseMissing('financial_transactions', ['id' => $id]);
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
    public function test_plan_crud_partial_period_validation_and_decimal_precision(string $resource, string $amountField, array $values): void
    {
        $payload = ['period_start' => '2026-10-01', 'period_end' => '2026-12-31', ...$values];
        $created = $this->postJson($this->url('farms/'.$this->farm->id.'/'.$resource), $payload)->assertCreated();
        $id = $created->json('data.id');
        $this->getJson($this->url($resource.'/'.$id))->assertOk()->assertJsonPath('data.'.$amountField, $values[$amountField]);
        $this->getJson($this->url($resource.'?from_date=2026-11-01&to_date=2026-11-30'))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($this->url($resource.'?from_date=2027-01-01'))->assertOk()->assertJsonPath('meta.total', 0);
        $this->patchJson($this->url($resource.'/'.$id), ['period_start' => '2027-01-01'])->assertUnprocessable()->assertJsonValidationErrors('period_end');
        $this->patchJson($this->url($resource.'/'.$id), ['period_end' => '2026-09-30'])->assertUnprocessable();
        $this->patchJson($this->url($resource.'/'.$id), [$amountField => '0'])->assertOk()->assertJsonPath('data.'.$amountField, '0.00');
        $this->putJson($this->url($resource.'/'.$id), $payload)->assertOk();
        $this->deleteJson($this->url($resource.'/'.$id))->assertNoContent();
        $this->getJson($this->url($resource.'/'.$id))->assertNotFound();
    }

    public function test_category_crud_duplicate_validation_and_history_protection(): void
    {
        $created = $this->postJson($this->url('categories'), ['name' => '  Sewa alat  ', 'transaction_type' => 'expense'])->assertCreated()
            ->assertJsonPath('data.name', 'Sewa alat');
        $id = $created->json('data.id');
        $this->getJson($this->url('categories/'.$id))->assertOk();
        $this->patchJson($this->url('categories/'.$id), ['name' => 'Sewa traktor'])->assertOk();
        $this->postJson($this->url('categories'), ['name' => 'Sewa traktor', 'transaction_type' => 'expense'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->getJson($this->url('categories?transaction_type=expense&search=traktor'))->assertOk()->assertJsonPath('meta.total', 1);
        $this->deleteJson($this->url('categories/'.$id))->assertNoContent();

        $this->transaction();
        $this->patchJson($this->url('categories/'.$this->income->id), ['transaction_type' => 'expense'])
            ->assertUnprocessable()->assertJsonValidationErrors('transaction_type');
        $this->deleteJson($this->url('categories/'.$this->income->id))->assertConflict();
        FinancialBudget::create(['farm_id' => $this->farm->id, 'category_id' => $this->expense->id,
            'created_by' => $this->admin->id, 'period_start' => '2026-10-01', 'period_end' => '2026-12-31', 'budget_amount' => '100']);
        $this->deleteJson($this->url('categories/'.$this->expense->id))->assertConflict();
        $this->assertDatabaseHas('financial_categories', ['id' => $this->expense->id]);
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function roles(): array
    {
        return ['owner' => ['owner', true, false], 'admin' => ['admin', true, true],
            'petugas' => ['petugas', false, false], 'superadmin' => ['superadmin', true, true]];
    }

    #[DataProvider('roles')]
    public function test_real_http_role_matrix_for_every_financial_resource(string $role, bool $read, bool $write): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $this->join($actor, $this->team);
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $records = [
            'transactions' => [$this->transaction()->id, $this->transactionPayload()],
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
            $this->getJson($this->url($resource))->assertStatus($read ? 200 : 403);
            $this->getJson($this->url($resource.'/'.$id))->assertStatus($read ? 200 : 403);
            $createPath = $resource === 'categories' ? $resource : 'farms/'.$this->farm->id.'/'.$resource;
            $this->postJson($this->url($createPath), $payload)->assertStatus($write ? 201 : 403);
            $update = $resource === 'categories' ? [...$payload, 'name' => 'Biaya tenaga kerja diperbarui'] : $payload;
            $this->patchJson($this->url($resource.'/'.$id), $update)->assertStatus($write ? 200 : 403);
            $this->deleteJson($this->url($resource.'/'.$id))->assertStatus($write ? 204 : 403);
        }
        $this->getJson($this->url('summary'))->assertStatus($read ? 200 : 403);
        $this->getJson($this->url('transactions/export'))->assertStatus($read ? 200 : 403);
    }

    public function test_cross_team_records_and_forged_parent_are_denied_without_leaking_totals(): void
    {
        $otherTeam = Team::factory()->create();
        $otherFarm = $this->farm($otherTeam);
        $transaction = $this->transaction($otherFarm, '999999.99');
        $this->transaction();
        $budget = FinancialBudget::create(['farm_id' => $otherFarm->id, 'created_by' => $this->admin->id,
            'period_start' => '2026-10-01', 'period_end' => '2026-12-31', 'budget_amount' => '100']);
        $projection = FinancialProjection::create(['farm_id' => $otherFarm->id, 'period_start' => '2026-10-01',
            'period_end' => '2026-12-31', 'projected_income' => '100', 'calculated_at' => now()]);
        foreach (['transactions' => $transaction, 'budgets' => $budget, 'projections' => $projection] as $resource => $record) {
            $this->getJson($this->url($resource.'/'.$record->id))->assertForbidden();
            $this->patchJson($this->url($resource.'/'.$record->id), [])->assertForbidden();
            $this->deleteJson($this->url($resource.'/'.$record->id))->assertForbidden();
            $this->getJson($this->url($resource.'?farm_id='.$otherFarm->id))->assertOk()->assertJsonPath('meta.total', 0);
        }
        $this->getJson($this->url('transactions'))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($this->url('summary'))->assertOk()->assertJsonPath('data.income', '7250000.15');
        $csv = $this->getJson($this->url('transactions/export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('7250000.15', $csv);
        $this->assertStringNotContainsString($transaction->id, $csv);
        $this->getJson($this->url('transactions', $otherTeam))->assertForbidden();
        $this->postJson($this->url('farms/'.$otherFarm->id.'/transactions'), $this->transactionPayload())->assertForbidden();
        $this->postJson($this->url('farms/'.$this->farm->id.'/transactions'), [...$this->transactionPayload(), 'farm_id' => $otherFarm->id])->assertUnprocessable();
    }

    public function test_scopes_limit_counts_summary_and_export_even_with_team_wide_update_grant(): void
    {
        $visible = $this->transaction();
        $hidden = $this->transaction($this->farm($this->team), '1000');
        $this->admin->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $roles = app(RolePermissions::class)->roles();
        $roles['admin']['financial_transactions.view'] = 'own';
        config(['access.roles' => $roles]);
        $this->getJson($this->url('transactions?per_page=1'))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $visible->id);
        $this->getJson($this->url('summary'))->assertOk()->assertJsonPath('data.income', '7250000.15');
        $this->assertStringNotContainsString($hidden->id, $this->getJson($this->url('transactions/export'))->streamedContent());
        $this->patchJson($this->url('transactions/'.$hidden->id), ['amount' => '1'])->assertForbidden();
        $roles['admin']['financial_transactions.view'] = 'invalid';
        config(['access.roles' => $roles]);
        $this->getJson($this->url('transactions'))->assertForbidden();
        $this->getJson($this->url('summary'))->assertForbidden();
        $this->getJson($this->url('transactions/export'))->assertForbidden();
        unset($roles['admin']['financial_transactions.view']);
        config(['access.roles' => $roles]);
        $this->getJson($this->url('transactions/'.$visible->id))->assertForbidden();
    }

    public function test_related_cycle_and_category_must_match_and_failed_changes_roll_back(): void
    {
        $otherCycle = $this->cycle($this->farm($this->team));
        $base = $this->url('farms/'.$this->farm->id.'/transactions');
        $this->postJson($base, [...$this->transactionPayload(), 'planting_cycle_id' => $otherCycle->id])
            ->assertUnprocessable()->assertJsonValidationErrors('planting_cycle_id');
        $this->postJson($base, [...$this->transactionPayload(), 'category_id' => $this->expense->id])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->postJson($base, [...$this->transactionPayload(), 'category_id' => (string) Str::uuid()])->assertUnprocessable();
        $transaction = $this->transaction();
        $this->patchJson($this->url('transactions/'.$transaction->id), ['transaction_type' => 'expense', 'amount' => '1'])->assertUnprocessable();
        $this->assertSame('7250000.15', $transaction->refresh()->amount);
        $this->assertSame('income', $transaction->transaction_type);
        $this->postJson($this->url('farms/'.$this->farm->id.'/projections'), [
            'period_start' => '2026-10-01', 'period_end' => '2026-12-31', 'projected_income' => '1', 'planting_cycle_id' => $otherCycle->id,
        ])->assertUnprocessable();
    }

    public function test_summary_uses_exact_database_arithmetic_and_filtered_csv_is_safe(): void
    {
        $first = $this->transaction(null, '9999999999999999.99');
        $second = $this->transaction(null, '0.01');
        $second->update(['description' => '=HYPERLINK("malicious")', 'transaction_date' => '2026-11-01']);
        FinancialTransaction::create([...$this->transactionPayload(), 'farm_id' => $this->farm->id,
            'recorded_by' => $this->admin->id, 'category_id' => $this->expense->id, 'transaction_type' => 'expense', 'amount' => '0.02']);
        $this->getJson($this->url('summary'))->assertOk()->assertJsonPath('data.income', '10000000000000000.00')
            ->assertJsonPath('data.expense', '0.02')->assertJsonPath('data.net', '9999999999999999.98')
            ->assertJsonPath('data.transaction_count', 3)->assertJsonCount(2, 'data.monthly')->assertJsonPath('data.source', 'database');
        $this->getJson($this->url('summary?from_date=2026-11-01&transaction_type=income'))->assertOk()
            ->assertJsonPath('data.income', '0.01')->assertJsonPath('data.transaction_count', 1);
        $this->getJson($this->url('summary?to_date=2026-01-01'))->assertOk()->assertJsonPath('data.income', '0.00');
        $csv = $this->getJson($this->url('transactions/export?from_date=2026-11-01'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString($first->id, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->getJson($this->url('transactions?sort=amount&direction=desc&per_page=1'))->assertOk()->assertJsonPath('data.0.id', $first->id);
    }

    public function test_server_owned_fields_bad_amounts_and_invalid_filters_are_rejected(): void
    {
        $base = $this->url('farms/'.$this->farm->id.'/transactions');
        foreach (['0', '-1', '1.001', '10000000000000000', '1e3', 'NaN'] as $amount) {
            $this->postJson($base, [...$this->transactionPayload(), 'amount' => $amount])->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        foreach (['farm_id', 'team_id', 'recorded_by', 'created_by', 'id', 'created_at'] as $field) {
            $this->postJson($base, [...$this->transactionPayload(), $field => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach (['sort=password', 'per_page=101', 'page=0', 'from_date=2026-11-01&to_date=2026-10-01', 'transaction_type=refund', 'farm_id=invalid'] as $query) {
            $this->getJson($this->url('transactions?'.$query))->assertUnprocessable();
        }
        $this->getJson($this->url('transactions/not-a-uuid'))->assertNotFound();
        $this->getJson($this->url('transactions/'.Str::uuid()))->assertNotFound();
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_projection_requires_a_value_and_budget_references_are_validated(): void
    {
        $period = ['period_start' => '2026-10-01', 'period_end' => '2026-12-31'];
        $this->postJson($this->url('farms/'.$this->farm->id.'/projections'), $period)->assertUnprocessable();
        $this->postJson($this->url('farms/'.$this->farm->id.'/projections'), [...$period, 'projected_income' => '-1'])->assertUnprocessable();
        $created = $this->postJson($this->url('farms/'.$this->farm->id.'/projections'), [...$period, 'projected_income' => '0'])->assertCreated();
        $this->patchJson($this->url('projections/'.$created->json('data.id')), ['projected_income' => null])->assertUnprocessable();
        $this->postJson($this->url('farms/'.$this->farm->id.'/budgets'), [...$period, 'budget_amount' => '100', 'category_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson($this->url('farms/'.$this->farm->id.'/budgets'), [...$period, 'budget_amount' => '100', 'created_by' => $this->admin->id])->assertUnprocessable();
    }

    public function test_optional_plan_links_can_be_cleared_and_server_metadata_cannot_be_replaced(): void
    {
        $period = ['period_start' => '2026-10-01', 'period_end' => '2026-12-31'];
        $budget = $this->postJson($this->url('farms/'.$this->farm->id.'/budgets'), [
            ...$period, 'budget_amount' => '100', 'category_id' => $this->expense->id,
        ])->assertCreated()->assertJsonPath('data.created_by', $this->admin->id)->json('data.id');
        $this->patchJson($this->url('budgets/'.$budget), ['category_id' => null])->assertOk()->assertJsonPath('data.category_id', null);
        $this->patchJson($this->url('budgets/'.$budget), ['created_by' => $this->admin->id])->assertUnprocessable();
        $cycle = $this->cycle($this->farm);
        $projection = $this->postJson($this->url('farms/'.$this->farm->id.'/projections'), [
            ...$period, 'projected_income' => '100', 'planting_cycle_id' => $cycle->id,
        ])->assertCreated()->json('data.id');
        $this->patchJson($this->url('projections/'.$projection), ['planting_cycle_id' => null])->assertOk()->assertJsonPath('data.planting_cycle_id', null);
        $this->patchJson($this->url('projections/'.$projection), ['calculated_at' => '2020-01-01'])->assertUnprocessable();
        $transaction = $this->transaction();
        $this->patchJson($this->url('transactions/'.$transaction->id), ['recorded_by' => $this->admin->id])->assertUnprocessable();
    }

    public function test_literal_search_filters_and_unallocated_records_never_appear_in_totals(): void
    {
        $transaction = $this->transaction();
        $transaction->update(['description' => 'Pembayaran 100% hasil panen']);
        $unallocated = $this->transaction(null, '1000000');
        $unallocated->update(['farm_id' => null]);
        $this->getJson($this->url('transactions?search=%25'))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($this->url('transactions?search=tidaktersedia'))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($this->url('transactions?category_id='.$this->income->id))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($this->url('transactions/'.$unallocated->id))->assertForbidden();
        $this->getJson($this->url('summary'))->assertOk()->assertJsonPath('data.transaction_count', 1);
        $this->getJson($this->url('transactions?team_id='.$this->team->id))->assertUnprocessable();
    }

    public function test_owner_in_multiple_teams_and_superadmin_still_get_route_specific_lists(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherTeam = Team::factory()->create();
        $this->join($owner, $this->team);
        $this->join($owner, $otherTeam);
        $local = $this->transaction();
        $remote = $this->transaction($this->farm($otherTeam), '1000');
        $this->actingAs($owner);
        $this->getJson($this->url('transactions'))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $local->id);
        $this->getJson($this->url('transactions', $otherTeam))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $remote->id);
        $this->getJson($this->url('transactions/'.$remote->id))->assertForbidden();
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->join($superadmin, $this->team);
        $this->actingAs($superadmin);
        $this->getJson($this->url('transactions'))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $local->id);
    }

    public function test_guests_cannot_read_or_write_finance(): void
    {
        auth()->logout();
        $this->getJson($this->url('transactions'))->assertUnauthorized();
        $this->getJson($this->url('summary'))->assertUnauthorized();
        $this->postJson($this->url('categories'), ['name' => 'Benih', 'transaction_type' => 'expense'])->assertUnauthorized();
    }
}

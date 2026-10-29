<?php

namespace Tests\Feature\Farms;

use App\Models\AuditLog;
use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ForceDeleteFarmTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->farm = Farm::query()->where('name', 'Kebun Cileuksa Demo')->firstOrFail();
        $this->developer = User::query()->where('email', 'superadmin@farmer-ic.test')->firstOrFail();
        $this->actingAs($this->developer);
    }

    public function test_only_superadmin_can_preview_or_submit_even_with_a_forged_role_or_blank_input(): void
    {
        $this->assertTrue(Gate::forUser($this->developer)->allows('forceDelete', $this->farm));
        foreach (['admin', 'owner', 'petugas'] as $role) {
            $actor = User::query()->where('email', $role.'@farmer-ic.test')->firstOrFail();
            $this->assertFalse(Gate::forUser($actor)->allows('forceDelete', $this->farm));
            $this->actingAs($actor)->get($this->url())->assertForbidden();
            $this->deleteJson($this->url(), [])->assertForbidden();
            $this->deleteJson($this->url(), ['role' => 'superadmin', 'force' => true, 'team_id' => $this->farm->team_id])->assertForbidden();
        }
        $admin = User::query()->where('email', 'admin@farmer-ic.test')->firstOrFail();
        $this->actingAs($admin)->get('/admin/farm-management/'.$this->farm->id)->assertInertia(fn (Assert $page) => $page->where('can.forceDelete', false));
        $this->assertDatabaseHas('farms', ['id' => $this->farm->id]);
    }

    public function test_preview_counts_match_deletion_and_shared_users_master_data_chat_and_other_farms_survive(): void
    {
        $other = $this->otherFarm();
        $weatherId = (string) Str::uuid();
        DB::table('weather_records')->insert(['id' => $weatherId, 'farm_id' => $other->id, 'recorded_at' => now()]);
        $conversationId = (string) Str::uuid();
        DB::table('ai_conversations')->insert([
            'id' => $conversationId, 'user_id' => $this->developer->id, 'team_id' => $this->farm->team_id,
            'title' => 'Diskusi histori kebun', 'created_at' => now(),
        ]);
        $this->get('/admin/farm-management/'.$this->farm->id)->assertInertia(fn (Assert $page) => $page->where('can.forceDelete', true));
        $summary = $this->summary();
        $counts = collect($summary['groups'])->keyBy('key');
        $this->assertSame([], $summary['blockers']);
        $this->assertSame(DB::table('farm_activities')->where('farm_id', $this->farm->id)->count(), $counts['farm_activities']['count']);
        $this->assertGreaterThan(0, $counts['block_mapping_details']['count']);
        $this->assertGreaterThan(0, $counts['ai_insights']['count']);
        $this->assertGreaterThan(0, $counts['financial_transactions']['count']);
        $this->assertSame(1, $counts['users']['count']);
        $usersBefore = DB::table('users')->count();
        $membershipsBefore = DB::table('team_members')->count();
        $masterBefore = [];
        foreach (['teams', 'roles', 'commodities', 'crop_varieties', 'financial_categories', 'activity_types', 'notifications'] as $table) {
            $masterBefore[$table] = DB::table($table)->count();
        }
        $auditIds = AuditLog::query()->pluck('id')->all();
        $this->delete($this->url(), $this->payload($summary))->assertRedirect('/admin/farm-management');
        // RefreshDatabase wraps HTTP requests in a transaction; explicitly run the
        // real PostgreSQL deferred checks that would run at an HTTP commit.
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        $this->assertDatabaseMissing('farms', ['id' => $this->farm->id]);
        foreach ($summary['groups'] as $group) {
            if (in_array($group['key'], ['farms', 'users', 'weather_records'], true)) {
                continue;
            }
            $this->assertDatabaseCount($group['key'], 0);
        }
        $this->assertDatabaseHas('farms', ['id' => $other->id]);
        $this->assertDatabaseHas('weather_records', ['id' => $weatherId, 'farm_id' => $other->id]);
        $this->assertDatabaseHas('ai_conversations', ['id' => $conversationId]);
        $this->assertSame($usersBefore, DB::table('users')->count());
        $this->assertSame($membershipsBefore, DB::table('team_members')->count());
        foreach ($masterBefore as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table.' must survive');
        }
        $this->assertSame(count($auditIds), AuditLog::query()->whereIn('id', $auditIds)->count());
        $this->assertNull(User::query()->where('email', 'petugas@farmer-ic.test')->firstOrFail()->assigned_farm_id);
        $audit = AuditLog::query()->where('action', 'force_delete')->firstOrFail();
        $this->assertSame($this->developer->id, $audit->user_id);
        $this->assertSame($this->farm->id, $audit->record_id);
        $this->assertSame('farms', $audit->table_name);
        $this->assertSame($this->farm->name, $audit->old_values['name']);
        $this->assertSame('Penghapusan data demo ganda.', $audit->new_values['reason']);
        $this->assertEquals($summary['groups'], $audit->new_values['summary']);
    }

    public function test_confirmation_and_reason_are_required_and_rejected_input_does_not_mutate_anything(): void
    {
        $payload = $this->payload($this->summary());
        foreach ([
            ['confirmation_name', 'Nama lain'], ['reason', ''], ['reason', '   '],
            ['summary_token', 'invalid'],
        ] as [$field, $value]) {
            $this->delete($this->url(), array_replace($payload, [$field => $value]))->assertSessionHasErrors($field);
            $this->assertDatabaseHas('farms', ['id' => $this->farm->id]);
            $this->assertDatabaseHas('users', ['email' => 'petugas@farmer-ic.test', 'assigned_farm_id' => $this->farm->id]);
        }
        $this->assertSame(0, AuditLog::query()->where('action', 'force_delete')->count());
    }

    public function test_stale_preview_and_token_for_another_farm_are_rejected(): void
    {
        $payload = $this->payload($this->summary());
        DB::table('weather_records')->insert(['id' => (string) Str::uuid(), 'farm_id' => $this->farm->id, 'recorded_at' => now()]);
        $this->delete($this->url(), $payload)->assertSessionHasErrors('force_delete');
        $other = $this->otherFarm();
        $otherSummary = $this->get($this->url($other))->assertOk()->viewData('page')['props']['summary'];
        $this->delete($this->url(), array_replace($payload, ['summary_token' => $otherSummary['token']]))->assertSessionHasErrors('force_delete');
        $this->assertDatabaseHas('farms', ['id' => $this->farm->id]);
        $this->assertSame(0, AuditLog::query()->where('action', 'force_delete')->count());
    }

    public function test_unallocated_finance_linked_to_a_cycle_blocks_deletion_instead_of_being_claimed_by_the_farm(): void
    {
        $transaction = DB::table('financial_transactions')->where('farm_id', $this->farm->id)->first();
        $id = (string) Str::uuid();
        DB::table('financial_transactions')->insert([
            'id' => $id, 'farm_id' => null, 'planting_cycle_id' => PlantingCycle::query()->where('farm_id', $this->farm->id)->firstOrFail()->id,
            'category_id' => $transaction->category_id, 'recorded_by' => $transaction->recorded_by,
            'transaction_date' => $transaction->transaction_date, 'transaction_type' => $transaction->transaction_type,
            'amount' => '50000.00', 'created_at' => now(),
        ]);
        $summary = $this->summary();
        $this->assertNotEmpty($summary['blockers']);
        $this->delete($this->url(), $this->payload($summary))->assertSessionHasErrors('force_delete');
        $this->assertDatabaseHas('financial_transactions', ['id' => $id, 'farm_id' => null]);
        $this->assertDatabaseHas('farms', ['id' => $this->farm->id]);
    }

    public function test_replaced_records_invalidate_the_preview_even_when_the_total_count_is_unchanged(): void
    {
        $summary = $this->summary();
        DB::table('weather_records')->where('farm_id', $this->farm->id)->delete();
        $replacement = (string) Str::uuid();
        DB::table('weather_records')->insert(['id' => $replacement, 'farm_id' => $this->farm->id, 'recorded_at' => now()]);
        $this->assertSame(collect($summary['groups'])->firstWhere('key', 'weather_records')['count'],
            DB::table('weather_records')->where('farm_id', $this->farm->id)->count());
        $this->delete($this->url(), $this->payload($summary))->assertSessionHasErrors('force_delete');
        $this->assertDatabaseHas('farms', ['id' => $this->farm->id]);
        $this->assertDatabaseHas('weather_records', ['id' => $replacement]);
    }

    public function test_soft_deleted_and_inactive_accounts_are_detached_but_not_removed(): void
    {
        $worker = User::query()->where('email', 'petugas@farmer-ic.test')->firstOrFail();
        $worker->forceFill(['is_active' => false])->save();
        $worker->delete();
        $summary = $this->summary();
        $this->assertSame(1, collect($summary['groups'])->firstWhere('key', 'users')['count']);
        $this->delete($this->url(), $this->payload($summary))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $worker->id, 'assigned_farm_id' => null, 'is_active' => false]);
        $this->assertNotNull(User::onlyTrashed()->findOrFail($worker->id)->deleted_at);
    }

    public function test_audit_write_failure_rolls_back_all_deletes_and_assignment_changes(): void
    {
        $summary = $this->summary();
        DB::unprepared("CREATE FUNCTION test_reject_force_delete_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.action = 'force_delete' THEN RAISE EXCEPTION 'Audit unavailable' USING ERRCODE = '23514'; END IF; RETURN NEW; END $$");
        DB::statement('CREATE TRIGGER test_reject_force_delete_audit BEFORE INSERT ON audit_logs FOR EACH ROW EXECUTE FUNCTION test_reject_force_delete_audit()');
        try {
            $this->delete($this->url(), $this->payload($summary))->assertSessionHasErrors('force_delete');
            $this->assertDatabaseHas('farms', ['id' => $this->farm->id]);
            $this->assertDatabaseHas('users', ['email' => 'petugas@farmer-ic.test', 'assigned_farm_id' => $this->farm->id]);
            foreach ($summary['groups'] as $group) {
                if ($group['key'] === 'users') {
                    continue; // The summary counts assignments, not every user account.
                }
                $this->assertSame($group['count'], DB::table($group['key'])->count(), $group['key'].' must roll back');
            }
            $this->assertSame(0, AuditLog::query()->where('action', 'force_delete')->count());
        } finally {
            DB::statement('DROP TRIGGER test_reject_force_delete_audit ON audit_logs');
            DB::statement('DROP FUNCTION test_reject_force_delete_audit()');
        }
    }

    public function test_guest_cannot_preview_or_submit(): void
    {
        auth()->logout();
        $this->get($this->url())->assertRedirect('/login');
        $this->delete($this->url(), [])->assertRedirect('/login');
    }

    public function test_superadmin_does_not_need_membership_or_current_team_to_force_delete_a_known_farm(): void
    {
        $this->developer->teams()->detach();
        $this->developer->forceFill(['current_team_id' => null])->save();
        $this->actingAs($this->developer->fresh());
        $summary = $this->summary();
        $this->delete($this->url(), $this->payload($summary))->assertRedirect('/admin/dashboard');
        $this->assertDatabaseMissing('farms', ['id' => $this->farm->id]);
        $this->assertSame(0, $this->developer->teams()->count());
        $this->assertNull($this->developer->fresh()->current_team_id);
    }

    private function url(?Farm $farm = null): string
    {
        return '/admin/farm-management/'.($farm ?? $this->farm)->id.'/force-delete';
    }

    /** @return array<string, mixed> */
    private function summary(): array
    {
        return $this->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/ForceDelete')->where('farm.id', $this->farm->id))
            ->viewData('page')['props']['summary'];
    }

    /** @param array<string, mixed> $summary @return array<string, string> */
    private function payload(array $summary): array
    {
        return ['confirmation_name' => $this->farm->name, 'reason' => 'Penghapusan data demo ganda.', 'summary_token' => $summary['token']];
    }

    private function otherFarm(): Farm
    {
        $team = Team::query()->create(['name' => 'Tenant kebun lain', 'is_personal' => false]);
        $farm = new Farm(['name' => 'Kebun Tetap Utuh', 'total_area_ha' => 2]);
        $farm->forceFill(['created_by' => $this->developer->id, 'team_id' => $team->id])->save();

        return $farm;
    }
}

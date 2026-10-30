<?php

namespace Tests\Feature\Blocks;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantingRound;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Support\RolePermissions;
use Database\Seeders\BlockMappingActivityTypeSeeder;
use Database\Seeders\DemoFarmBlockSeeder;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** HTTP, policy and persistence integration on the guarded PostgreSQL test schema. */
class FarmBlockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create();
        $this->join($this->admin);
        $this->farm = $this->farm();
        $this->actingAs($this->admin);
    }

    private function join(User $actor): void
    {
        $this->team->members()->attach($actor, ['role' => TeamRole::Member->value]);
        $actor->forceFill(['current_team_id' => $this->team->id])->save();
        $actor->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    private function farm(?Team $team = null): Farm
    {
        $farm = new Farm(['name' => 'Kebun Percobaan', 'total_area_ha' => '10.0000']);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function block(string $code = 'B01', string $area = '5.0000', ?Farm $farm = null): FarmBlock
    {
        $farm ??= $this->farm;

        $creator = $farm->team_id === $this->team->id ? $this->admin : User::factory()->create(['role' => 'superadmin']);

        return app(FarmBlockService::class)->create($farm, $creator, $farm->team, ['code' => $code, 'name' => 'Blok Utara', 'area_ha' => $area]);
    }

    private function url(?FarmBlock $block = null, ?Farm $farm = null): string
    {
        return '/kebun/'.($farm ?? $this->farm)->id.'/blocks'.($block ? '/'.$block->id : '');
    }

    private function constraints(): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
    }

    public function test_admin_crud_normalizes_code_and_starts_land_clearing(): void
    {
        $this->get($this->url().'/create')->assertInertia(fn (Assert $page) => $page->component('farmer/blocks/Form')->where('block', null));
        $this->post($this->url(), ['code' => ' b01 ', 'name' => 'Blok Utara', 'area_ha' => '3.1250'])->assertRedirect();
        $block = FarmBlock::firstOrFail();
        $this->assertSame('B01', $block->code);
        $this->assertSame('3.1250', $block->area_ha);
        $this->assertSame($this->admin->id, $block->created_by);
        $this->assertNotNull($block->land_clearing_started_at);
        $this->assertNull($block->land_clearing_completed_at);
        $this->get($this->url($block))->assertInertia(fn (Assert $page) => $page->component('farmer/blocks/Show')->where('can.update', true)->has('batches', 0));
        $this->get($this->url($block).'/edit')->assertInertia(fn (Assert $page) => $page->component('farmer/blocks/Form')->where('block.id', $block->id));
        $this->patch($this->url($block), ['name' => 'Blok Timur'])->assertRedirect($this->url($block));
        $this->assertSame('Blok Timur', $block->refresh()->name);
        $this->delete($this->url($block))->assertRedirect($this->url());
        $this->assertDatabaseMissing('farm_blocks', ['id' => $block->id]);
        $this->constraints();
    }

    public static function roles(): array
    {
        return ['owner' => ['owner', false], 'admin' => ['admin', true], 'petugas' => ['petugas', false], 'superadmin' => ['superadmin', true]];
    }

    #[DataProvider('roles')]
    public function test_role_permissions_and_http_denials(string $role, bool $mutates): void
    {
        $block = $this->block();
        $actor = User::factory()->create(['role' => $role]);
        if ($role !== 'superadmin') {
            $this->join($actor);
        }
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($actor);
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.create', $mutates));
        $this->get($this->url($block))->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.update', $mutates));
        $this->assertSame($mutates, Gate::forUser($actor)->allows('create', [FarmBlock::class, $this->farm]));
        $this->assertFalse($role !== 'superadmin' && Gate::forUser($actor)->allows('configure', $block));
        $payload = ['code' => 'B02', 'name' => 'Blok Selatan', 'area_ha' => '1'];
        if ($mutates) {
            $this->post($this->url(), $payload)->assertRedirect();
            $this->patch($this->url($block), ['name' => 'Blok Revisi'])->assertRedirect();
            $this->delete($this->url($block))->assertRedirect();
        } else {
            $this->get($this->url().'/create')->assertForbidden();
            $this->get($this->url($block).'/edit')->assertForbidden();
            $this->post($this->url(), $payload)->assertForbidden();
            $this->patch($this->url($block), ['name' => 'Tidak Boleh'])->assertForbidden();
            $this->delete($this->url($block))->assertForbidden();
        }
        $this->constraints();
    }

    public function test_petugas_creates_independent_batches_without_approval_or_invented_planting_data(): void
    {
        $block = $this->block();
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->join($actor);
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($actor)->post($this->url($block).'/batches', ['reserved_area_ha' => '1.2500'])->assertRedirect($this->url($block));
        $first = PlantingCycle::firstOrFail();
        $this->assertSame(1, $first->batch_number);
        $this->assertSame('A', $first->subblock_label);
        $this->assertSame($actor->id, $first->created_by);
        $this->assertSame('planned', $first->status);
        $this->assertSame('0.0000', $first->planted_area_ha);
        $this->assertNull($first->planted_at);
        $this->assertNull($first->variety_id);
        $completedAt = $block->refresh()->land_clearing_completed_at->toISOString();
        $this->travel(1)->hours();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1.5000'])->assertRedirect();
        $this->assertSame($completedAt, $block->refresh()->land_clearing_completed_at->toISOString());
        $this->assertSame(1, PlantingRound::count());
        $this->assertSame(12, PlantingCycleStage::count());
        $this->get($this->url($block))->assertInertia(fn (Assert $page) => $page->where('can.update', false)->where('can.createBatch', true)
            ->where('batches.0.stages.0.status', 'completed')->where('batches.0.stages.1.status', 'in_progress')
            ->where('batches.0.stages.2.status', 'upcoming')->where('batches.0.stages.2.started_at', null)
            ->where('batches.1.batch_number', 2)->where('batches.1.subblock_label', 'B'));
        $this->constraints();
    }

    public function test_next_round_reuses_labels_but_continues_batch_numbers_across_blocks(): void
    {
        $block = $this->block();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        PlantingCycle::query()->update(['status' => 'cancelled']);
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '2'])->assertRedirect();
        $other = $this->block('B02');
        $this->post($this->url($other).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        $this->assertSame([1, 2, 3], PlantingCycle::orderBy('batch_number')->pluck('batch_number')->all());
        $this->assertSame(['A', 'A', 'A'], PlantingCycle::orderBy('batch_number')->pluck('subblock_label')->all());
        $this->assertSame(['closed', 'active'], PlantingRound::where('block_id', $block->id)->orderBy('round_number')->pluck('status')->all());
        $this->constraints();
    }

    public function test_capacity_guards_resize_deactivation_reactivation_and_history(): void
    {
        $block = $this->block('B01', '6');
        $other = $this->block('B02', '4');
        $this->post($this->url(), ['code' => 'B03', 'name' => 'Berlebih', 'area_ha' => '.0001'])->assertSessionHasErrors('area_ha');
        $this->patch($this->url($other), ['is_active' => false])->assertRedirect();
        $this->patch($this->url($block), ['area_ha' => '10'])->assertRedirect();
        $this->patch($this->url($other), ['is_active' => true])->assertSessionHasErrors('area_ha');
        $this->post($this->url($other).'/batches', ['reserved_area_ha' => '1'])->assertSessionHasErrors('reserved_area_ha');
        $this->patch('/admin/farm-management/'.$this->farm->id, ['total_area_ha' => '9.9999'])->assertSessionHasErrors('total_area_ha');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '3'])->assertRedirect();
        $this->patch($this->url($block), ['area_ha' => '2.9999'])->assertSessionHasErrors('area_ha');
        $this->patch($this->url($block), ['is_active' => false])->assertSessionHasErrors('is_active');
        $this->delete($this->url($block))->assertSessionHasErrors('delete');
        $this->deleteJson('/api/kebun/'.$this->farm->id)->assertConflict();
        $this->constraints();
    }

    public function test_rejected_batch_creation_rolls_back_transition_and_sequence(): void
    {
        $block = $this->block('B01', '2');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '2.0001'])->assertSessionHasErrors('reserved_area_ha');
        $this->assertNull($block->refresh()->land_clearing_completed_at);
        $this->assertDatabaseCount('planting_rounds', 0);
        $this->assertDatabaseCount('planting_cycle_stages', 0);
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1', 'subblock_label' => ' a '])->assertRedirect();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1', 'subblock_label' => 'A'])->assertSessionHasErrors('subblock_label');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        $this->assertSame([1, 2], PlantingCycle::orderBy('batch_number')->pluck('batch_number')->all());
        $this->constraints();
    }

    public function test_scoped_lists_totals_search_and_directory_do_not_expose_other_farms(): void
    {
        $this->block('B01', '2');
        $this->block('B02', '3');
        $other = $this->farm();
        $this->block('B03', '9', $other);
        $this->get($this->url().'?per_page=1')->assertInertia(fn (Assert $page) => $page->where('blocks.total', 2)->has('blocks.data', 1)->where('summary.total', 2)->where('summary.active_area_ha', '5.0000'));
        $this->get($this->url().'?search=B02')->assertInertia(fn (Assert $page) => $page->where('blocks.total', 1));
        $this->get($this->url().'?search=%25')->assertInertia(fn (Assert $page) => $page->where('blocks.total', 0));
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->join($actor);
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($actor)->get('/blocks')->assertInertia(fn (Assert $page) => $page->where('farms.total', 1)->where('farms.data.0.id', $this->farm->id));
        $this->get($this->url(null, $other))->assertForbidden();
    }

    public function test_cross_team_nested_id_and_missing_assignment_are_denied(): void
    {
        $block = $this->block();
        $foreignTeam = Team::factory()->create();
        $foreign = $this->farm($foreignTeam);
        $foreignBlock = $this->block('B99', '5', $foreign);
        $this->get($this->url($foreignBlock, $foreign))->assertForbidden();
        $this->patch($this->url($foreignBlock, $foreign), ['name' => 'Bocor'])->assertForbidden();
        $this->post($this->url($foreignBlock, $foreign).'/batches', ['reserved_area_ha' => '1'])->assertForbidden();
        $this->get($this->url($foreignBlock))->assertNotFound();
        $this->post($this->url($foreignBlock).'/batches', ['reserved_area_ha' => '1'])->assertNotFound();
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->join($actor);
        $this->actingAs($actor)->get($this->url($block))->assertForbidden();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertForbidden();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->join($owner);
        $this->actingAs($owner)->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertForbidden();
        $this->assertDatabaseCount('planting_cycles', 0);
    }

    public function test_validation_rejects_forged_server_fields_and_invalid_values(): void
    {
        $block = $this->block();
        $this->post($this->url(), ['code' => 'b01', 'name' => 'Duplikat', 'area_ha' => '1'])->assertSessionHasErrors('code');
        $this->post($this->url(), ['code' => 'B 02', 'name' => '', 'area_ha' => '1.00001', 'farm_id' => $this->farm->id, 'created_by' => $this->admin->id])->assertSessionHasErrors(['code', 'name', 'area_ha', 'farm_id', 'created_by']);
        $this->patch($this->url($block), ['land_clearing_completed_at' => now()->toISOString(), 'current_stage' => 'harvest'])->assertSessionHasErrors(['land_clearing_completed_at', 'current_stage']);
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '-1', 'farm_id' => $this->farm->id, 'batch_number' => 99, 'status' => 'active', 'stages' => ['harvest']])->assertSessionHasErrors(['reserved_area_ha', 'farm_id', 'batch_number', 'status', 'stages']);
        $this->getJson($this->url().'?per_page=101')->assertUnprocessable();
    }

    public function test_default_deny_and_query_scope_reuse_matrix(): void
    {
        $block = $this->block();
        $this->assertFalse(Gate::forUser($this->admin)->allows('configure', $block));
        $this->assertFalse(Gate::forUser($this->admin)->allows('create', FarmBlock::class));
        $this->assertFalse(Gate::forUser($this->admin)->allows('create', [FarmBlock::class, $this->team]));
        $roles = app(RolePermissions::class)->roles();
        $roles['admin']['farm_blocks.view'] = 'invalid';
        config(['access.roles' => $roles]);
        $this->assertSame(0, FarmBlock::visibleTo($this->admin, $this->team)->count());
        $this->assertFalse(Gate::forUser($this->admin)->allows('update', $block));
        unset($roles['admin']['farm_blocks.view']);
        config(['access.roles' => $roles]);
        $this->get($this->url())->assertForbidden();
    }

    public function test_demo_seeder_is_idempotent_and_does_not_rewrite_existing_blocks(): void
    {
        $this->seed([DemoIdentitySeeder::class, DemoFarmSeeder::class, DemoFarmBlockSeeder::class]);
        $demo = Farm::where('name', 'Kebun Cileuksa Demo')->firstOrFail();
        $ids = FarmBlock::where('farm_id', $demo->id)->pluck('id')->all();
        $this->seed(DemoFarmBlockSeeder::class);
        $this->assertSame($ids, FarmBlock::where('farm_id', $demo->id)->pluck('id')->all());
        $this->assertSame(2, count($ids));
        $this->constraints();
    }

    public function test_guest_routes_require_login(): void
    {
        $block = $this->block();
        auth()->logout();
        $this->get('/blocks')->assertRedirect('/login');
        $this->get($this->url())->assertRedirect('/login');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect('/login');
    }

    public function test_mapping_reference_is_optional_same_farm_and_keeps_measurement_snapshot(): void
    {
        $this->seed(BlockMappingActivityTypeSeeder::class);
        $block = $this->block();
        $payload = ['work_date' => now('Asia/Jakarta')->format('Y-m-d'), 'start_time' => '08:00', 'end_time' => '10:00', 'worker_count' => 2,
            'activities' => [['type' => 'block_mapping', 'obstacle_status' => 'none',
                'block_mapping' => ['block_id' => $block->id, 'block_name' => 'Nama saat pengukuran', 'length_m' => '100', 'width_m' => '100']]]];
        $this->post('/kebun/'.$this->farm->id.'/daily-logs', $payload)->assertRedirect();
        $this->patch($this->url($block), ['name' => 'Nama Baru'])->assertRedirect();
        $this->assertDatabaseHas('block_mapping_details', ['block_id' => $block->id, 'block_name' => 'Nama saat pengukuran']);
        $this->delete($this->url($block))->assertSessionHasErrors('delete');
        $other = $this->farm();
        $otherBlock = $this->block('B99', '1', $other);
        $payload['activities'][0]['block_mapping']['block_id'] = $otherBlock->id;
        $this->post('/kebun/'.$this->farm->id.'/daily-logs', $payload)->assertSessionHasErrors('activities.0.block_mapping.block_id');
        $this->assertDatabaseCount('daily_activity_logs', 1);
        $this->constraints();
    }

    public function test_database_constraints_reject_bypassed_capacity_cross_farm_and_incomplete_timeline(): void
    {
        $block = $this->block('B01', '10');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '5'])->assertRedirect();
        $batch = PlantingCycle::firstOrFail();
        $other = $this->farm();
        $invalidWrites = [
            fn () => DB::table('farm_blocks')->where('id', $block->id)->update(['area_ha' => '10.0001']),
            fn () => DB::table('planting_cycles')->where('id', $batch->id)->update(['farm_id' => $other->id]),
            fn () => DB::table('planting_cycle_stages')->where('planting_cycle_id', $batch->id)->where('code', 'harvest')->delete(),
        ];
        foreach ($invalidWrites as $write) {
            DB::beginTransaction();
            try {
                $write();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                $this->fail('Database accepted an invalid block/batch write.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->getCode());
            } finally {
                DB::rollBack();
            }
        }
        $this->constraints();
    }
}

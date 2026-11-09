<?php

namespace Tests\Feature\Petugas;

use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\Petugas\PetugasGardenService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Integration coverage on the guarded, dedicated PostgreSQL test schema. */
class PetugasGardenTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private User $petugas;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-03T18:30:00Z'));
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->petugas = User::factory()->create(['role' => 'petugas']);
        $this->join($this->admin, $this->team);
        $this->join($this->petugas, $this->team);
        $this->farm = $this->farm($this->team);
        $this->petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
    }

    private function join(User $user, Team $team): void
    {
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    private function farm(Team $team): Farm
    {
        $farm = new Farm(['name' => 'Kebun Sukamaju', 'land_tenure' => 'owned', 'total_area_ha' => 10,
            'address' => 'Jalan Kebun Utara', 'district' => 'Cisarua', 'city' => 'Bogor', 'province' => 'Jawa Barat']);
        $farm->forceFill(['team_id' => $team->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function block(Farm $farm, string $code = 'B01', ?User $actor = null): FarmBlock
    {
        return app(FarmBlockService::class)->create($farm, $actor ?? $this->admin, $farm->team, [
            'code' => $code, 'name' => 'Blok '.$code, 'area_ha' => '5.0000',
        ]);
    }

    private function batch(FarmBlock $block, string $status = 'planned', ?User $actor = null): PlantingCycle
    {
        $batch = app(FarmBlockService::class)->createBatch($block, $actor ?? $this->admin, $block->farm->team, [
            'reserved_area_ha' => '0.5000', 'planned_planting_at' => '2026-11-08',
        ]);
        if (in_array($status, ['active', 'harvested'], true)) {
            $commodity = Commodity::firstOrCreate(['name' => 'Jagung']);
            $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
            $batch->forceFill(['status' => $status, 'planted_at' => '2026-10-28', 'planted_area_ha' => '0.5000',
                'variety_id' => $variety->id, 'estimated_harvest_at' => '2027-01-26'])->save();
        }

        return $batch;
    }

    public function test_overview_and_totals_only_include_the_assigned_farm_and_persisted_stages(): void
    {
        $this->farm->update(['latitude' => -6.5965, 'longitude' => 106.9502]);
        $north = $this->block($this->farm);
        $south = $this->block($this->farm, 'B02');
        $active = $this->batch($north, 'active');
        $planned = $this->batch($north);
        $this->batch($south);
        $otherFarm = $this->farm($this->team);
        $this->batch($this->block($otherFarm));
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $this->join($otherAdmin, $foreignTeam);
        $this->batch($this->block($this->farm($foreignTeam), actor: $otherAdmin), actor: $otherAdmin);

        $this->actingAs($this->petugas)->get('/petugas/kebun?farm_id='.$otherFarm->id.'&team_id='.$foreignTeam->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/Kebun')->where('garden.farm.id', $this->farm->id)
            ->where('garden.farm.address', 'Jalan Kebun Utara')->where('garden.farm.total_area_ha', '10.0000')
            ->where('garden.farm.latitude', '-6.5965000')->where('garden.farm.longitude', '106.9502000')
            ->where('garden.summary', ['blocks' => 2, 'batches' => 3])->has('garden.blocks', 2)
            ->where('garden.selected_block.id', $north->id)->where('garden.selected_block.batches_count', 2)
            ->where('garden.batches.total', 2)->has('garden.batches.data', 2)
            ->where('garden.batches.data.0.id', $planned->id)->where('garden.batches.data.0.hst', null)
            ->where('garden.batches.data.0.planted_area_ha', '0.0000')
            ->where('garden.batches.data.0.current_stage', null)->has('garden.batches.data.0.stages', 6)
            ->where('garden.batches.data.1.id', $active->id)->where('garden.batches.data.1.hst', 7)
            ->where('garden.batches.data.1.planted_area_ha', '0.5000')
            ->where('garden.batches.data.1.estimated_harvest_at', '2027-01-26')
            ->missing('garden.farm.owner')->missing('garden.farm.financial_transactions'));
        $this->get('/petugas/kebun?block='.$south->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('garden.selected_block.id', $south->id)->where('garden.batches.total', 1)
            ->where('garden.summary.batches', 3));
    }

    public function test_batch_pagination_keeps_the_selected_block_and_clamps_out_of_range_pages(): void
    {
        $north = $this->block($this->farm);
        $south = $this->block($this->farm, 'B02');
        $this->batch($north);
        $first = $this->batch($south);
        for ($index = 1; $index < 7; $index++) {
            $this->batch($south);
        }
        $this->actingAs($this->petugas)->get('/petugas/kebun?block='.$south->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('garden.batches.total', 7)->where('garden.batches.last_page', 2)->has('garden.batches.data', 6)
            ->where('garden.batches.next_page_url', fn ($url) => str_contains($url, 'block='.$south->id)));
        $this->get('/petugas/kebun?block='.$south->id.'&page=99')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('garden.batches.current_page', 2)->has('garden.batches.data', 1)->where('garden.batches.data.0.id', $first->id));
    }

    public function test_automatic_block_timestamps_do_not_claim_an_unreported_clearing(): void
    {
        $block = $this->block($this->farm);
        $startedAt = $block->getAttribute('land_clearing_started_at');
        $this->assertInstanceOf(CarbonImmutable::class, $startedAt);

        $this->actingAs($this->petugas)->get('/petugas/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('garden.blocks.0.land_clearing_started_at', null)
            ->where('garden.blocks.0.land_clearing_completed_at', null));

        $block->forceFill(['land_clearing_completed_at' => $startedAt->addHour()])->save();
        $completedAt = $block->refresh()->getAttribute('land_clearing_completed_at');
        $this->assertInstanceOf(CarbonImmutable::class, $completedAt);
        $this->get('/petugas/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('garden.blocks.0.land_clearing_started_at', null)
            ->where('garden.blocks.0.land_clearing_completed_at', null));
    }

    public function test_previous_rounds_remain_distinct_when_subblock_labels_are_reused(): void
    {
        $block = $this->block($this->farm);
        $harvested = $this->batch($block, 'harvested');
        $next = $this->batch($block);
        $this->assertSame($harvested->subblock_label, $next->subblock_label);

        $this->actingAs($this->petugas)->get('/petugas/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('garden.batches.total', 2)->where('garden.batches.data.0.round_number', 2)
            ->where('garden.batches.data.1.round_number', 1)->where('garden.batches.data.1.status', 'harvested'));
    }

    public function test_cross_farm_block_selection_and_lost_membership_return_403(): void
    {
        $other = $this->block($this->farm($this->team));
        $this->actingAs($this->petugas)->get('/petugas/kebun?block='.$other->id)->assertForbidden();
        $this->team->members()->detach($this->petugas);
        $this->get('/petugas/kebun')->assertForbidden();
    }

    public function test_missing_assignments_and_empty_block_lists_are_honest_empty_states(): void
    {
        $this->actingAs($this->petugas)->get('/petugas/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('garden.farm.id', $this->farm->id)->where('garden.summary', ['blocks' => 0, 'batches' => 0])
            ->where('garden.farm.latitude', null)->where('garden.farm.longitude', null)
            ->has('garden.blocks', 0)->has('garden.batches.data', 0)->where('garden.selected_block', null));
        $this->petugas->forceFill(['assigned_farm_id' => null])->save();
        $this->petugas->unsetRelation('assignedFarm');
        $this->get('/petugas/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('garden.farm', null)->has('garden.blocks', 0)->where('garden.batches.total', 0));
    }

    public static function requiredPermissions(): array
    {
        return [['farms.view'], ['farm_blocks.view'], ['planting_rounds.view'], ['planting_cycles.view'], ['planting_cycle_stages.view']];
    }

    #[DataProvider('requiredPermissions')]
    public function test_missing_grants_deny_page_access(string $permission): void
    {
        $roles = config('access.roles');
        unset($roles['petugas'][$permission]);
        config(['access.roles' => $roles]);
        $this->actingAs($this->petugas)->get('/petugas/kebun')->assertForbidden();
    }

    public function test_service_counts_and_pages_remain_empty_without_cycle_visibility(): void
    {
        $this->batch($this->block($this->farm));
        $roles = config('access.roles');
        unset($roles['petugas']['planting_cycles.view']);
        config(['access.roles' => $roles]);
        $garden = app(PetugasGardenService::class)->overview($this->petugas, $this->farm, $this->team);
        $this->assertSame(0, $garden['summary']['batches']);
        $this->assertSame(0, $garden['selected_block']['batches_count']);
        $this->assertSame(0, $garden['batches']->total());
    }

    public function test_block_and_pagination_inputs_are_validated(): void
    {
        $this->actingAs($this->petugas)->get('/petugas/kebun?block=invalid')->assertSessionHasErrors('block');
        $this->get('/petugas/kebun?page=0')->assertSessionHasErrors('page');
    }
}

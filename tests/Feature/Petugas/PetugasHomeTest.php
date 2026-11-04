<?php

namespace Tests\Feature\Petugas;

use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Integration coverage on the guarded, dedicated PostgreSQL test schema. */
class PetugasHomeTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private User $petugas;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        // November 4 in WIB, while the UTC server is still on November 3.
        $this->travelTo(CarbonImmutable::parse('2026-11-03T18:30:00Z'));
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->petugas = User::factory()->create(['role' => 'petugas']);
        $this->join($this->admin, $this->team);
        $this->join($this->petugas, $this->team);
        $this->farm = $this->farm('Kebun Sukamaju', $this->team);
        $this->petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
    }

    private function join(User $user, Team $team): void
    {
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    private function farm(string $name, Team $team): Farm
    {
        $farm = new Farm(['name' => $name, 'land_tenure' => 'owned', 'total_area_ha' => 10]);
        $farm->forceFill(['team_id' => $team->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function batch(Farm $farm, string $status = 'planned', ?User $actor = null): PlantingCycle
    {
        $actor ??= $this->admin;
        $team = $farm->team;
        $block = FarmBlock::where('farm_id', $farm->id)->first()
            ?? app(FarmBlockService::class)->create($farm, $actor, $team, [
                'code' => 'B01', 'name' => 'Blok Utara', 'area_ha' => '10.0000',
            ]);
        $batch = app(FarmBlockService::class)->createBatch($block, $actor, $team, [
            'reserved_area_ha' => '1.0000', 'planned_planting_at' => '2026-10-28',
        ]);
        if (in_array($status, ['active', 'harvested'], true)) {
            $commodity = Commodity::firstOrCreate(['name' => 'Jagung']);
            $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
            $batch->forceFill(['status' => $status, 'planted_at' => '2026-10-28', 'planted_area_ha' => 1, 'variety_id' => $variety->id])->save();
        } elseif ($status !== 'planned') {
            $batch->forceFill(['status' => $status])->save();
        }

        return $batch;
    }

    private function task(PlantingCycle $batch, string $status = 'pending', ?string $date = '2026-11-04', string $name = 'Penyiraman pagi'): PlantingCycleTask
    {
        return PlantingCycleTask::create([
            'planting_cycle_id' => $batch->id,
            'planting_cycle_stage_id' => $batch->stages()->where('code', 'maintenance')->value('id'),
            'activity_category' => 'maintenance', 'activity_subcategory' => 'irrigation',
            'name' => $name, 'instructions' => 'Siram merata di sekitar pangkal tanaman sebelum pukul 09.00.',
            'offset_days' => 7, 'tolerance_days' => 0, 'scheduled_date' => $date, 'due_date' => $date,
            'is_required' => true, 'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    public function test_home_scopes_batch_counts_and_today_sop_to_the_assigned_farm_in_wib(): void
    {
        $planned = $this->batch($this->farm);
        $active = $this->batch($this->farm, 'active');
        $harvested = $this->batch($this->farm, 'harvested');
        $cancelled = $this->batch($this->farm, 'cancelled');
        $pending = $this->task($active);
        $running = $this->task($active, 'in_progress');
        $this->task($planned, 'submitted');
        $this->task($harvested, 'completed');
        foreach (['2026-11-03', '2026-11-05', null] as $date) {
            $this->task($active, date: $date);
        }
        $this->task($active, 'cancelled');
        $this->task($active, 'skipped');
        $this->task($cancelled);
        $unassigned = $this->farm('Kebun Tidak Ditugaskan', $this->team);
        $this->task($this->batch($unassigned));
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreignAdmin = User::factory()->create(['role' => 'admin']);
        $this->join($foreignAdmin, $foreignTeam);
        $foreign = $this->farm('Kebun Tenant Lain', $foreignTeam);
        $this->task($this->batch($foreign, actor: $foreignAdmin));

        $this->actingAs($this->petugas)->get('/petugas?farm_id='.$foreign->id.'&team_id='.$foreignTeam->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/Home')
            ->where('home.date', '2026-11-04')->where('home.farm.id', $this->farm->id)
            ->where('home.summary', ['pre_planting' => 1, 'planting' => 1, 'harvest' => 1])
            ->where('home.task_summary', ['total' => 4, 'completed' => 1])
            ->where('home.tasks.total', 4)->has('home.tasks.data', 4)
            ->where('home.tasks.data.0.id', $running->id)
            ->where('home.tasks.data.1.id', $pending->id)
            ->where('home.tasks.data.1.hst', 7)
            ->where('home.tasks.data.1.block_name', 'Blok Utara')
            ->where('home.tasks.data.1.batch_number', $active->batch_number)
            ->where('home.tasks.data.1.stage', 'maintenance')
            ->where('home.tasks.data.1.instructions', $pending->instructions)
            ->where('home.tasks.data.2.hst', null)
            ->where('home.tasks.data.3.status', 'completed'));
    }

    public function test_task_pagination_counts_all_visible_tasks_without_leaking_unassigned_records(): void
    {
        $batch = $this->batch($this->farm);
        for ($index = 1; $index <= 11; $index++) {
            $this->task($batch, name: sprintf('Pemeriksaan bedeng %02d', $index));
        }
        $this->task($this->batch($this->farm('Kebun Selatan', $this->team)));

        $this->actingAs($this->petugas)->get('/petugas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('home.tasks.total', 11)->where('home.task_summary.total', 11)
            ->where('home.tasks.last_page', 2)->has('home.tasks.data', 10));
        $this->get('/petugas?page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('home.tasks.total', 11)->where('home.tasks.current_page', 2)
            ->has('home.tasks.data', 1)->where('home.tasks.data.0.name', 'Pemeriksaan bedeng 11'));
    }

    public function test_home_without_an_assignment_has_an_honest_empty_state(): void
    {
        $this->task($this->batch($this->farm));
        $this->petugas->forceFill(['assigned_farm_id' => null])->save();

        $this->actingAs($this->petugas)->get('/petugas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('home.farm', null)->where('home.summary', ['pre_planting' => 0, 'planting' => 0, 'harvest' => 0])
            ->where('home.task_summary.total', 0)->where('home.tasks.total', 0)->has('home.tasks.data', 0));
    }

    public function test_assignment_alone_cannot_bypass_team_membership(): void
    {
        $this->team->members()->detach($this->petugas);
        $this->actingAs($this->petugas)->get('/petugas')->assertForbidden();
    }

    public static function requiredPermissions(): array
    {
        return [['farms.view'], ['planting_cycles.view'], ['planting_cycle_tasks.view']];
    }

    #[DataProvider('requiredPermissions')]
    public function test_missing_required_permissions_returns_a_real_403(string $permission): void
    {
        $roles = config('access.roles');
        unset($roles['petugas'][$permission]);
        config(['access.roles' => $roles]);

        $this->actingAs($this->petugas)->get('/petugas')->assertForbidden();
    }

    public function test_missing_block_visibility_excludes_its_counts_and_tasks(): void
    {
        $this->task($this->batch($this->farm));
        $roles = config('access.roles');
        unset($roles['petugas']['farm_blocks.view']);
        config(['access.roles' => $roles]);

        $this->actingAs($this->petugas)->get('/petugas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('home.summary.pre_planting', 0)->where('home.tasks.total', 0));
    }
}

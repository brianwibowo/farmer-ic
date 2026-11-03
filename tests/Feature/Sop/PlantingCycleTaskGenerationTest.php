<?php

namespace Tests\Feature\Sop;

use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\PlantingCycleTaskService;
use App\Services\SopTemplateService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantingCycleTaskGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private Farm $farm;

    private FarmBlock $block;

    private Commodity $commodity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->join($this->admin);
        $this->commodity = Commodity::query()->create(['name' => 'Jagung']);
        $this->farm = new Farm([
            'name' => 'Kebun Tugas SOP',
            'land_tenure' => 'owned',
            'total_area_ha' => '10.0000',
        ]);
        $this->farm->forceFill([
            'team_id' => $this->team->id,
            'created_by' => $this->admin->id,
        ])->save();
        $this->block = app(FarmBlockService::class)->create($this->farm, $this->admin, $this->team, [
            'code' => 'B01',
            'name' => 'Blok Utara',
            'area_ha' => '5.0000',
        ]);
    }

    private function join(User $user): void
    {
        $this->team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => $this->team->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    private function publishedSop(?Team $team = null): SopTemplate
    {
        $service = app(SopTemplateService::class);
        $template = $service->create($this->admin, $team ?? $this->team, [
            'commodity_id' => $this->commodity->id,
            'name' => 'SOP Jagung',
            'description' => 'Panduan penanaman sampai panen.',
            'is_active' => true,
        ]);
        $version = $template->versions()->firstOrFail();

        foreach ([
            ['activity_category' => 'maintenance', 'activity_subcategory' => 'irrigation', 'name' => 'Pengairan awal', 'offset_days' => 7, 'tolerance_days' => 2],
            ['activity_category' => 'harvest', 'activity_subcategory' => null, 'name' => 'Panen', 'offset_days' => 90, 'tolerance_days' => 5],
        ] as $task) {
            $service->createTask($version, [
                ...$task,
                'instructions' => null,
                'is_required' => true,
            ]);
        }

        $service->publish($version, $this->admin);

        return $template;
    }

    private function subblok(?string $plannedPlantingAt = '2026-11-05'): PlantingCycle
    {
        return app(FarmBlockService::class)->createBatch($this->block, $this->admin, $this->team, [
            'reserved_area_ha' => '2.0000',
            'planned_planting_at' => $plannedPlantingAt,
        ]);
    }

    private function useDefaultSop(SopTemplate $template): void
    {
        $this->farm->forceFill(['default_sop_template_id' => $template->id])->save();
    }

    public function test_generates_dated_tasks_for_a_subblok_from_the_farm_default_sop(): void
    {
        $template = $this->publishedSop();
        $version = $template->versions()->where('status', 'published')->firstOrFail();
        $this->useDefaultSop($template);
        $subblok = $this->subblok();

        $result = app(PlantingCycleTaskService::class)->generateForSubblok($subblok);

        $this->assertSame('generated', $result['status']);
        $this->assertSame(2, $result['created']);
        $this->assertSame($version->id, $subblok->refresh()->sop_template_version_id);

        $tasks = PlantingCycleTask::query()
            ->where('planting_cycle_id', $subblok->id)
            ->orderBy('offset_days')
            ->get();

        $this->assertCount(2, $tasks);
        $this->assertFalse($tasks->contains('activity_category', 'planting'));

        $irrigation = $tasks->firstWhere('activity_subcategory', 'irrigation');
        $harvest = $tasks->firstWhere('activity_category', 'harvest');

        $this->assertSame('2026-11-12', $irrigation->scheduled_date->toDateString());
        $this->assertSame('2026-11-14', $irrigation->due_date->toDateString());
        $this->assertSame('maintenance', $irrigation->stage->code);
        $this->assertSame('2027-02-03', $harvest->scheduled_date->toDateString());
        $this->assertSame('2027-02-08', $harvest->due_date->toDateString());
        $this->assertSame('harvest', $harvest->stage->code);
    }

    public function test_planting_cannot_be_persisted_as_a_cycle_task(): void
    {
        $subblok = $this->subblok();
        $stage = $subblok->stages()->where('code', 'planting')->firstOrFail();

        $this->expectException(QueryException::class);

        PlantingCycleTask::query()->create([
            'planting_cycle_id' => $subblok->id,
            'planting_cycle_stage_id' => $stage->id,
            'activity_category' => 'planting',
            'activity_subcategory' => null,
            'name' => 'Tanam benih',
            'instructions' => null,
            'offset_days' => 0,
            'tolerance_days' => 2,
            'scheduled_date' => '2026-11-05',
            'due_date' => '2026-11-07',
            'is_required' => true,
            'status' => 'pending',
        ]);
    }

    public function test_generation_is_idempotent_and_reschedules_only_pending_tasks(): void
    {
        $template = $this->publishedSop();
        $this->useDefaultSop($template);
        $subblok = $this->subblok();
        $service = app(PlantingCycleTaskService::class);
        $service->generateForSubblok($subblok);

        $second = $service->generateForSubblok($subblok);
        $this->assertSame('already_generated', $second['status']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, PlantingCycleTask::query()->where('planting_cycle_id', $subblok->id)->count());

        $irrigation = PlantingCycleTask::query()
            ->where('planting_cycle_id', $subblok->id)
            ->where('activity_subcategory', 'irrigation')
            ->firstOrFail();
        $irrigation->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
        ])->save();

        $subblok->forceFill(['planted_at' => '2026-11-08'])->save();
        $third = $service->generateForSubblok($subblok);

        $this->assertSame('already_generated', $third['status']);
        $this->assertSame(1, $third['rescheduled']);
        $this->assertSame('2026-11-12', $irrigation->refresh()->scheduled_date->toDateString());

        $harvest = PlantingCycleTask::query()
            ->where('planting_cycle_id', $subblok->id)
            ->where('activity_category', 'harvest')
            ->firstOrFail();
        $this->assertSame('2027-02-06', $harvest->scheduled_date->toDateString());
    }

    public function test_generates_tasks_without_dates_when_the_subblok_has_no_anchor(): void
    {
        $template = $this->publishedSop();
        $this->useDefaultSop($template);
        $subblok = $this->subblok(null);

        $result = app(PlantingCycleTaskService::class)->generateForSubblok($subblok);

        $this->assertSame('generated', $result['status']);
        $this->assertSame(2, $result['created']);
        $this->assertSame(0, PlantingCycleTask::query()
            ->where('planting_cycle_id', $subblok->id)
            ->whereNotNull('scheduled_date')
            ->count());
    }

    public function test_reports_missing_or_unpublished_default_sop(): void
    {
        $subblok = $this->subblok();
        $service = app(PlantingCycleTaskService::class);

        $this->assertSame('missing_sop', $service->generateForSubblok($subblok)['status']);
        $this->assertSame(0, PlantingCycleTask::query()->count());

        $draft = app(SopTemplateService::class)->create($this->admin, $this->team, [
            'commodity_id' => $this->commodity->id,
            'name' => 'SOP Draft',
            'description' => null,
            'is_active' => true,
        ]);
        $this->useDefaultSop($draft);

        $this->assertSame('missing_published_version', $service->generateForSubblok($subblok)['status']);
        $this->assertSame(0, PlantingCycleTask::query()->count());
    }

    public function test_rejects_a_default_sop_from_another_tenant(): void
    {
        $otherTeam = Team::factory()->create();
        $foreign = $this->publishedSop($otherTeam);
        $this->farm->forceFill(['default_sop_template_id' => $foreign->id])->save();
        $subblok = $this->subblok();

        $result = app(PlantingCycleTaskService::class)->generateForSubblok($subblok);

        $this->assertSame('invalid_sop_tenant', $result['status']);
        $this->assertSame(0, PlantingCycleTask::query()->count());
    }

    public function test_an_existing_snapshot_survives_a_default_sop_change(): void
    {
        $first = $this->publishedSop();
        $this->useDefaultSop($first);
        $subblok = $this->subblok();
        $service = app(PlantingCycleTaskService::class);
        $service->generateForSubblok($subblok);

        $second = app(SopTemplateService::class)->create($this->admin, $this->team, [
            'commodity_id' => $this->commodity->id,
            'name' => 'SOP Pengganti',
            'description' => null,
            'is_active' => true,
        ]);
        $version = $second->versions()->firstOrFail();
        app(SopTemplateService::class)->createTask($version, [
            'activity_category' => 'harvest',
            'activity_subcategory' => null,
            'name' => 'Panen versi baru',
            'instructions' => null,
            'offset_days' => 0,
            'tolerance_days' => 1,
            'is_required' => true,
        ]);
        app(SopTemplateService::class)->publish($version, $this->admin);
        $this->useDefaultSop($second);

        $result = $service->generateForSubblok($subblok);

        $this->assertSame('already_generated', $result['status']);
        $this->assertCount(2, PlantingCycleTask::query()->where('planting_cycle_id', $subblok->id)->get());
        $this->assertSame($first->versions()->where('status', 'published')->value('id'), $subblok->refresh()->sop_template_version_id);
    }
}

<?php

namespace Tests\Feature\Petugas;

use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\SopTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PetugasActivityFormTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private Farm $farm;

    private Commodity $commodity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->join($this->admin);
        $this->commodity = Commodity::query()->create(['name' => 'Jagung']);
        $this->farm = new Farm([
            'name' => 'Kebun SOP Default',
            'land_tenure' => 'owned',
            'total_area_ha' => '10.0000',
        ]);
        $this->farm->forceFill([
            'team_id' => $this->team->id,
            'created_by' => $this->admin->id,
        ])->save();
    }

    private function join(User $user): void
    {
        $this->team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => $this->team->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    private function petugas(): User
    {
        $petugas = User::factory()->create([
            'role' => 'petugas',
            'assigned_farm_id' => $this->farm->id,
        ]);
        $this->join($petugas);

        return $petugas;
    }

    private function publishedSop(?Team $team = null): SopTemplate
    {
        $service = app(SopTemplateService::class);
        $sop = $service->create($this->admin, $team ?? $this->team, [
            'commodity_id' => $this->commodity->id,
            'name' => 'SOP Jagung',
            'description' => 'Panduan penanaman sampai panen.',
            'is_active' => true,
        ]);
        $version = $sop->versions()->firstOrFail();
        $service->createTask($version, [
            'activity_category' => 'planting',
            'activity_subcategory' => null,
            'name' => 'Tanam benih',
            'instructions' => null,
            'offset_days' => 0,
            'tolerance_days' => 2,
            'is_required' => true,
        ]);
        $service->publish($version, $this->admin);

        return $sop;
    }

    public function test_petugas_sees_the_farm_default_sop_without_a_selector(): void
    {
        $sop = $this->publishedSop();
        $this->farm->forceFill(['default_sop_template_id' => $sop->id])->save();

        $this->actingAs($this->petugas())
            ->get('/petugas/aktivitas/buat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/ActivityCreate')
                ->where('farm.id', $this->farm->id)
                ->where('defaultSop.id', $sop->id)
                ->where('defaultSop.name', 'SOP Jagung')
                ->where('defaultSop.commodity.name', 'Jagung')
                ->where('defaultSop.published_version.version', 1)
                ->where('defaultSop.tasks_count', 1)
                ->where('defaultSop.is_usable', true));
    }

    public function test_petugas_without_an_assigned_farm_has_no_default_sop(): void
    {
        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->join($petugas);

        $this->actingAs($petugas)
            ->get('/petugas/aktivitas/buat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/ActivityCreate')
                ->where('farm', null)
                ->where('defaultSop', null));
    }

    public function test_a_default_sop_from_another_tenant_is_not_exposed(): void
    {
        $otherTeam = Team::factory()->create();
        $otherSop = $this->publishedSop($otherTeam);
        $this->farm->forceFill(['default_sop_template_id' => $otherSop->id])->save();

        $this->actingAs($this->petugas())
            ->get('/petugas/aktivitas/buat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/ActivityCreate')
                ->where('defaultSop', null));
    }

    public function test_an_unpublished_default_sop_is_reported_as_not_usable(): void
    {
        $sop = app(SopTemplateService::class)->create($this->admin, $this->team, [
            'commodity_id' => $this->commodity->id,
            'name' => 'SOP Draft',
            'description' => null,
            'is_active' => true,
        ]);
        $this->farm->forceFill(['default_sop_template_id' => $sop->id])->save();

        $this->actingAs($this->petugas())
            ->get('/petugas/aktivitas/buat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/ActivityCreate')
                ->where('defaultSop.id', $sop->id)
                ->where('defaultSop.published_version', null)
                ->where('defaultSop.is_usable', false));
    }
}

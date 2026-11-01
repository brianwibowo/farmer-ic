<?php

namespace Tests\Feature\Sop;

use App\Enums\TeamRole;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\SopTemplate;
use App\Models\SopTemplateVersion;
use App\Models\Team;
use App\Models\User;
use App\Services\SopTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SopTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    private Farm $farm;

    private CropVariety $variety;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create();
        $this->join($this->admin);
        $this->farm = $this->farm();
        $this->variety = $this->variety();
        $this->actingAs($this->admin);
    }

    private function join(User $user, ?Team $team = null): void
    {
        ($team ?? $this->team)->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => ($team ?? $this->team)->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    private function farm(?Team $team = null, string $name = 'Kebun SOP'): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => '10.0000']);
        $farm->forceFill([
            'team_id' => ($team ?? $this->team)->id,
            'created_by' => $this->admin->id,
        ])->save();

        return $farm;
    }

    private function variety(string $name = 'Bisi 18'): CropVariety
    {
        $commodity = Commodity::query()->firstOrCreate(['name' => 'Jagung']);

        return CropVariety::query()->create([
            'commodity_id' => $commodity->id,
            'name' => $name,
        ]);
    }

    private function activityType(string $code = 'planting'): ActivityType
    {
        return ActivityType::query()->firstOrCreate(['code' => $code], [
            'name' => ucfirst(str_replace('_', ' ', $code)),
            'description' => 'Jenis aktivitas pengujian.',
        ]);
    }

    /** @return array<string, mixed> */
    private function taskPayload(int $position = 1, string $code = 'planting'): array
    {
        return [
            'activity_type_id' => $this->activityType($code)->id,
            'stage_code' => 'planting',
            'name' => 'Langkah '.$position,
            'instructions' => 'Instruksi langkah '.$position.'.',
            'schedule_anchor' => 'planting_date',
            'offset_days' => ($position - 1) * 7,
            'tolerance_days' => 2,
            'position' => $position,
            'is_required' => true,
        ];
    }

    private function createTemplate(): SopTemplate
    {
        return app(SopTemplateService::class)->create($this->admin, $this->farm, [
            'variety_id' => $this->variety->id,
            'name' => 'SOP Jagung Bisi 18',
            'description' => 'Panduan budidaya.',
            'is_active' => true,
        ]);
    }

    public function test_admin_creates_template_with_initial_draft_version(): void
    {
        $response = $this->post('/admin/farms/'.$this->farm->id.'/sops', [
            'variety_id' => $this->variety->id,
            'name' => 'SOP Jagung Bisi 18',
            'description' => 'Panduan budidaya.',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $template = SopTemplate::query()->firstOrFail();
        $this->assertSame($this->farm->id, $template->farm_id);
        $this->assertSame($this->variety->id, $template->variety_id);
        $this->assertSame($this->admin->id, $template->created_by);
        $this->assertDatabaseHas('sop_template_versions', [
            'sop_template_id' => $template->id,
            'version' => 1,
            'status' => 'draft',
        ]);
    }

    public function test_task_creation_publish_and_frozen_version(): void
    {
        $template = $this->createTemplate();
        $version = $template->versions()->firstOrFail();
        $service = app(SopTemplateService::class);

        $this->post('/admin/sops/'.$template->id.'/versions/'.$version->id.'/tasks', $this->taskPayload())
            ->assertRedirect();
        $this->post('/admin/sops/'.$template->id.'/versions/'.$version->id.'/publish')
            ->assertRedirect();

        $version->refresh();
        $this->assertSame('published', $version->status);
        $this->assertSame($this->admin->id, $version->published_by);
        $this->assertNotNull($version->published_at);

        $this->expectException(ValidationException::class);
        $service->createTask($version, $this->taskPayload(2, 'harvest'));
    }

    public function test_new_version_copies_tasks_and_publishing_retires_previous_version(): void
    {
        $template = $this->createTemplate();
        $service = app(SopTemplateService::class);
        $first = $template->versions()->firstOrFail();
        $service->createTask($first, $this->taskPayload());
        $service->publish($first, $this->admin);

        $second = $service->storeVersion($template, $this->admin, 'Revisi dosis.');
        $this->assertSame(2, $second->version);
        $this->assertSame(1, $second->tasks()->count());
        $this->assertSame('draft', $second->status);

        $service->createTask($second, $this->taskPayload(2, 'harvest'));
        $service->publish($second, $this->admin);

        $this->assertSame('retired', $first->refresh()->status);
        $this->assertSame('published', $second->refresh()->status);
    }

    public function test_publish_requires_at_least_one_task(): void
    {
        $template = $this->createTemplate();
        $version = $template->versions()->firstOrFail();

        $this->expectException(ValidationException::class);
        app(SopTemplateService::class)->publish($version, $this->admin);
    }

    public function test_copy_to_multiple_farms_creates_independent_draft_templates(): void
    {
        $template = $this->createTemplate();
        $service = app(SopTemplateService::class);
        $version = $template->versions()->firstOrFail();
        $service->createTask($version, $this->taskPayload());
        $service->publish($version, $this->admin);

        $second = $this->farm(null, 'Kebun Tujuan A');
        $third = $this->farm(null, 'Kebun Tujuan B');
        $created = $service->copyToFarms($this->admin, $version, [$second, $third]);

        $this->assertCount(2, $created);
        $this->assertSame($version->id, $created[0]->copied_from_version_id);
        $this->assertSame($version->id, $created[1]->copied_from_version_id);
        foreach ($created as $copy) {
            $copyVersion = $copy->versions()->firstOrFail();
            $this->assertSame('draft', $copyVersion->status);
            $this->assertSame(1, $copyVersion->tasks()->count());
            $this->assertSame($this->variety->id, $copy->variety_id);
        }
    }

    public function test_copy_rejects_a_farm_from_another_tenant(): void
    {
        $template = $this->createTemplate();
        $service = app(SopTemplateService::class);
        $version = $template->versions()->firstOrFail();
        $service->createTask($version, $this->taskPayload());
        $service->publish($version, $this->admin);

        $foreignTeam = Team::factory()->create();
        $foreignFarm = $this->farm($foreignTeam, 'Kebun Tenant Lain');

        $this->expectException(ValidationException::class);
        $service->copyToFarms($this->admin, $version, [$foreignFarm]);
    }

    public function test_admin_updates_template_without_changing_variety(): void
    {
        $template = $this->createTemplate();
        $other = $this->variety('Pioneer 32');

        $this->put('/admin/sops/'.$template->id, [
            'variety_id' => $this->variety->id,
            'name' => 'SOP Jagung Revisi',
            'description' => 'Deskripsi baru.',
            'is_active' => false,
        ])->assertRedirect();

        $template->refresh();
        $this->assertSame('SOP Jagung Revisi', $template->name);
        $this->assertFalse($template->is_active);
        $this->assertSame($this->variety->id, $template->variety_id);

        $this->put('/admin/sops/'.$template->id, [
            'variety_id' => $other->id,
            'name' => 'SOP Jagung Revisi',
        ])->assertSessionHasErrors('variety_id');
    }

    public function test_index_show_and_form_pages_render_for_admin(): void
    {
        $template = $this->createTemplate();

        $this->get('/admin/sops')
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('sops/Index')
                ->has('sops.data', 1)
                ->where('can.create', true));

        $this->get('/admin/sops/'.$template->id)
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('sops/Show')
                ->where('template.id', $template->id)
                ->where('can.update', true)
                ->where('can.copy', true)
                ->has('template.versions', 1));

        $this->get('/admin/farms/'.$this->farm->id.'/sops/create')
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('sops/Form')
                ->where('template', null)
                ->where('farm.id', $this->farm->id));

        $this->get('/admin/sops/'.$template->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('sops/Form')
                ->where('template.id', $template->id));
    }

    public function test_owner_reads_but_cannot_mutate_and_petugas_cannot_open_catalog(): void
    {
        $template = $this->createTemplate();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->join($owner);
        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->join($petugas);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();

        $this->assertTrue(Gate::forUser($owner)->allows('view', $template));
        $this->assertFalse(Gate::forUser($owner)->allows('create', [SopTemplate::class, $this->farm]));
        $this->assertFalse(Gate::forUser($petugas)->allows('view', $template));

        $this->actingAs($owner)->post('/admin/farms/'.$this->farm->id.'/sops', [
            'variety_id' => $this->variety->id,
            'name' => 'Tidak boleh',
        ])->assertForbidden();

        $this->actingAs($petugas)->get('/admin/sops/'.$template->id)->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Owner;

use App\Enums\TeamRole;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\PlantingCycle;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Database\Seeders\DemoMonitoringSeeder;
use Database\Seeders\DemoSopSeeder;
use Database\Seeders\SopActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** HTTP and persistence integration on the guarded, separate PostgreSQL schema. */
class OwnerPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $petugas;

    private Team $team;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DemoIdentitySeeder::class,
            DemoFarmSeeder::class,
            SopActivityTypeSeeder::class,
            DemoSopSeeder::class,
            DemoActivitySeeder::class,
            DemoMonitoringSeeder::class,
        ]);
        $this->owner = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['owner']['email'])->firstOrFail();
        $this->admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
        $this->petugas = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
        $this->team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
        $this->farm = Farm::query()->where('name', 'Kebun Cileuksa')->firstOrFail();
        $this->actingAs($this->owner);
    }

    public function test_projection_page_is_owner_only_and_receives_scoped_harvest_data(): void
    {
        $this->get('/proyeksi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Projection')
            ->has('projection.cards', 3)
            ->where('projection.period', 'cycle')
            ->where('urls.index', '/proyeksi')
            ->where('urls.home', '/'));

        foreach ([$this->admin, $this->petugas] as $actor) {
            $this->actingAs($actor)->get('/proyeksi')->assertForbidden();
        }

        $this->app['auth']->guard()->logout();
        $this->get('/proyeksi')->assertRedirect(route('login'));
    }

    public function test_owner_workspace_shares_admin_farm_pages_without_admin_access_or_write_permissions(): void
    {
        foreach (['/' => 'owner/Dashboard', '/proyeksi' => 'owner/Projection', '/kebun' => 'farms/Index', '/kebun/'.$this->farm->id => 'farms/Show'] as $url => $component) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component($component)->where('navigation.owner', true)
                ->where('navigation.admin', false)->where('navigation.farms', true));
        }
        $this->get('/kebun')->assertInertia(fn (Assert $page) => $page
            ->where('can.create', false)->where('farms.data.0.can.update', false)
            ->where('farms.data.0.can.delete', false)->where('farms.data.0.can.forceDelete', false));

        $detailUrl = $this->get('/')->viewData('page')['props']['dashboard']['landMap']['farms'][0]['detail_url'];
        $this->get($detailUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Show')->where('farm.id', $this->farm->id)
            ->where('legalityUrl', '/kebun/'.$this->farm->id.'/legalitas')
            ->where('can.update', false)->where('can.delete', false)->where('can.forceDelete', false)
            ->where('can.createBlock', false)
            ->where('blocks', fn ($blocks) => collect($blocks)->every(fn ($block) => ! $block['can']['update'] && ! $block['can']['delete'] && ! $block['can']['createBatch']))
            ->where('batchPermissions', fn ($permissions) => collect($permissions)->every(fn ($allowed) => $allowed === false)));

        foreach (['/admin/dashboard', '/admin/farm-management', '/admin/farm-management/'.$this->farm->id, '/admin/farm-management/create', '/admin/farm-management/'.$this->farm->id.'/edit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/farm-management', ['name' => 'Dilarang'])->assertForbidden();
        $this->delete('/admin/farm-management/'.$this->farm->id)->assertForbidden();
    }

    public function test_owner_farm_filters_pagination_and_related_sop_grants_stay_scoped(): void
    {
        $this->makeFarm($this->team, 'Kebun Selatan A');
        $this->makeFarm($this->team, 'Kebun Selatan B');
        $otherTeam = Team::factory()->create(['is_personal' => false]);
        $this->makeFarm($otherTeam, 'Kebun Selatan Rahasia');

        $response = $this->get('/kebun?search=Kebun%20Selatan&sort=name&direction=desc&per_page=1');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farms.total', 2)->has('farms.data', 1)->where('summary.count', 3)
            ->where('farms.data.0.name', 'Kebun Selatan B')
            ->where('farms.next_page_url', fn ($url) => str_contains($url, '/kebun?') && ! str_contains($url, '/admin/')));
        $this->get($response->viewData('page')['props']['farms']['next_page_url'])
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('farms.data.0.name', 'Kebun Selatan A'));

        $sop = SopTemplate::query()->where('team_id', $this->team->id)->firstOrFail();
        $this->farm->forceFill(['default_sop_template_id' => $sop->id])->save();
        $this->revoke(['sop_template_versions.view']);
        $this->get('/kebun/'.$this->farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farm.default_sop.published_version', null)->where('farm.default_sop.tasks_count', 0));
        $this->revoke(['sop_templates.view']);
        $this->get('/kebun/'.$this->farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farm.default_sop', null));
    }

    public function test_owner_reads_database_dashboard_farms_blocks_and_monitoring(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Dashboard')
            ->where('auth.user.id', $this->owner->id)
            ->where('currentTeam.id', $this->team->id)
            ->where('dashboard.metrics.0.value', 1)
            ->where('dashboard.cycles.running', 2)
            ->where('dashboard.farmOptions.0.id', $this->farm->id));

        $this->get('/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Index')->where('farms.total', 1)
            ->where('farms.data.0.id', $this->farm->id)
            ->where('farms.data.0.name', 'Kebun Cileuksa')
            ->where('summary.count', 1));

        $this->get('/kebun/'.$this->farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Show')->where('farm.id', $this->farm->id)
            ->has('blocks', 2)->where('can.update', false)->where('can.delete', false));

        $block = FarmBlock::query()->where('farm_id', $this->farm->id)->where('code', 'B01')->firstOrFail();
        $this->get('/kebun/'.$this->farm->id.'/blok/'.$block->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Block')->where('block.id', $block->id)
            ->has('cycles', 2)->has('cycles.0.stages', 6)->has('cycles.1.stages', 6)
            ->where('cycles', fn ($rows) => collect($rows)->contains(
                fn ($cycle) => $cycle['status'] === 'active' && $cycle['planted_area_ha'] === '4.2500',
            )));
    }

    public function test_owner_reads_all_activity_types_including_records_without_logbooks(): void
    {
        $this->get('/aktivitas?per_page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Activities')->where('activities.total', 14)->has('activities.data', 2));

        $activity = FarmActivity::query()->where('description', 'Penanaman Blok 1')->firstOrFail();
        $activity->forceFill(['daily_activity_log_id' => null, 'position' => null])->save();
        $this->get('/aktivitas/'.$activity->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Activity')->where('activity.id', $activity->id)
            ->where('activity.farm.id', $this->farm->id)
            ->where('activity.performer.id', $this->petugas->id)
            ->has('activity.photos', 1)
            ->where('activity.photos.0.image_url', fn ($url) => str_contains($url, '/daily-logs/activities/'.$activity->id.'/photos/'))
            ->where('activity.details', fn ($details) => collect($details)->contains(fn ($detail) => $detail['label'] === 'Jumlah benih (kg)' && $detail['value'] === '18,5')
                && collect($details)->contains(fn ($detail) => $detail['label'] === 'Luas tanam (ha)' && $detail['value'] === '4,25'))
            ->where('activity.log', null));
    }

    public function test_activity_filters_scope_rows_and_pagination_totals_together(): void
    {
        $harvest = ActivityType::query()->where('code', 'harvest')->firstOrFail();
        $query = http_build_query([
            'farm_id' => $this->farm->id,
            'performed_by' => $this->petugas->id,
            'activity_type_id' => $harvest->id,
            'from_date' => '2026-08-30',
            'to_date' => '2026-08-30',
        ]);
        $this->get('/aktivitas?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('activities.total', 1)->where('activities.data.0.type.code', 'harvest'));
        $this->get('/aktivitas?from_date=2027-01-01&to_date=2027-01-31')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('activities.total', 0));
        $fertilization = ActivityType::query()->where('code', 'base_fertilization')->firstOrFail();
        $this->get('/aktivitas?activity_type_id='.$fertilization->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('activities.total', 1)->where('activities.data.0.type.code', 'base_fertilization'));
    }

    public function test_owner_reads_sop_versions_and_people_without_developer_accounts(): void
    {
        $sop = SopTemplate::query()->where('team_id', $this->team->id)->firstOrFail();
        $this->get('/lainnya')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/More')
            ->where('ownerNavigation.farms', true)->where('ownerNavigation.finance', true)
            ->where('ownerNavigation.activities', true)->where('ownerNavigation.sops', true)->where('ownerNavigation.users', true));
        $this->get('/lainnya/sop')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Sops')->where('sops.total', 1)->where('sops.data.0.id', $sop->id));
        $this->get('/lainnya/sop/'.$sop->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Sop')->where('template.id', $sop->id)->has('template.versions', 1)
            ->where('template.versions.0.status', 'published')->has('template.versions.0.tasks', 5));
        $this->get('/lainnya/pengguna')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Users')->where('users.total', 3)
            ->where('users.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['role'] !== 'superadmin')));
        $this->get('/lainnya/pengguna/'.$this->petugas->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/User')->where('managedUser.id', $this->petugas->id)
            ->where('managedUser.assigned_farm.id', $this->farm->id));
    }

    public function test_admin_updates_are_visible_on_the_next_owner_read(): void
    {
        $this->actingAs($this->admin)->patch('/admin/farm-management/'.$this->farm->id, [
            'name' => 'Kebun Cileuksa Utara',
        ])->assertRedirect();

        $this->actingAs($this->owner)->get('/kebun/'.$this->farm->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('farm.name', 'Kebun Cileuksa Utara'));
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.farmOptions.0.name', 'Kebun Cileuksa Utara'));
    }

    public function test_admin_batch_labels_refresh_for_owner_and_owner_cannot_edit_them(): void
    {
        $batch = PlantingCycle::query()->where('farm_id', $this->farm->id)->where('status', 'planned')->firstOrFail();
        $block = $batch->block;
        $url = '/kebun/'.$this->farm->id.'/blocks/'.$block->id.'/batches/'.$batch->id;
        $data = ['reserved_area_ha' => $batch->reserved_area_ha, 'subblock_label' => 'SELATAN-02'];

        $this->actingAs($this->admin)->put($url, $data)->assertRedirect();
        $this->actingAs($this->owner)->get('/kebun/'.$this->farm->id.'/blok/'.$block->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cycles.0.subblock_label', 'SELATAN-02'));
        $this->put($url, [...$data, 'subblock_label' => 'SELATAN-03'])->assertForbidden();
        $this->assertSame('SELATAN-02', $batch->fresh()->subblock_label);
    }

    public function test_persisted_petugas_logbook_activity_appears_in_owner_feed(): void
    {
        $this->actingAs($this->petugas)->post('/kebun/'.$this->farm->id.'/daily-logs', [
            'work_date' => '2026-11-04', 'start_time' => '08:00', 'end_time' => null, 'worker_count' => 3,
            'notes' => 'Pemetaan ulang batas blok setelah hujan.',
            'activities' => [[
                'type' => 'block_mapping', 'notes' => 'Pengukuran ulang sisi timur blok',
                'block_mapping' => ['block_name' => 'Blok Timur', 'length_m' => 25, 'width_m' => 40,
                    'row_spacing_cm' => 40, 'plant_spacing_cm' => 20, 'rows_per_bed' => 2],
                'obstacle_status' => 'none',
            ]],
        ])->assertRedirect();

        $activity = FarmActivity::query()->where('description', 'Pengukuran ulang sisi timur blok')->firstOrFail();
        $this->actingAs($this->owner)->get('/aktivitas?search=Pengukuran')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('activities.total', 1)->where('activities.data.0.id', $activity->id));
        $this->get('/aktivitas/'.$activity->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('activity.worker_count', 3)->where('activity.log.end_time', null));
    }

    public function test_farms_without_coordinates_or_observations_remain_in_lists_and_totals(): void
    {
        $farm = $this->makeFarm($this->team, 'Kebun Sukamaju Baru');
        $this->get('/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farms.total', 2)->where('summary.count', 2)
            ->where('farms.data', fn ($rows) => collect($rows)->contains('id', $farm->id)));
        $this->get('/kebun/'.$farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farm.latitude', null)->where('farm.longitude', null)
            ->has('blocks', 0)->where('can.createBlock', false));
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('dashboard.metrics.0.value', 2));
    }

    public function test_unrelated_tenant_data_never_enters_lists_options_or_counts(): void
    {
        $otherTeam = Team::factory()->create(['name' => 'Tenant Sejahtera']);
        $foreign = $this->makeFarm($otherTeam, 'Kebun Tenant Lain');
        $activity = $this->makeActivity($foreign, 'Catatan rahasia tenant lain');
        $this->get('/kebun?team_id='.$otherTeam->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farms.total', 1)->where('summary.count', 1)
            ->where('farms.data.0.id', $this->farm->id));
        $this->get('/aktivitas?team_id='.$otherTeam->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('activities.total', 14)->has('farms', 1)
            ->where('activities.data', fn ($rows) => ! collect($rows)->contains('id', $activity->id)));
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.metrics.0.value', 1)->has('dashboard.farmOptions', 1));
        $this->assertDenied('/kebun/'.$foreign->id);
        $this->assertDenied('/aktivitas/'.$activity->id);
        $this->get('/?farm_id='.$foreign->id)->assertForbidden();
        $this->get('/aktivitas?farm_id='.$foreign->id)->assertForbidden();
    }

    public function test_associated_second_tenant_requires_switching_the_persisted_active_team(): void
    {
        $second = Team::factory()->create(['name' => 'Tenant Kedua Owner']);
        $second->members()->attach($this->owner, ['role' => TeamRole::Member->value]);
        $farm = $this->makeFarm($second, 'Kebun Tenant Kedua');

        $this->get('/kebun?team_id='.$second->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farms.total', 1)->where('farms.data.0.id', $this->farm->id));
        $this->assertDenied('/kebun/'.$farm->id);
        $this->get('/?farm_id='.$farm->id)->assertNotFound();
        $this->get('/aktivitas?farm_id='.$farm->id)->assertNotFound();

        $this->owner->refresh()->switchTeam($second);
        $this->get('/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('currentTeam.id', $second->id)->where('farms.total', 1)->where('farms.data.0.id', $farm->id));
        $this->get('/kebun/'.$farm->id)->assertOk();
        $this->assertDenied('/kebun/'.$this->farm->id);
        $this->get('/?farm_id='.$this->farm->id)->assertNotFound();
        $this->get('/aktivitas?farm_id='.$this->farm->id)->assertNotFound();
    }

    public function test_nested_block_cannot_be_reparented_with_a_different_farm_url(): void
    {
        $other = $this->makeFarm($this->team, 'Kebun Kedua');
        $block = FarmBlock::query()->where('farm_id', $this->farm->id)->firstOrFail();
        $this->assertDenied('/kebun/'.$other->id.'/blok/'.$block->id);
    }

    public function test_owner_switches_tenants_only_through_persisted_membership(): void
    {
        $second = Team::factory()->create(['name' => 'Tenant Kebun Barat']);
        $second->members()->attach($this->owner, ['role' => TeamRole::Member->value]);
        $farm = $this->makeFarm($second, 'Kebun Barat');

        $this->post('/owner/tenant/'.$second->slug)->assertRedirect('/');
        $this->assertSame($second->id, $this->owner->fresh()->current_team_id);
        $this->get('/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('currentTeam.id', $second->id)->where('farms.total', 1)->where('farms.data.0.id', $farm->id));
        $this->assertDenied('/kebun/'.$this->farm->id);

        $outsider = Team::factory()->create(['name' => 'Tenant Tanpa Keanggotaan']);
        $this->post('/owner/tenant/'.$outsider->slug)->assertForbidden();
        $this->assertSame($second->id, $this->owner->fresh()->current_team_id);
    }

    public function test_empty_tenant_returns_empty_database_results_instead_of_demo_records(): void
    {
        $empty = Team::factory()->create(['name' => 'Tenant Baru Owner']);
        $empty->members()->attach($this->owner, ['role' => TeamRole::Member->value]);
        $this->owner->refresh()->switchTeam($empty);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.metrics.0.value', 0)->has('dashboard.farmOptions', 0)->has('dashboard.activities', 0));
        $this->get('/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farms.total', 0)->where('summary.count', 0));
        $this->get('/aktivitas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('activities.total', 0)->has('farms', 0));
        $this->get('/lainnya/sop')->assertOk()->assertInertia(fn (Assert $page) => $page->where('sops.total', 0));
    }

    public function test_current_team_pointer_without_membership_never_grants_owner_access(): void
    {
        $this->team->members()->detach($this->owner->id);
        $this->owner->unsetRelation('teams')->unsetRelation('currentTeam');

        foreach (['/', '/kebun', '/aktivitas', '/lainnya/sop', '/lainnya/pengguna'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/lainnya')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('ownerNavigation.farms', false)->where('ownerNavigation.finance', false)
            ->where('ownerNavigation.activities', false)->where('ownerNavigation.sops', false)->where('ownerNavigation.users', false));
    }

    public function test_unrelated_sops_and_people_are_not_serialized_or_accessible(): void
    {
        $other = Team::factory()->create(['name' => 'Tenant Dokumen Lain']);
        $foreignUser = User::factory()->create(['role' => 'petugas', 'name' => 'Petugas Tenant Lain']);
        $other->members()->attach($foreignUser, ['role' => TeamRole::Member->value]);
        $source = SopTemplate::query()->where('team_id', $this->team->id)->firstOrFail();
        $foreignSop = SopTemplate::create([
            'team_id' => $other->id, 'commodity_id' => $source->commodity_id, 'name' => 'SOP Tenant Lain',
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $this->get('/lainnya/sop')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sops.total', 1)->where('sops.data.0.id', $source->id));
        $this->get('/lainnya/pengguna')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 3)
            ->where('users.data', fn ($rows) => ! collect($rows)->contains('id', $foreignUser->id)));
        $this->assertDenied('/lainnya/sop/'.$foreignSop->id);
        $this->assertDenied('/lainnya/pengguna/'.$foreignUser->id);
    }

    public function test_new_owner_pages_deny_other_application_roles(): void
    {
        foreach ([$this->admin, $this->petugas] as $actor) {
            $this->actingAs($actor);
            foreach (['/kebun', '/kebun/'.$this->farm->id, '/aktivitas', '/lainnya', '/lainnya/sop', '/lainnya/pengguna'] as $url) {
                $this->get($url)->assertForbidden();
            }
        }
    }

    public function test_owner_cannot_mutate_operational_records_through_existing_endpoints(): void
    {
        $block = FarmBlock::query()->where('farm_id', $this->farm->id)->firstOrFail();
        $sop = SopTemplate::query()->where('team_id', $this->team->id)->firstOrFail();
        $this->post('/kebun/'.$this->farm->id.'/blocks/'.$block->id.'/batches', ['reserved_area_ha' => '0.1000'])->assertForbidden();
        $this->patch('/kebun/'.$this->farm->id.'/blocks/'.$block->id, ['name' => 'Tidak boleh berubah'])->assertForbidden();
        $this->delete('/kebun/'.$this->farm->id.'/blocks/'.$block->id)->assertForbidden();
        $this->patch('/admin/farm-management/'.$this->farm->id, ['name' => 'Tidak boleh berubah'])->assertForbidden();
        $this->delete('/admin/sops/'.$sop->id)->assertForbidden();
        $this->delete('/admin/user-management/'.$this->petugas->id)->assertForbidden();
        $this->assertSame('Kebun Cileuksa', $this->farm->fresh()->name);
    }

    public function test_revoked_child_grants_remove_photos_stages_tasks_and_sop_versions(): void
    {
        $this->revoke(['activity_photos.view', 'planting_cycle_stages.view', 'planting_cycle_tasks.view', 'sop_template_versions.view']);
        $activity = FarmActivity::query()->where('description', 'Penanaman Blok 1')->firstOrFail();
        $block = FarmBlock::query()->where('farm_id', $this->farm->id)->where('code', 'B01')->firstOrFail();
        $sop = SopTemplate::query()->where('team_id', $this->team->id)->firstOrFail();

        $this->get('/aktivitas/'.$activity->id)->assertOk()->assertInertia(fn (Assert $page) => $page->has('activity.photos', 0));
        $this->get('/kebun/'.$this->farm->id.'/blok/'.$block->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('cycles.0.stages', 0)->has('cycles.0.tasks', 0));
        $this->get('/lainnya/sop/'.$sop->id)->assertOk()->assertInertia(fn (Assert $page) => $page->has('template.versions', 0));
    }

    public function test_missing_grants_hide_menu_entries_and_return_real_forbidden_responses(): void
    {
        $this->revoke(['farms.view', 'financial_transactions.view', 'users.view', 'sop_templates.view']);
        $this->get('/lainnya')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('ownerNavigation.farms', false)->where('ownerNavigation.finance', false)
            ->where('ownerNavigation.activities', true)->where('ownerNavigation.users', false)->where('ownerNavigation.sops', false));
        $this->get('/kebun')->assertForbidden();
        $this->get('/finance')->assertForbidden();
        $this->get('/lainnya/pengguna')->assertForbidden();
        $this->get('/lainnya/sop')->assertForbidden();
    }

    public function test_revoked_resource_grants_hide_dashboard_shortcuts_and_more_entries(): void
    {
        $this->revoke(['farm_activities.view', 'sop_templates.view', 'users.view']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('ownerNavigation.activities', false)
            ->where('ownerNavigation.sops', false)
            ->where('ownerNavigation.users', false));
        $this->get('/lainnya')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('ownerNavigation.activities', false)
            ->where('ownerNavigation.sops', false)
            ->where('ownerNavigation.users', false));
        $this->get('/aktivitas')->assertForbidden();
        $this->get('/lainnya/sop')->assertForbidden();
        $this->get('/lainnya/pengguna')->assertForbidden();
    }

    public function test_revoked_variety_grant_hides_its_nested_commodity_from_dashboard_and_batches(): void
    {
        $this->revoke(['crop_varieties.view']);
        $block = FarmBlock::query()->where('farm_id', $this->farm->id)->where('code', 'B01')->firstOrFail();

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->has('dashboard.commodities', 0));
        $this->get('/kebun/'.$this->farm->id.'/blok/'.$block->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cycles.0.variety', null));
        $this->get('/kebun/'.$this->farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('observations')->missing('blocks.0.batches.0.variety'));
    }

    public function test_malformed_filters_are_validated_on_the_server(): void
    {
        $this->getJson('/aktivitas?from_date=2026-12-31&to_date=2026-12-01')->assertUnprocessable()->assertJsonValidationErrors('to_date');
        $this->getJson('/aktivitas?farm_id=not-a-uuid')->assertUnprocessable()->assertJsonValidationErrors('farm_id');
        $this->getJson('/kebun?per_page=999')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/?period=365')->assertUnprocessable()->assertJsonValidationErrors('period');
    }

    public function test_finance_uses_the_same_database_records_and_preserves_owner_read_only_actions(): void
    {
        $category = FinancialCategory::create(['name' => 'Penjualan Panen Owner', 'transaction_type' => 'income']);
        $transaction = FinancialTransaction::create([
            'farm_id' => $this->farm->id, 'category_id' => $category->id, 'recorded_by' => $this->admin->id,
            'transaction_date' => '2026-11-04', 'transaction_type' => 'income', 'amount' => '1750000.25',
            'description' => 'Penjualan hasil panen kebun Cileuksa',
        ]);
        $this->get('/finance?year=2026&period=Q4')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farmer/Finance')->where('summary.income', '1750000.25')
            ->where('transactions.data.0.id', $transaction->id));
        $this->delete('/finance/transactions/'.$transaction->id)->assertForbidden();
        $this->assertDatabaseHas('financial_transactions', ['id' => $transaction->id, 'amount' => '1750000.25']);
    }

    public function test_legacy_demo_bookmarks_redirect_to_database_destinations(): void
    {
        $activity = FarmActivity::query()->firstOrFail();
        $this->get('/kebun/detail?id='.$this->farm->id)->assertRedirect('/kebun/'.$this->farm->id);
        $this->get('/kebun/detail?id=kebun-001')->assertRedirect('/kebun');
        $this->get('/rincian-aktivitas?id='.$activity->id)->assertRedirect('/aktivitas/'.$activity->id);
        $this->get('/rincian-aktivitas?id=activity-demo-001')->assertRedirect('/aktivitas');
        $this->get('/laporan')->assertRedirect('/');
    }

    private function makeFarm(Team $team, string $name): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => '2.5000', 'land_tenure' => 'owned']);
        $farm->forceFill(['team_id' => $team->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function makeActivity(Farm $farm, string $description): FarmActivity
    {
        return FarmActivity::create([
            'farm_id' => $farm->id, 'activity_type_id' => ActivityType::query()->where('code', 'irrigation')->firstOrFail()->id,
            'performed_by' => $this->petugas->id, 'activity_date' => '2026-11-04 09:00:00+07',
            'description' => $description, 'status' => 'completed',
        ]);
    }

    /** @param list<string> $abilities */
    private function revoke(array $abilities): void
    {
        $roles = app(RolePermissions::class)->roles();
        foreach ($abilities as $ability) {
            unset($roles['owner'][$ability]);
        }
        config(['access.roles' => $roles]);
    }

    private function assertDenied(string $url): void
    {
        $this->assertContains($this->get($url)->status(), [403, 404], $url.' must not expose an out-of-scope record.');
    }
}

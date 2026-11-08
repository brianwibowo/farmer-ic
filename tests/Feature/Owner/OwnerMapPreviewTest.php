<?php

namespace Tests\Feature\Owner;

use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Real HTTP/persistence integration on the guarded, separate PostgreSQL test database. */
class OwnerMapPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create(['name' => 'Tenant Peta Owner', 'is_personal' => false]);
        foreach ([$this->owner, $this->admin] as $actor) {
            $this->team->members()->attach($actor, ['role' => TeamRole::Member->value]);
            $actor->switchTeam($this->team);
        }
        $this->actingAs($this->owner);
    }

    public function test_map_preview_reuses_scoped_crop_rows_and_exposes_only_the_actual_pic_name(): void
    {
        $farm = $this->farm('Kebun A');
        $this->cycle($farm, 'Jagung', 'Bisi 18', 'active');
        $this->cycle($farm, 'Jagung', 'Pertiwi', 'harvested');
        $this->cycle($farm, 'Kedelai', 'Anjasmoro', 'harvested');
        $this->cycle($farm, 'Kentang', 'Granola', 'planned');
        $this->cycle($farm, 'Tomat', 'Servo', 'cancelled');
        $pic = $this->user('Petugas PIC Kebun A', $farm);
        $farm->forceFill(['pic_user_id' => $pic->id])->save();
        $before = $pic->refresh()->getRawOriginal();

        $response = $this->get('/')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.landMap.farms.0.id', $farm->id)
            ->where('dashboard.landMap.farms.0.detail_url', '/kebun/'.$farm->id)
            ->where('dashboard.landMap.farms.0.pic', ['id' => $pic->id, 'name' => $pic->name])
            ->has('dashboard.landMap.farms.0.crops', 2)
            ->where('dashboard.landMap.farms.0.crops.0.name', 'Jagung')
            ->where('dashboard.landMap.farms.0.crops.0.varieties', ['Bisi 18', 'Pertiwi'])
            ->where('dashboard.landMap.farms.0.crops.1.name', 'Kedelai')
            ->where('dashboard.landMap.farms.0.crops', $response->viewData('page')['props']['landTable']['rows'][0]['crops'])
            ->missing('dashboard.landMap.farms.0.pic.email')
            ->missing('dashboard.landMap.farms.0.pic.phone_number'));
        $this->assertSame($before, $pic->refresh()->getRawOriginal());
    }

    public function test_pic_requires_the_farm_pointer_and_exact_matching_active_petugas_assignment(): void
    {
        $farm = $this->farm('Kebun A');
        $other = $this->farm('Kebun B');
        $assignedOnly = $this->user('Petugas hanya ditugaskan', $farm);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('dashboard.landMap.farms.0.pic', null));

        $otherPic = $this->user('PIC kebun B', $other);
        $other->forceFill(['pic_user_id' => $otherPic->id])->save();
        $farm->forceFill(['pic_user_id' => $otherPic->id])->save();
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.landMap.farms.0.pic', null)
            ->where('dashboard.landMap.farms.1.pic.name', $otherPic->name));

        foreach ([
            $this->user('Petugas nonaktif', $farm, active: false),
            $this->user('Admin bukan PIC', $farm, role: 'admin'),
            $this->user('Petugas tanpa assignment', null),
        ] as $invalid) {
            $farm->forceFill(['pic_user_id' => $invalid->id])->save();
            $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('dashboard.landMap.farms.0.pic', null));
        }

        $farm->forceFill(['pic_user_id' => $assignedOnly->id])->save();
        $assignedOnly->delete();
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('dashboard.landMap.farms.0.pic', null));
    }

    public function test_foreign_membership_and_other_tenant_records_never_enter_the_map_preview(): void
    {
        $farm = $this->farm('Kebun A');
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreignFarm = $this->farm('Kebun Rahasia', $foreignTeam);
        $foreignPic = $this->user('PIC tenant rahasia', $farm, team: $foreignTeam);
        $farm->forceFill(['pic_user_id' => $foreignPic->id])->save();
        $this->cycle($foreignFarm, 'Komoditas rahasia', 'Varietas rahasia');

        $this->get('/?team_id='.$foreignTeam->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.landMap.farms', 1)
            ->where('dashboard.landMap.farms.0.id', $farm->id)
            ->where('dashboard.landMap.farms.0.pic', null)
            ->has('dashboard.landMap.farms.0.crops', 0));
        $this->get('/?farm_id='.$foreignFarm->id)->assertForbidden();
    }

    public function test_related_resource_grants_are_applied_before_serializing_pic_and_crops(): void
    {
        $farm = $this->farm('Kebun A');
        $pic = $this->user('PIC Kebun A', $farm);
        $farm->forceFill(['pic_user_id' => $pic->id])->save();
        $this->cycle($farm, 'Jagung', 'Bisi 18');
        $roles = app(RolePermissions::class)->roles();
        unset($roles['owner']['users.view'], $roles['owner']['crop_varieties.view']);
        config(['access.roles' => $roles]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.landMap.farms.0.pic', null)
            ->has('dashboard.landMap.farms.0.crops', 0));
    }

    public function test_previous_farm_filter_keeps_all_scoped_popup_data_and_admin_payload_unchanged(): void
    {
        $first = $this->farm('Kebun A');
        $second = $this->farm('Kebun B');
        $pic = $this->user('PIC Kebun B', $second);
        $second->forceFill(['pic_user_id' => $pic->id])->save();
        $this->cycle($second, 'Padi', 'Ciherang');
        $this->get('/?farm_id='.$second->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.filters.farm_id', null)
            ->has('dashboard.landMap.farms', 2)
            ->where('dashboard.landMap.farms.0.id', $first->id)
            ->where('dashboard.landMap.farms.0.pic', null)
            ->has('dashboard.landMap.farms.0.crops', 0)
            ->where('dashboard.landMap.farms.1.id', $second->id)
            ->where('dashboard.landMap.farms.1.pic.name', $pic->name)
            ->where('dashboard.landMap.farms.1.crops.0.name', 'Padi'));

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('dashboard.landMap.farms.0.crops')
            ->missing('dashboard.landMap.farms.0.pic')
            ->missing('dashboard.landMap.farms.0.detail_url'));
    }

    private function farm(string $name, ?Team $team = null): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => '8.0000', 'city' => 'Kabupaten Bogor', 'latitude' => '-6.5', 'longitude' => '106.9']);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function user(string $name, ?Farm $farm, string $role = 'petugas', bool $active = true, ?Team $team = null): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role, 'is_active' => $active, 'assigned_farm_id' => $farm?->id]);
        ($team ?? $this->team)->members()->attach($user, ['role' => TeamRole::Member->value]);

        return $user;
    }

    private function cycle(Farm $farm, string $commodityName, string $varietyName, string $status = 'active'): void
    {
        $commodity = Commodity::firstOrCreate(['name' => $commodityName]);
        $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => $varietyName]);
        PlantingCycle::create(['farm_id' => $farm->id, 'variety_id' => $variety->id, 'planted_at' => '2026-09-01', 'planted_area_ha' => '1.0000', 'status' => $status]);
    }
}

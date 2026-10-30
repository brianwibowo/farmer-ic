<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Portal admission and Laravel/Inertia navigation on the dedicated PostgreSQL test schema. */
class PetugasPortalTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            'home' => ['/petugas', 'petugas/Home'],
            'garden' => ['/petugas/kebun', 'petugas/Kebun'],
            'activity' => ['/petugas/aktivitas', 'petugas/Aktivitas'],
            'report' => ['/petugas/laporan', 'petugas/Laporan'],
        ];
    }

    #[DataProvider('pages')]
    public function test_petugas_can_open_empty_pages_without_an_assigned_farm(string $url, string $component): void
    {
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->assertNull($actor->assigned_farm_id);
        $this->actingAs($actor)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component($component)->where('auth.user.id', $actor->id)->where('navigation.admin', false)
            ->where('navigation.finance.transactions', false)->missing('farms')->missing('activities')->missing('batches'));
    }

    #[DataProvider('pages')]
    public function test_guests_cannot_open_portal_pages(string $url, string $component): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    public function test_application_roles_not_team_ownership_control_admission(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->assertTrue($actor->ownsTeam($actor->currentTeam));
            $this->assertFalse(Gate::forUser($actor)->allows('petugas.access'));
            foreach (self::pages() as [$url]) {
                $this->actingAs($actor)->get($url)->assertForbidden();
            }
        }
        $developer = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($developer)->get('/petugas')->assertOk();
        $this->assertTrue(Gate::forUser($developer)->allows('petugas.access'));
    }

    public function test_login_and_authenticated_login_page_use_petugas_home(): void
    {
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->post('/login', ['email' => $actor->email, 'password' => 'password'])->assertRedirect('/petugas');
        $this->assertAuthenticatedAs($actor);
        $this->get('/login')->assertRedirect('/petugas');
    }

    public function test_allowed_intended_pages_are_retained_and_denied_admin_intent_is_removed(): void
    {
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->withSession(['url.intended' => url('/petugas/aktivitas')])->post('/login', ['email' => $actor->email, 'password' => 'password'])
            ->assertRedirect('/petugas/aktivitas');
        auth()->logout();
        $this->withSession(['url.intended' => url('/admin/farm-management')])->post('/login', ['email' => $actor->email, 'password' => 'password'])
            ->assertRedirect('/petugas');
    }

    public function test_owner_does_not_land_on_a_denied_petugas_intended_page(): void
    {
        $actor = User::factory()->create(['role' => 'owner']);
        $this->withSession(['url.intended' => url('/petugas/kebun')])->post('/login', ['email' => $actor->email, 'password' => 'password'])
            ->assertRedirect('/');
    }

    public function test_missing_or_changed_application_role_denies_access(): void
    {
        $actor = User::factory()->create(['role' => 'petugas']);
        $actor->setRelation('role', null);
        $this->assertFalse(Gate::forUser($actor)->allows('petugas.access'));
        $actor->setRelation('role', new Role(['name' => 'unrecognized']));
        $this->assertFalse(Gate::forUser($actor)->allows('petugas.access'));
        $actor->setRelation('role', Role::where('name', 'owner')->firstOrFail());
        $this->actingAs($actor)->get('/petugas')->assertForbidden();
    }

    public function test_removing_the_petugas_role_definition_denies_portal_admission(): void
    {
        $actor = User::factory()->create(['role' => 'petugas']);
        $roles = app(RolePermissions::class)->roles();
        unset($roles['petugas']);
        config(['access.roles' => $roles]);
        $this->actingAs($actor)->get('/petugas')->assertForbidden();
    }
}

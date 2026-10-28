<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileAndForbiddenTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_see_minimal_account_identity_without_admin_navigation_on_public_pages(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
            ->where('account', ['name' => $owner->name, 'email' => $owner->email])
            ->where('currentTeam', null)->where('teams', [])
            ->where('navigation.admin', false)->where('navigation.farms', false));
    }

    public function test_logout_ends_the_real_owner_session_and_clears_profile_identity(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('account.email', $owner->email));
        $this->post('/logout', [], ['X-Inertia' => 'true'])->assertRedirect('/');
        $this->assertGuest();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('account', null));
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_browser_permission_denials_render_the_forbidden_page_with_status_403(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin/dashboard')->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('errors/Forbidden')
                ->where('status', 403)->where('canLogout', true)
                ->where('currentTeam', null)->where('teams', [])
                ->where('navigation.admin', false)->missing('exception')->missing('trace'));
    }

    public function test_inertia_permission_denials_are_valid_inertia_responses(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin/farm-management', [
            'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/admin/farm-management')),
        ])->assertForbidden()->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'errors/Forbidden')->assertJsonPath('props.status', 403);
    }

    public function test_web_json_requests_keep_their_json_error_contract(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->getJson('/admin/dashboard')->assertForbidden()
            ->assertJsonStructure(['message'])->assertJsonMissingPath('component');
    }

    public function test_api_permission_denials_stay_json_even_with_a_browser_accept_header(): void
    {
        Route::get('/api/__tests/forbidden', fn () => abort(403, 'API permission denied.'));
        $this->get('/api/__tests/forbidden', ['Accept' => 'text/html'])->assertForbidden()
            ->assertHeader('Content-Type', 'application/json')->assertJsonPath('message', 'API permission denied.')
            ->assertJsonMissingPath('component');
    }

    public function test_guest_forbidden_page_has_no_logout_action_and_other_statuses_are_unchanged(): void
    {
        Route::get('/__tests/forbidden', fn () => abort(403))->middleware('web');
        $this->get('/__tests/forbidden')->assertForbidden()->assertInertia(fn (Assert $page) => $page
            ->component('errors/Forbidden')->where('canLogout', false)->where('account', null));
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/__tests/missing-page')->assertNotFound();
    }
}

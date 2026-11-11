<?php

namespace Tests\Feature\Owner;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Route integration on the guarded, dedicated PostgreSQL test database. */
class ExecutiveSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_the_isolated_json_demo_without_operational_props(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/ringkasan-eksekutif?team_id=999&farm_id=foreign')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/ExecutiveSummary')
            ->missing('dashboard')->missing('farms')->missing('contracts'));
    }

    public function test_admin_and_petugas_receive_forbidden_and_guests_must_sign_in(): void
    {
        $this->get('/ringkasan-eksekutif')->assertRedirect('/login');
        foreach (['admin', 'petugas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/ringkasan-eksekutif')->assertForbidden();
        }
    }

    public function test_named_route_uses_the_existing_authenticated_owner_boundary(): void
    {
        $route = app('router')->getRoutes()->getByName('owner.executive-summary');
        $this->assertNotNull($route);
        $this->assertSame('ringkasan-eksekutif', $route->uri());
        foreach (['auth', 'verified', 'can:owner.access'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }
}

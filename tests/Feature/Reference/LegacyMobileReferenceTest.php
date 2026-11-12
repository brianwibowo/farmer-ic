<?php

namespace Tests\Feature\Reference;

use App\Enums\TeamRole;
use App\Http\Controllers\HomeController;
use App\Models\AiConversation;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Uses only the guarded, isolated PostgreSQL test database. */
class LegacyMobileReferenceTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        '/referensi' => ['home', 'Dashboard'],
        '/referensi/kebun' => ['kebun', 'Kebun'],
        '/referensi/kebun/detail' => ['kebun.detail', 'KebunDetail'],
        '/referensi/kebun/aktivitas' => ['kebun.aktivitas', 'KebunAktivitas'],
        '/referensi/aktivitas' => ['aktivitas', 'Aktivitas'],
        '/referensi/rincian-aktivitas' => ['rincian-aktivitas', 'RincianAktivitas'],
        '/referensi/laporan' => ['laporan', 'Laporan'],
        '/referensi/finance' => ['finance', 'Finance'],
        '/referensi/ai' => ['ai', 'Index'],
    ];

    public function test_reference_pages_require_the_existing_login(): void
    {
        foreach (array_keys(self::PAGES) as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_all_existing_roles_can_read_the_historical_pages_without_a_team_or_provisioning_records(): void
    {
        foreach (['superadmin', 'owner', 'admin', 'petugas'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $actor->forceFill(['current_team_id' => null])->save();
            $actor->unsetRelation('currentTeam');
            $this->actingAs($actor);
            $before = $this->domainCounts();

            foreach (self::PAGES as $url => [$name, $component]) {
                $this->assertSame($url, route('reference.'.$name, absolute: false));
                $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->component('reference/'.$component, false)
                    ->where('reference.sourceCommit', '73f2555d91d0fb2d6acb7bc70813921edd479ea4')
                    ->where('reference.readOnly', true)
                    ->where('reference.basePath', '/referensi')
                    ->has('pendingInvitations', 0));
            }

            $this->assertSame($before, $this->domainCounts());
            $this->assertNull($actor->refresh()->current_team_id);
        }
    }

    public function test_query_input_cannot_select_a_different_inertia_component(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/referensi?referencePage=users/Index&page=Dashboard')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('reference/Dashboard', false));
    }

    public function test_reference_namespace_cannot_dispatch_live_team_ai_or_finance_mutations(): void
    {
        $actor = User::factory()->create(['role' => 'owner']);
        $team = Team::factory()->create(['slug' => 'referensi', 'is_personal' => false]);
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
        $actor->switchTeam($team);
        $this->actingAs($actor);
        $before = $this->domainCounts();

        $this->postJson('/referensi/ai/messages', ['message' => ''])->assertNotFound();
        $this->postJson('/referensi/ai/conversations')->assertNotFound();
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->json($method, '/referensi/finance')->assertNotFound();
            $this->json($method, '/referensi/kebun')->assertNotFound();
        }
        $this->get('/referensi/not-a-page')->assertNotFound();
        $this->assertSame($before, $this->domainCounts());
    }

    public function test_current_route_names_actions_and_role_protection_are_not_replaced(): void
    {
        $this->assertSame('/', route('home', absolute: false));
        $this->assertSame('/kebun', route('kebun', absolute: false));
        $this->assertSame('/finance', route('finance', absolute: false));
        $this->assertSame('/aktivitas', route('aktivitas', absolute: false));
        $this->assertSame(HomeController::class, Route::getRoutes()->getByName('home')->getActionName());
        $this->assertSame(\App\Http\Controllers\FarmManagementController::class.'@index', Route::getRoutes()->getByName('kebun')->getActionName());
        $this->assertContains('can:owner.access', Route::getRoutes()->getByName('kebun')->gatherMiddleware());
        $this->actingAs(User::factory()->create(['role' => 'petugas']))->get('/kebun')->assertForbidden();
    }

    /** @return array<string, int> */
    private function domainCounts(): array
    {
        return [
            'users' => User::count(),
            'teams' => Team::count(),
            'farms' => Farm::count(),
            'transactions' => FinancialTransaction::count(),
            'conversations' => AiConversation::count(),
        ];
    }
}

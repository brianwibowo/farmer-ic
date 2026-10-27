<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FarmerGuestPagesTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function pages(): array
    {
        return [
            'home' => ['/', 'farmer/Dashboard'],
            'finance' => ['/finance', 'farmer/Finance'],
            'farms' => ['/kebun', 'farmer/Kebun'],
            'farm detail' => ['/kebun/detail', 'farmer/KebunDetail'],
            'farm activities' => ['/kebun/aktivitas', 'farmer/KebunAktivitas'],
            'activities' => ['/aktivitas', 'farmer/Aktivitas'],
            'activity detail' => ['/rincian-aktivitas', 'farmer/RincianAktivitas'],
            'reports' => ['/laporan', 'farmer/Laporan'],
            'AI interface' => ['/bypass', 'farmer/Index'],
        ];
    }

    #[DataProvider('pages')]
    public function test_pages_open_without_provisioning_users_or_teams(string $url, string $component): void
    {
        if ($url === '/bypass') {
            $this->get($url)->assertRedirect(route('login'));

            return;
        }

        // No migrations: any legacy user/team lookup or insert makes this fail.
        DB::connection()->enableQueryLog();

        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('auth.user', null)
            ->where('currentTeam', null)
            ->where('teams', []));

        $this->assertGuest();
        $this->assertSame([], DB::connection()->getQueryLog());
    }

    public function test_old_login_session_does_not_trigger_legacy_user_queries_on_public_pages(): void
    {
        $key = Auth::guard('web')->getName();
        DB::connection()->enableQueryLog();

        $this->withSession([$key => 1])->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('farmer/Dashboard')
                ->where('auth.user', null)
                ->where('currentTeam', null)
                ->where('teams', []));

        $this->assertSame([], DB::connection()->getQueryLog());
    }

    public function test_existing_ai_api_still_requires_authentication(): void
    {
        $this->postJson('/kebun-petani/ai/messages', ['message' => 'Kondisi kebun'])
            ->assertUnauthorized();
    }
}

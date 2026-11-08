<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The former public Owner demos now require the existing Laravel login. */
class FarmerGuestPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return [
            'farms' => ['/kebun'],
            'legacy farm detail' => ['/kebun/detail'],
            'legacy farm activities' => ['/kebun/aktivitas'],
            'activities' => ['/aktivitas'],
            'legacy activity detail' => ['/rincian-aktivitas'],
            'retired reports' => ['/laporan'],
            'more' => ['/lainnya'],
            'SOP' => ['/lainnya/sop'],
            'users' => ['/lainnya/pengguna'],
            'AI interface' => ['/bypass'],
            'finance' => ['/finance'],
        ];
    }

    #[DataProvider('pages')]
    public function test_owner_pages_require_login_without_provisioning_demo_identity(string $url): void
    {
        $this->get($url)->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Team::query()->count());
    }

    public function test_existing_ai_api_still_requires_authentication(): void
    {
        $this->postJson('/kebun-petani/ai/messages', ['message' => 'Kondisi kebun'])
            ->assertUnauthorized();
    }
}

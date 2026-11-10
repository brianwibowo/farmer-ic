<?php

namespace Tests\Feature\Owner;

use App\Models\Farm;
use App\Models\FarmLegality;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Database\Seeders\DemoMonitoringSeeder;
use Database\Seeders\DemoOwnerPortfolioSeeder;
use Database\Seeders\DemoSopSeeder;
use Database\Seeders\FarmLegalitySeeder;
use Database\Seeders\SopActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** Seed persistence integration on the guarded separate PostgreSQL test database. */
class FarmLegalitySeederTest extends TestCase
{
    use RefreshDatabase;

    private function prerequisites(): void
    {
        $this->seed([
            DemoIdentitySeeder::class, DemoFarmSeeder::class, DemoActivitySeeder::class,
            SopActivityTypeSeeder::class, DemoSopSeeder::class, DemoMonitoringSeeder::class,
            DemoOwnerPortfolioSeeder::class,
        ]);
    }

    public function test_seeds_varied_per_farm_records_without_fabricating_pks_for_owned_land(): void
    {
        $this->prerequisites();
        $this->seed(FarmLegalitySeeder::class);
        $this->assertDatabaseCount('farm_legalities', 7);
        $records = FarmLegality::query()->with('farm')->get();
        $this->assertCount(4, $records->filter(fn (FarmLegality $record) => $record->farm->land_tenure === 'owned'));
        $this->assertCount(3, $records->filter(fn (FarmLegality $record) => $record->farm->land_tenure !== 'owned'));
        foreach ($records as $record) {
            $this->assertNotSame('', trim($record->institution));
            $this->assertNotSame('', trim($record->summary));
            $this->assertStringNotContainsStringIgnoringCase('demo', $record->institution.' '.$record->summary);
            if ($record->farm->land_tenure === 'owned') {
                $this->assertNull($record->pks_started_at);
                $this->assertNull($record->pks_ends_at);
            } else {
                $this->assertNotNull($record->pks_started_at);
                $this->assertNotNull($record->pks_ends_at);
                $this->assertTrue($record->pks_ends_at->greaterThanOrEqualTo($record->pks_started_at));
                $this->assertSame($record->farm->land_tenure_party, $record->institution);
            }
        }
        $this->assertGreaterThan(1, $records->pluck('summary')->unique()->count());
        $this->assertGreaterThan(1, $records->whereNotNull('pks_started_at')->pluck('pks_started_at')->unique()->count());
    }

    public function test_rerun_preserves_record_ids_user_edits_farm_fields_and_account_credentials(): void
    {
        $this->prerequisites();
        $farms = Farm::query()->orderBy('id')->get()->map(fn (Farm $farm) => $farm->getRawOriginal())->all();
        $accounts = User::query()->orderBy('id')->get()->map(fn (User $user) => $user->getRawOriginal())->all();
        $this->seed(FarmLegalitySeeder::class);
        $record = FarmLegality::query()->firstOrFail();
        $record->update(['summary' => 'Ringkasan hasil verifikasi dokumen yang diperbarui pengguna.']);
        $before = FarmLegality::query()->orderBy('id')->get()->map(fn (FarmLegality $item) => $item->getRawOriginal())->all();

        $this->seed(FarmLegalitySeeder::class);

        $this->assertSame($before, FarmLegality::query()->orderBy('id')->get()->map(fn (FarmLegality $item) => $item->getRawOriginal())->all());
        $this->assertSame($farms, Farm::query()->orderBy('id')->get()->map(fn (Farm $farm) => $farm->getRawOriginal())->all());
        $this->assertSame($accounts, User::query()->orderBy('id')->get()->map(fn (User $user) => $user->getRawOriginal())->all());
    }

    public function test_same_named_foreign_farm_receives_no_seed_record(): void
    {
        $this->prerequisites();
        $team = Team::factory()->create(['is_personal' => false]);
        $farm = new Farm(['name' => 'Kebun Cileuksa', 'land_tenure' => 'owned', 'total_area_ha' => '20.0000']);
        $farm->forceFill(['team_id' => $team->id, 'created_by' => User::query()->value('id')])->save();
        $before = $farm->refresh()->getRawOriginal();

        $this->seed(FarmLegalitySeeder::class);

        $this->assertDatabaseCount('farm_legalities', 7);
        $this->assertDatabaseMissing('farm_legalities', ['farm_id' => $farm->id]);
        $this->assertSame($before, $farm->refresh()->getRawOriginal());
    }

    public function test_missing_trusted_identities_fail_before_writes(): void
    {
        try {
            $this->seed(FarmLegalitySeeder::class);
            $this->fail('Legalitas seed requires the persisted prototype identities.');
        } catch (RuntimeException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        $this->assertDatabaseCount('farm_legalities', 0);
    }

    public function test_production_environment_is_rejected_before_writes(): void
    {
        $this->app->instance('env', 'production');
        try {
            app(FarmLegalitySeeder::class)->run();
            $this->fail('Legalitas seed must never run in production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('farm_legalities', 0);
    }

    public function test_changed_superadmin_role_is_rejected_before_seed_writes(): void
    {
        $this->prerequisites();
        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])->firstOrFail();
        $actor->forceFill(['role_id' => Role::query()->where('name', 'admin')->firstOrFail()->id])->save();
        try {
            $this->seed(FarmLegalitySeeder::class);
            $this->fail('Legalitas seed requires the trusted Superadmin actor.');
        } catch (RuntimeException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        $this->assertDatabaseCount('farm_legalities', 0);
    }
}

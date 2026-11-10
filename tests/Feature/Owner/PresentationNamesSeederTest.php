<?php

namespace Tests\Feature\Owner;

use App\Models\ActivityPhoto;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FertilizationDetail;
use App\Models\FinancialTransaction;
use App\Models\PlantingCycle;
use App\Models\PlantObservation;
use App\Models\Team;
use App\Models\User;
use App\Models\WeatherRecord;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoFinanceSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Database\Seeders\DemoMonitoringSeeder;
use Database\Seeders\DemoOperationsSeeder;
use Database\Seeders\DemoOwnerPortfolioData;
use Database\Seeders\DemoOwnerPortfolioSeeder;
use Database\Seeders\DemoSopSeeder;
use Database\Seeders\PresentationNamesSeeder;
use Database\Seeders\SopActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** Persistence checks use the separate, guarded PostgreSQL test database. */
class PresentationNamesSeederTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): Team
    {
        $this->seed([
            DemoIdentitySeeder::class, DemoFarmSeeder::class,
            SopActivityTypeSeeder::class, DemoSopSeeder::class, DemoActivitySeeder::class,
            DemoMonitoringSeeder::class,
            DemoFinanceSeeder::class, DemoOperationsSeeder::class, DemoOwnerPortfolioSeeder::class,
        ]);

        return Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
    }

    /** @return array<string, array<int, object>> */
    private function protectedData(): array
    {
        $snapshot = [];
        foreach (['users' => ['id', 'email', 'password', 'role_id', 'current_team_id', 'assigned_farm_id', 'created_at', 'updated_at'],
            'team_members' => ['team_id', 'user_id', 'role', 'created_at', 'updated_at'],
            'teams' => ['id', 'slug', 'is_personal', 'created_at', 'updated_at'],
            'farms' => ['id', 'team_id', 'owner_id', 'created_by', 'total_area_ha', 'created_at', 'updated_at'],
            'farm_blocks' => ['id', 'farm_id', 'area_ha', 'created_at', 'updated_at'],
            'farm_activities' => ['id', 'farm_id', 'planting_cycle_id', 'activity_date', 'status', 'created_at', 'updated_at'],
            'activity_photos' => ['id', 'activity_id', 'image_url', 'uploaded_at'],
            'weather_records' => ['id', 'farm_id', 'recorded_at', 'temperature_c', 'humidity_percentage'],
            'plant_observations' => ['id', 'activity_id', 'planting_cycle_id', 'observed_at', 'observed_by'],
            'planting_cycles' => ['id', 'farm_id', 'block_id', 'planted_at', 'reserved_area_ha', 'planted_area_ha', 'estimated_weight_ton', 'created_at'],
            'financial_transactions' => ['id', 'farm_id', 'planting_cycle_id', 'amount', 'transaction_date', 'created_at'],
        ] as $table => $columns) {
            $snapshot[$table] = DB::table($table)->select($columns)->orderBy($columns[0])->get()->all();
        }

        return $snapshot;
    }

    public function test_cleanup_updates_existing_fixture_copy_in_place_and_keeps_all_relationships_and_credentials(): void
    {
        config(['app.name' => 'Kebun Nusantara']);
        $team = $this->fixtures();
        $team->forceFill(['name' => 'Farm IC Demo'])->saveQuietly();
        foreach (DemoIdentitySeeder::ACCOUNTS as $account) {
            User::query()->where('email', $account['email'])->update(['name' => $account['name'].' Demo']);
        }
        $base = Farm::query()->where('name', DemoFarmSeeder::FARM_NAME)->firstOrFail();
        $base->update(['name' => 'Kebun Cileuksa Demo']);
        DailyActivityLog::query()->where('farm_id', $base->id)->update(['notes' => 'Pemetaan awal untuk persiapan bedengan demo.']);
        WeatherRecord::query()->where('farm_id', $base->id)->update(['data_source' => 'Stasiun cuaca demo']);
        FinancialTransaction::query()->where('farm_id', $base->id)->where('description', 'Pembelian benih jagung Bisi 18 untuk siklus')
            ->update(['description' => 'Pembelian benih jagung Bisi 18 untuk siklus demo']);
        $fertilization = FarmActivity::query()->where('farm_id', $base->id)->where('description', 'Pemupukan susulan pertama Blok 1')->firstOrFail();
        FertilizationDetail::query()->where('activity_id', $fertilization->id)->update(['brand_name' => 'NPK Demo']);
        foreach (DemoOwnerPortfolioData::sites() as $site) {
            $farmId = DemoOwnerPortfolioData::id($team, 'farm:'.$site['key']);
            Farm::query()->whereKey($farmId)->update(['name' => $site['name'].' Demo']);
            FarmBlock::query()->where('farm_id', $farmId)->update([
                'description' => 'Blok fisik pada skenario portofolio '.$site['name'].' Demo, snapshot 5 November 2026.',
            ]);
            foreach ($site['batches'] as $batch) {
                PlantingCycle::query()->where('farm_id', $farmId)->where('subblock_label', $batch['subblock_label'])
                    ->update(['subblock_label' => 'DEMO-'.$batch['subblock_label']]);
                $key = $site['key'].':'.$batch['key'];
                FarmActivity::query()->whereKey(DemoOwnerPortfolioData::id($team, 'activity:'.$key.':planting'))
                    ->update(['description' => 'Penanaman '.$site['commodity'].' DEMO-'.$batch['subblock_label']]);
                PlantObservation::query()->whereKey(DemoOwnerPortfolioData::id($team, 'observation:'.$key))
                    ->update(['notes' => 'Observasi contoh portofolio demo pada petak ukur; bukan hasil analisis AI.']);
                ActivityPhoto::query()->whereKey(DemoOwnerPortfolioData::id($team, 'photo:'.$key))
                    ->update(['label' => 'Ilustrasi lahan demo', 'caption' => 'Ilustrasi lokal untuk contoh portofolio '.$site['name'].' Demo; bukan foto lapangan aktual.']);
            }
            WeatherRecord::query()->where('farm_id', $farmId)->update(['data_source' => 'Stasiun contoh portofolio demo']);
        }
        $before = $this->protectedData();

        $this->seed(PresentationNamesSeeder::class);

        $this->assertEquals($before, $this->protectedData());
        $this->assertSame('Kebun Nusantara', $team->refresh()->name);
        $this->assertSame('farm-ic-demo', $team->slug);
        $this->assertSame(DemoFarmSeeder::FARM_NAME, $base->refresh()->name);
        foreach (DemoIdentitySeeder::ACCOUNTS as $account) {
            $this->assertSame($account['name'], User::query()->where('email', $account['email'])->value('name'));
        }
        foreach (DemoOwnerPortfolioData::sites() as $site) {
            $this->assertSame($site['name'], Farm::query()->findOrFail(DemoOwnerPortfolioData::id($team, 'farm:'.$site['key']))->name);
        }
        $this->assertDatabaseHas('fertilization_details', ['activity_id' => $fertilization->id, 'brand_name' => 'NPK']);
        $this->assertDatabaseHas('daily_activity_logs', ['farm_id' => $base->id, 'notes' => 'Pemetaan awal untuk persiapan bedengan.']);
        $this->assertSame(0, PlantingCycle::query()->where('subblock_label', 'like', 'DEMO%')->count());
        $this->assertSame(0, ActivityPhoto::query()->where('label', 'Ilustrasi lahan demo')->count());
        $this->assertSame(0, PlantObservation::query()->where('notes', 'like', '%portofolio demo%')->count());
        $clean = $this->protectedData();
        $this->seed(PresentationNamesSeeder::class);
        $this->assertEquals($clean, $this->protectedData());
    }

    public function test_fresh_sources_and_legacy_base_adoption_do_not_create_duplicate_farms(): void
    {
        config(['app.name' => 'Bumi Hijauan']);
        $team = $this->fixtures();
        $this->assertSame(config('app.name'), $team->name);
        $base = Farm::query()->where('name', DemoFarmSeeder::FARM_NAME)->firstOrFail();
        $base->update(['name' => 'Kebun Cileuksa Demo']);
        $this->seed(DemoFarmSeeder::class);
        $this->seed(DemoOwnerPortfolioSeeder::class);
        $this->assertSame(DemoFarmSeeder::FARM_NAME, $base->refresh()->name);
        $this->assertSame(6, Farm::query()->where('team_id', $team->id)->count());
        foreach ([Farm::class => ['name'], User::class => ['name'], Team::class => ['name'],
            FarmBlock::class => ['description'], FarmActivity::class => ['description'], PlantingCycle::class => ['subblock_label'],
            PlantObservation::class => ['notes'], WeatherRecord::class => ['data_source'], ActivityPhoto::class => ['label', 'caption'],
        ] as $class => $fields) {
            foreach ($class::query()->get() as $record) {
                foreach ($fields as $field) {
                    $this->assertDoesNotMatchRegularExpression('/\bdemo\b/i', (string) $record->getAttribute($field));
                }
            }
        }
    }

    public function test_cleanup_leaves_other_tenants_and_custom_content_untouched(): void
    {
        $team = $this->fixtures();
        $team->forceFill(['name' => 'Kebun Pelanggan'])->saveQuietly();
        $foreignTeam = Team::factory()->create(['name' => 'Tenant Demo Eksternal', 'is_personal' => false]);
        $foreign = new Farm(['name' => 'Kebun Cileuksa Demo', 'total_area_ha' => '5.0000']);
        $foreign->forceFill([
            'team_id' => $foreignTeam->id,
            'created_by' => User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])->firstOrFail()->id,
        ])->save();
        $custom = Farm::query()->where('name', DemoFarmSeeder::FARM_NAME)->firstOrFail();
        $custom->update(['name' => 'Kebun Pelanggan', 'address' => 'Jalan Demo milik pelanggan']);
        $beforeForeign = $foreign->refresh()->getRawOriginal();
        $beforeCustom = $custom->refresh()->getRawOriginal();
        $this->seed(PresentationNamesSeeder::class);
        $this->assertSame($beforeForeign, $foreign->refresh()->getRawOriginal());
        $this->assertSame($beforeCustom, $custom->refresh()->getRawOriginal());
        $this->assertSame('Tenant Demo Eksternal', $foreignTeam->refresh()->name);
        $this->assertSame('Kebun Pelanggan', $team->refresh()->name);
    }

    public function test_cleanup_updates_recognized_legacy_brand_names_to_configuration(): void
    {
        $team = $this->fixtures();
        config(['app.name' => 'Kebun Nusantara']);

        foreach (['Hijauan', 'Bumi Hijauan'] as $legacyName) {
            $team->forceFill(['name' => $legacyName])->saveQuietly();
            $this->seed(PresentationNamesSeeder::class);
            $this->assertSame('Kebun Nusantara', $team->refresh()->name);
            $this->assertSame('farm-ic-demo', $team->slug);
        }
    }

    public function test_missing_trusted_actor_and_production_guard_reject_before_changes(): void
    {
        try {
            $this->seed(PresentationNamesSeeder::class);
            $this->fail('An existing trusted Superadmin is required.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Superadmin', $exception->getMessage());
        }
        $this->assertDatabaseCount('farms', 0);
        $this->app->instance('env', 'production');
        try {
            app(PresentationNamesSeeder::class)->run();
            $this->fail('Name refresh is restricted to local/testing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}

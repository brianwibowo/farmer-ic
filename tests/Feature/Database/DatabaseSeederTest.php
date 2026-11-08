<?php

namespace Tests\Feature\Database;

use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\Team;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoOwnerPortfolioData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** Full seed integration on the separate, guarded PostgreSQL test database. */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_seed_includes_owner_details_and_refreshes_names_without_duplicates_on_rerun(): void
    {
        $this->seed(DatabaseSeeder::class);

        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
        $this->assertDatabaseCount('farms', 6);
        $this->assertDatabaseCount('financial_transactions', 24);
        $this->assertSame(['Jagung', 'Rumput', 'Sorgum'], Commodity::query()->orderBy('name')->pluck('name')->all());
        foreach (DemoOwnerPortfolioData::sites() as $site) {
            $farmId = DemoOwnerPortfolioData::id($team, 'farm:'.$site['key']);
            $this->assertDatabaseHas('farms', ['id' => $farmId, 'name' => $site['name'], 'team_id' => $team->id]);
            $this->assertSame(2, DB::table('farm_blocks')->where('farm_id', $farmId)->count());
            $this->assertSame(count($site['batches']), PlantingCycle::query()->where('farm_id', $farmId)->count());
            $this->assertTrue(FarmActivity::query()->where('farm_id', $farmId)->exists());
            $this->assertDatabaseHas('weather_records', ['farm_id' => $farmId]);
            $this->assertDatabaseHas('farms', [
                'id' => $farmId, 'commodity_id' => Commodity::query()->where('name', $site['commodity'])->value('id'),
            ]);
            foreach (PlantingCycle::query()->where('farm_id', $farmId)->whereNotNull('variety_id')->with('variety.commodity')->get() as $cycle) {
                $this->assertSame($site['commodity'], $cycle->variety->commodity->name);
                $this->assertSame($site['variety'], $cycle->variety->name);
            }
        }

        $tables = ['users', 'teams', 'team_members', 'farms', 'farm_blocks', 'planting_rounds',
            'planting_cycles', 'planting_cycle_stages', 'planting_cycle_tasks', 'sop_templates',
            'sop_template_versions', 'sop_template_tasks', 'farm_activities', 'planting_details',
            'fertilization_details', 'irrigation_details', 'pesticide_details', 'harvest_details',
            'plant_observations', 'farm_risks', 'weather_records', 'activity_photos',
            'financial_transactions', 'financial_budgets', 'financial_projections'];
        $counts = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);
        $farm = Farm::query()->findOrFail(DemoOwnerPortfolioData::id($team, 'farm:sukamaju'));
        $farm->update(['name' => 'Kebun Sukamaju Demo']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Kebun Sukamaju', $farm->refresh()->name);
        $this->assertSame(['Jagung', 'Rumput', 'Sorgum'], Commodity::query()->orderBy('name')->pluck('name')->all());
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    public function test_complete_seed_rejects_production_before_writing_any_records(): void
    {
        $this->app->instance('env', 'production');
        try {
            app(DatabaseSeeder::class)->run();
            $this->fail('The full demo seed must reject production before creating identities.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('farms', 0);
        $this->assertDatabaseCount('administrative_regions', 0);
    }
}

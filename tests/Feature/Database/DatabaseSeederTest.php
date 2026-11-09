<?php

namespace Tests\Feature\Database;

use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoFarmSeeder;
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

        $tables = ['users', 'teams', 'team_members', 'farms', 'farm_blocks',
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

    public function test_seeded_activities_follow_the_planting_anchor_and_sop_schedule(): void
    {
        $this->seed(DatabaseSeeder::class);

        $farm = Farm::query()->where('name', DemoFarmSeeder::FARM_NAME)->firstOrFail();
        $cycle = PlantingCycle::query()->where('farm_id', $farm->id)->where('status', 'active')->firstOrFail();
        $previous = PlantingCycle::query()->where('farm_id', $farm->id)->where('status', 'harvested')->firstOrFail();

        $activityDate = fn (string $description): string => FarmActivity::query()
            ->where('farm_id', $farm->id)
            ->where('description', $description)
            ->firstOrFail()
            ->activity_date
            ->setTimezone('Asia/Jakarta')
            ->toDateString();

        $scheduled = fn (string $category, ?string $subcategory): string => $cycle->tasks()
            ->where('activity_category', $category)
            ->when(
                $subcategory === null,
                fn ($query) => $query->whereNull('activity_subcategory'),
                fn ($query) => $query->where('activity_subcategory', $subcategory),
            )
            ->firstOrFail()
            ->effectiveScheduledDate()
            ->toDateString();

        $planted = $cycle->planted_at->toDateString();

        $this->assertSame($planted, $activityDate('Penanaman Blok 1'));
        $this->assertSame($scheduled('maintenance', 'irrigation'), $activityDate('Irigasi sore Blok 1'));
        $this->assertSame($scheduled('maintenance', 'follow_up_fertilization'), $activityDate('Pemupukan susulan pertama Blok 1'));
        $this->assertSame($scheduled('maintenance', 'pest_control'), $activityDate('Pengendalian ulat Blok 1'));
        $this->assertDatabaseMissing('farm_activities', [
            'farm_id' => $farm->id,
            'description' => 'Panen percobaan Blok 2',
        ]);

        $linkedTask = function (string $description) use ($farm): PlantingCycleTask {
            return FarmActivity::query()
                ->where('farm_id', $farm->id)
                ->where('description', $description)
                ->firstOrFail()
                ->task()
                ->firstOrFail();
        };

        foreach ([
            'Irigasi sore Blok 1' => 'irrigation',
            'Pemupukan susulan pertama Blok 1' => 'follow_up_fertilization',
            'Pengendalian ulat Blok 1' => 'pest_control',
        ] as $description => $subcategory) {
            $task = $linkedTask($description);
            $this->assertSame('completed', $task->status);
            $this->assertSame($subcategory, $task->activity_subcategory);
            $this->assertSame(
                $activityDate($description),
                $task->completed_at->setTimezone('Asia/Jakarta')->toDateString(),
            );
        }

        $this->assertSame('pending', $cycle->tasks()->where('name', 'Pemupukan lanjutan')->firstOrFail()->status);
        $harvestTask = $cycle->tasks()->where('activity_category', 'harvest')->firstOrFail();
        $this->assertSame('pending', $harvestTask->status);
        $this->assertSame(0, FarmActivity::query()->where('planting_cycle_task_id', $harvestTask->id)->count());
        $this->assertSame(3, FarmActivity::query()->where('planting_cycle_id', $cycle->id)->whereNotNull('planting_cycle_task_id')->count());
        $this->assertSame(1, FarmActivity::query()->where('planting_cycle_id', $previous->id)->whereNotNull('planting_cycle_task_id')->count());
        $this->assertSame(4, FarmActivity::query()->where('farm_id', $farm->id)->whereNotNull('planting_cycle_task_id')->count());
        $this->assertSame(8, FarmActivity::query()->where('farm_id', $farm->id)->count());

        $this->assertSame('2026-06-01', $previous->planted_at->toDateString());
        $this->assertSame('2026-08-30', $previous->estimated_harvest_at->toDateString());
        $this->assertNotNull($previous->sop_template_version_id);
        $this->assertSame(6, $previous->stages()->count());
        $this->assertSame(6, $previous->stages()->where('status', 'completed')->count());
        $this->assertSame('2026-08-30', $activityDate('Panen Blok Utara putaran sebelumnya'));
        $previousHarvest = $linkedTask('Panen Blok Utara putaran sebelumnya');
        $this->assertSame('completed', $previousHarvest->status);
        $this->assertSame(
            '2026-08-30',
            $previousHarvest->completed_at->setTimezone('Asia/Jakarta')->toDateString(),
        );

        $planned = PlantingCycle::query()->where('farm_id', $farm->id)->where('status', 'planned')->firstOrFail();
        $this->assertSame('2026-11-20', $planned->planned_planting_at->toDateString());
        $this->assertSame(0, $planned->tasks()->count());

        $mapping = FarmActivity::query()
            ->where('farm_id', $farm->id)
            ->whereHas('activityType', fn ($query) => $query->where('code', 'block_mapping'))
            ->firstOrFail();
        $this->assertLessThan($planted, $mapping->activity_date->setTimezone('Asia/Jakarta')->toDateString());
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

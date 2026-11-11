<?php

namespace Tests\Feature\Database;

use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FinancialCategory;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Services\Owner\HarvestProjectionService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoOwnerPortfolioData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Full seed integration on the separate, guarded PostgreSQL test database. */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_seed_runs_in_production_environment(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
            $this->assertDatabaseHas('teams', ['slug' => 'farm-ic-demo']);
            $this->assertDatabaseCount('farms', 7);
            $this->assertDatabaseCount('farm_legalities', 7);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_complete_seed_includes_owner_details_and_refreshes_names_without_duplicates_on_rerun(): void
    {
        $this->seed(DatabaseSeeder::class);

        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
        $this->assertDatabaseCount('farms', 7);
        $this->assertDatabaseCount('financial_transactions', 24);
        $this->assertSame(['Jagung', 'Rumput', 'Sorgum'], Commodity::query()->orderBy('name')->pluck('name')->all());
        $demoFarm = Farm::query()->where('name', 'Kebun Cileuksa')->firstOrFail();
        $this->assertTrue(DB::table('block_mapping_details as d')->join('farm_activities as a', 'a.id', '=', 'd.activity_id')
            ->where('a.farm_id', $demoFarm->id)->exists());
        foreach (['B01', 'B02'] as $code) {
            $blockId = DB::table('farm_blocks')->where('farm_id', $demoFarm->id)->where('code', $code)->value('id');
            foreach (['land_cleaning', 'loosening'] as $type) {
                $detail = $type === 'land_cleaning' ? 'land_cleaning_details' : 'loosening_details';
                $this->assertTrue(DB::table('farm_activities as a')->join('activity_types as t', 't.id', '=', 'a.activity_type_id')
                    ->join($detail.' as d', 'd.activity_id', '=', 'a.id')
                    ->where('a.farm_id', $demoFarm->id)->where('a.block_id', $blockId)->whereNull('a.planting_cycle_id')->where('t.code', $type)->exists());
            }
        }
        $this->assertGreaterThan(0, DB::table('farm_activities')->where('farm_id', $demoFarm->id)->whereNotNull('planting_cycle_id')->whereNotNull('block_id')->count());
        $this->assertSame(0, DB::table('farm_activities as a')->join('planting_cycles as c', 'c.id', '=', 'a.planting_cycle_id')
            ->whereNotNull('c.block_id')
            ->where(fn ($query) => $query->whereNull('a.block_id')->orWhereColumn('a.block_id', '<>', 'c.block_id'))->count());
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
            'financial_transactions', 'financial_budgets', 'financial_projections', 'land_cleaning_details', 'loosening_details'];
        $counts = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);
        $farm = Farm::query()->findOrFail(DemoOwnerPortfolioData::id($team, 'farm:sukamaju'));
        $farm->update(['name' => 'Kebun Cariu Demo']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Kebun Cariu', $farm->refresh()->name);
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
        $this->assertSame(12, FarmActivity::query()->where('farm_id', $farm->id)->count());

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

    public function test_owner_farm_prune_previews_then_deletes_only_non_approved_demo_farms_and_their_finance(): void
    {
        $this->seed(DatabaseSeeder::class);
        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
        $superadmin = User::query()->where('email', 'superadmin@farmer-ic.test')->firstOrFail();
        $extra = new Farm(['name' => 'Kebun Tambahan Bogor', 'total_area_ha' => '3.0000', 'land_tenure' => 'owned']);
        $extra->forceFill([
            'team_id' => $team->id, 'created_by' => $superadmin->id,
            'city' => 'Kabupaten Bogor', 'district' => 'Cibinong', 'province' => 'Jawa Barat',
        ])->save();
        $category = FinancialCategory::query()->where('transaction_type', 'expense')->firstOrFail();
        DB::table('financial_transactions')->insert([
            'id' => (string) Str::uuid(), 'farm_id' => $extra->id, 'category_id' => $category->id,
            'recorded_by' => $superadmin->id, 'transaction_date' => '2026-11-05',
            'transaction_type' => 'expense', 'amount' => '100.00', 'created_at' => now(),
        ]);

        $this->artisan('demo:prune-owner-farms')->assertExitCode(0);
        $this->assertDatabaseHas('farms', ['id' => $extra->id]);
        $this->assertDatabaseHas('financial_transactions', ['farm_id' => $extra->id]);

        $this->artisan('demo:prune-owner-farms --apply --force')->assertExitCode(0);

        $this->assertDatabaseMissing('farms', ['id' => $extra->id]);
        $this->assertDatabaseMissing('financial_transactions', ['farm_id' => $extra->id]);
        $this->assertSame(7, Farm::query()->where('team_id', $team->id)->count());
        foreach (DemoOwnerPortfolioData::sites() as $site) {
            $this->assertDatabaseHas('farms', ['id' => DemoOwnerPortfolioData::id($team, 'farm:'.$site['key'])]);
        }
        $this->assertDatabaseHas('farms', ['name' => 'Kebun Cileuksa', 'team_id' => $team->id]);
        $owner = User::query()->where('email', 'owner@farmer-ic.test')->firstOrFail();
        $projection = app(HarvestProjectionService::class)->summary($owner, $team);
        $this->assertSame(7, $projection['farm_count']);
        $this->assertEqualsCanonicalizing([
            'Kebun Cariu', 'Kebun Cileuksa', 'Kebun Cipanengah', 'Kebun Jonggol',
            'Kebun Megamendung', 'Kebun Pamijahan', 'Kebun Sirnasari',
        ], array_column($projection['rows'], 'farm_name'));

        $this->actingAs($owner)
            ->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.total', 7)
            ->where('landTable.rows', fn ($rows) => collect($rows)->count() === 7
                && collect($rows)->pluck('name')->sort()->values()->all() === [
                    'Kebun Cariu', 'Kebun Cileuksa', 'Kebun Cipanengah', 'Kebun Jonggol',
                    'Kebun Megamendung', 'Kebun Pamijahan', 'Kebun Sirnasari',
                ])
            ->where('landTable.groups', fn ($groups) => collect($groups)->count() === 2
                && collect($groups)->pluck('name')->all() === ['Kabupaten Bogor', 'Kabupaten Sukabumi']));
    }
}

<?php

namespace Tests\Feature\Database;

use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FinancialCategory;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\Owner\HarvestProjectionService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoOwnerPortfolioData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
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
        $this->assertDatabaseCount('farms', 7);
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
        $farm->update(['name' => 'Kebun Cariu Demo']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Kebun Cariu', $farm->refresh()->name);
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

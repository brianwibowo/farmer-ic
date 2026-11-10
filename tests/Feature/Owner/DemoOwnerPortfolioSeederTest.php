<?php

namespace Tests\Feature\Owner;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WeatherRecord;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Database\Seeders\DemoMonitoringSeeder;
use Database\Seeders\DemoOwnerPortfolioData;
use Database\Seeders\DemoOwnerPortfolioSeeder;
use Database\Seeders\DemoSopSeeder;
use Database\Seeders\SopActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/** Persistence and HTTP integration on the separate, guarded PostgreSQL test database. */
class DemoOwnerPortfolioSeederTest extends TestCase
{
    use RefreshDatabase;

    private function prerequisites(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(12, 0));
        $this->seed([
            DemoIdentitySeeder::class,
            DemoFarmSeeder::class,
            DemoActivitySeeder::class,
            SopActivityTypeSeeder::class,
            DemoSopSeeder::class,
            DemoMonitoringSeeder::class,
        ]);
    }

    private function owner(): User
    {
        return User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['owner']['email'])->firstOrFail();
    }

    private function demoTeam(): Team
    {
        return Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
    }

    /** @return array<string, list<string>> */
    private function recordIds(): array
    {
        $snapshot = [];
        foreach (['farms', 'farm_blocks', 'planting_rounds', 'planting_cycles', 'planting_cycle_stages',
            'sop_templates', 'sop_template_versions', 'sop_template_tasks', 'planting_cycle_tasks',
            'farm_activities', 'planting_details', 'fertilization_details', 'irrigation_details',
            'pesticide_details', 'harvest_details', 'plant_observations', 'farm_risks',
            'weather_records', 'activity_photos', 'commodities', 'crop_varieties', 'activity_types'] as $table) {
            $key = str_ends_with($table, '_details') ? 'activity_id' : 'id';
            $snapshot[$table] = DB::table($table)->orderBy($key)->pluck($key)->all();
        }

        return $snapshot;
    }

    public function test_portfolio_populates_owner_table_complete_pie_and_clickable_details(): void
    {
        $this->prerequisites();
        $this->seed(DemoOwnerPortfolioSeeder::class);
        $team = $this->demoTeam();
        $this->actingAs($this->owner());

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Dashboard')->where('landTable.total', 6)
            ->where('landTable.total_area_ha', '44.5000')->has('landTable.rows', 6)
            ->has('dashboard.landMap.farms', 6)->has('dashboard.commodities', 3)
            ->where('dashboard.commodities', fn ($rows) => collect($rows)->pluck('areaValue', 'name')->sortKeys()->all() == [
                'Jagung' => 9.75, 'Rumput' => 6.25, 'Sorgum' => 3,
            ]));

        foreach (DemoOwnerPortfolioData::sites() as $site) {
            $farm = Farm::query()->findOrFail(DemoOwnerPortfolioData::id($team, 'farm:'.$site['key']));
            $this->get('/kebun/'.$farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('owner/Farm')->where('farm.id', $farm->id)
                ->where('farm.default_sop.is_active', true)->has('blocks', 2)
                ->has('legacyCycles', 0)->where('weather', fn ($weather) => $weather !== null));

            $cycle = PlantingCycle::query()->where('farm_id', $farm->id)->with('plantingRound.block')->firstOrFail();
            $block = $cycle->plantingRound->block;
            $this->get('/kebun/'.$farm->id.'/blok/'.$block->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('owner/Block')->where('block.id', $block->id)
                ->where('cycles', fn ($rows) => collect($rows)->isNotEmpty() && collect($rows)->every(
                    fn ($batch) => count($batch['stages']) === 6 && count($batch['tasks']) > 0 && $batch['sop'] !== null,
                )));

            $activity = FarmActivity::query()->where('farm_id', $farm->id)->orderByDesc('activity_date')->firstOrFail();
            $this->get('/aktivitas/'.$activity->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('owner/Activity')->where('activity.id', $activity->id)
                ->where('activity.farm.id', $farm->id)
                ->where('activity.details', fn ($details) => collect($details)->isNotEmpty()));
        }

        $farmIds = collect(DemoOwnerPortfolioData::sites())
            ->map(fn (array $site) => DemoOwnerPortfolioData::id($team, 'farm:'.$site['key']));
        $batches = PlantingCycle::query()->whereIn('farm_id', $farmIds)->withCount(['stages', 'tasks'])->get();
        $this->assertCount(8, $batches);
        foreach ($batches as $batch) {
            $this->assertSame(6, $batch->stages_count);
            $this->assertGreaterThan(0, $batch->tasks_count);
            $this->assertNotNull($batch->sop_template_version_id);
        }
        $detailTypes = [
            'planting' => 'plantingDetail', 'fertilization' => 'fertilizationDetail',
            'irrigation' => 'irrigationDetail', 'pesticide' => 'pesticideDetail',
            'harvest' => 'harvestDetail', 'growth_check' => 'observation',
        ];
        $activities = FarmActivity::query()->whereIn('farm_id', $farmIds)
            ->with(['activityType', ...array_values($detailTypes)])->get();
        $this->assertNotEmpty($activities);
        foreach ($activities as $activity) {
            $relation = $detailTypes[$activity->activityType->code];
            $this->assertNotNull($activity->getRelation($relation));
            $this->assertSame(1, collect(array_values($detailTypes))
                ->filter(fn (string $detail) => $activity->getRelation($detail) !== null)->count());
        }
    }

    public function test_rerun_preserves_ids_manual_edits_original_farm_and_all_demo_accounts(): void
    {
        $this->prerequisites();
        $original = Farm::query()->where('name', 'Kebun Cileuksa')->firstOrFail();
        $originalAttributes = $original->getRawOriginal();
        $accounts = User::query()->orderBy('id')->get()->map(fn (User $user) => $user->getRawOriginal())->all();
        $this->seed(DemoOwnerPortfolioSeeder::class);
        $team = $this->demoTeam();
        $farm = Farm::query()->findOrFail(DemoOwnerPortfolioData::id($team, 'farm:sukamaju'));
        $farm->update(['name' => 'Kebun Sukamaju Disesuaikan', 'total_area_ha' => '8.1250']);
        $activity = FarmActivity::query()->where('farm_id', $farm->id)->firstOrFail();
        $activity->update(['description' => 'Catatan hasil kunjungan yang disesuaikan pengguna']);
        $ids = $this->recordIds();

        $this->seed(DemoOwnerPortfolioSeeder::class);

        $this->assertSame($ids, $this->recordIds());
        $this->assertSame('Kebun Sukamaju Disesuaikan', $farm->refresh()->name);
        $this->assertSame('8.1250', $farm->total_area_ha);
        $this->assertSame('Catatan hasil kunjungan yang disesuaikan pengguna', $activity->refresh()->description);
        $this->assertSame($originalAttributes, $original->refresh()->getRawOriginal());
        $this->assertSame($accounts, User::query()->orderBy('id')->get()->map(fn (User $user) => $user->getRawOriginal())->all());
    }

    public function test_same_named_foreign_farm_is_untouched_and_owner_dashboard_stays_tenant_scoped(): void
    {
        $this->prerequisites();
        $foreignTeam = Team::factory()->create(['name' => 'Tenant Pembanding', 'is_personal' => false]);
        $foreign = new Farm(['name' => 'Kebun Sukamaju', 'total_area_ha' => '999.0000', 'land_tenure' => 'rented']);
        $foreign->forceFill([
            'team_id' => $foreignTeam->id,
            'created_by' => User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])->firstOrFail()->id,
        ])->save();
        $before = $foreign->refresh()->getRawOriginal();

        $this->seed(DemoOwnerPortfolioSeeder::class);
        $this->assertSame($before, $foreign->refresh()->getRawOriginal());
        $farmId = DemoOwnerPortfolioData::id($this->demoTeam(), 'farm:sukamaju');
        $this->actingAs($this->owner())->get('/?farm_id='.$farmId)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.total', 6)->where('landTable.total_area_ha', '44.5000')
            ->has('landTable.rows', 6)->has('dashboard.landMap.farms', 6)
            ->has('dashboard.commodities', 3)
            ->where('landTable.rows', fn ($rows) => ! collect($rows)->pluck('id')->contains($foreign->id))
            ->where('dashboard.landMap.farms', fn ($rows) => ! collect($rows)->pluck('id')->contains($foreign->id)));
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.total', 6)->where('landTable.total_area_ha', '44.5000'));
        $this->get('/kebun/'.$foreign->id)->assertForbidden();
    }

    public function test_missing_identity_and_production_guards_fail_before_writes(): void
    {
        try {
            $this->seed(DemoOwnerPortfolioSeeder::class);
            $this->fail('Portfolio seeding must require existing trusted demo identities.');
        } catch (RuntimeException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        $this->assertDatabaseCount('farms', 0);
        $this->assertDatabaseCount('commodities', 0);

        $this->app->instance('env', 'production');
        try {
            app(DemoOwnerPortfolioSeeder::class)->run();
            $this->fail('Portfolio seeding must not run in production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('farms', 0);
    }

    public function test_tampered_demo_actor_role_is_rejected_before_portfolio_writes(): void
    {
        $this->prerequisites();
        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])->firstOrFail();
        $actor->forceFill(['role_id' => Role::query()->where('name', 'admin')->firstOrFail()->id])->save();
        $before = $this->recordIds();
        try {
            $this->seed(DemoOwnerPortfolioSeeder::class);
            $this->fail('The fixture actor must retain its trusted demo Superadmin role.');
        } catch (RuntimeException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        $this->assertSame($before, $this->recordIds());
    }

    public function test_a_failure_rolls_back_the_entire_portfolio_scenario(): void
    {
        $this->prerequisites();
        $before = $this->recordIds();
        $event = 'eloquent.creating: '.WeatherRecord::class;
        Event::listen($event, function (): void {
            throw new RuntimeException('Simulated portfolio detail failure.');
        });
        try {
            $this->seed(DemoOwnerPortfolioSeeder::class);
            $this->fail('Expected the simulated detail persistence failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated portfolio detail failure.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame($before, $this->recordIds());
    }
}

<?php

namespace Tests\Feature\Owner;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WeatherRecord;
use App\Services\Owner\HarvestProjectionService;
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
            SopActivityTypeSeeder::class,
            DemoSopSeeder::class,
            DemoActivitySeeder::class,
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
        foreach (['farms', 'farm_blocks', 'planting_cycles', 'planting_cycle_stages',
            'sop_templates', 'sop_template_versions', 'sop_template_tasks', 'planting_cycle_tasks',
            'farm_activities', 'planting_details', 'fertilization_details', 'irrigation_details',
            'pesticide_details', 'harvest_details', 'plant_observations', 'farm_risks',
            'weather_records', 'activity_photos', 'commodities', 'crop_varieties', 'activity_types'] as $table) {
            $key = str_ends_with($table, '_details') ? 'activity_id' : 'id';
            $snapshot[$table] = DB::table($table)->orderBy($key)->pluck($key)->all();
        }

        return $snapshot;
    }

    public function test_portfolio_populates_owner_table_with_requested_sites_and_clickable_details(): void
    {
        $this->prerequisites();
        $this->seed(DemoOwnerPortfolioSeeder::class);
        $team = $this->demoTeam();
        $expectedSites = [
            'Kebun Cileuksa' => ['Kabupaten Bogor', 'Cileuksa', '100.0000'],
            'Kebun Cariu' => ['Kabupaten Bogor', 'Cariu', '50.0000'],
            'Kebun Jonggol' => ['Kabupaten Bogor', 'Jonggol', '110.0000'],
            'Kebun Pamijahan' => ['Kabupaten Bogor', 'Pamijahan', '25.0000'],
            'Kebun Sirnasari' => ['Kabupaten Bogor', 'Sirnasari', '187.0000'],
            'Kebun Megamendung' => ['Kabupaten Bogor', 'Megamendung', '25.0000'],
            'Kebun Cipanengah' => ['Kabupaten Sukabumi', 'Cipanengah', '100.0000'],
        ];
        foreach ($expectedSites as $name => [$city, $district, $area]) {
            $this->assertDatabaseHas('farms', [
                'name' => $name, 'city' => $city, 'district' => $district,
                'province' => 'Jawa Barat', 'total_area_ha' => $area,
            ]);
        }
        $this->assertSame(7, Farm::query()->where('team_id', $team->id)->count());
        foreach (['Kebun Sukamaju', 'Kebun Cibogo', 'Kebun Mekarsari', 'Kebun Cikeas', 'Kebun Cisarua'] as $oldName) {
            $this->assertDatabaseMissing('farms', ['team_id' => $team->id, 'name' => $oldName]);
        }
        $this->actingAs($this->owner());

        $megamendungId = DemoOwnerPortfolioData::id($team, 'farm:cisarua');
        $activeMegamendungBatch = PlantingCycle::query()->where('farm_id', $megamendungId)
            ->where('status', 'active')->where('subblock_label', 'A2')->firstOrFail();
        $this->assertSame('active', $activeMegamendungBatch->status);
        $projection = app(HarvestProjectionService::class)->summary($this->owner(), $team);
        $this->assertEquals(2500, collect($projection['rows'])->firstWhere('farm_name', 'Kebun Megamendung')['projection_ton']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Dashboard')->where('landTable.total', 7)
            ->where('landTable.total_area_ha', '597.0000')->has('landTable.rows', 7)
            ->has('dashboard.landMap.farms', 7)->has('dashboard.commodities', 3)
            ->where('dashboard.commodities', fn ($rows) => collect($rows)->pluck('areaValue', 'name')->sortKeys()->all() == [
                'Jagung' => 18.0, 'Rumput' => 7.0, 'Sorgum' => 3.0,
            ])
            ->has('landTable.groups', 2)
            ->where('landTable.groups.0.name', 'Kabupaten Bogor')
            ->where('landTable.groups.0.farm_count', 6)
            ->where('landTable.groups.0.area_ha', '497.0000')
            ->where('landTable.groups.1.name', 'Kabupaten Sukabumi')
            ->where('landTable.groups.1.farm_count', 1)
            ->where('landTable.groups.1.area_ha', '100.0000'));

        foreach (DemoOwnerPortfolioData::sites() as $site) {
            $farm = Farm::query()->findOrFail(DemoOwnerPortfolioData::id($team, 'farm:'.$site['key']));
            $this->get('/kebun/'.$farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('farms/Show')->where('farm.id', $farm->id)
                ->where('farm.default_sop.is_active', true)->has('blocks', 2)
                ->where('can.update', false)->where('can.createBlock', false));

            $cycle = PlantingCycle::query()->where('farm_id', $farm->id)->with('block')->firstOrFail();
            $block = $cycle->block;
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
        $this->assertCount(10, $batches);
        foreach ($batches as $batch) {
            $this->assertSame(6, $batch->stages_count);
            $this->assertGreaterThan(0, $batch->tasks_count);
            $this->assertNotNull($batch->sop_template_version_id);
        }
        $detailTypes = [
            'planting' => 'plantingDetail', 'follow_up_fertilization' => 'fertilizationDetail',
            'irrigation' => 'irrigationDetail', 'pest_control' => 'pesticideDetail',
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

    public function test_rerun_preserves_operational_history_and_syncs_requested_site_data(): void
    {
        $this->prerequisites();
        $original = Farm::query()->where('name', 'Kebun Cileuksa')->firstOrFail();
        $originalAttributes = $original->getRawOriginal();
        $accounts = User::query()->orderBy('id')->get()->map(fn (User $user) => $user->getRawOriginal())->all();
        $this->seed(DemoOwnerPortfolioSeeder::class);
        $team = $this->demoTeam();
        $farm = Farm::query()->findOrFail(DemoOwnerPortfolioData::id($team, 'farm:sukamaju'));
        $farm->update(['name' => 'Kebun Cariu Disesuaikan', 'total_area_ha' => '8.1250']);
        $activity = FarmActivity::query()->where('farm_id', $farm->id)->firstOrFail();
        $activity->update(['description' => 'Catatan hasil kunjungan yang disesuaikan pengguna']);
        $ids = $this->recordIds();

        $this->seed(DemoOwnerPortfolioSeeder::class);

        $this->assertSame($ids, $this->recordIds());
        $this->assertSame('Kebun Cariu', $farm->refresh()->name);
        $this->assertSame('50.0000', $farm->total_area_ha);
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
            ->where('landTable.total', 7)->where('landTable.total_area_ha', '597.0000')
            ->has('landTable.rows', 7)->has('dashboard.landMap.farms', 7)
            ->has('dashboard.commodities', 3)
            ->where('landTable.rows', fn ($rows) => ! collect($rows)->pluck('id')->contains($foreign->id))
            ->where('dashboard.landMap.farms', fn ($rows) => ! collect($rows)->pluck('id')->contains($foreign->id)));
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.total', 7)->where('landTable.total_area_ha', '597.0000'));
        $this->get('/kebun/'.$foreign->id)->assertForbidden();
    }

    public function test_missing_identity_guard_fails_before_writes(): void
    {
        try {
            $this->seed(DemoOwnerPortfolioSeeder::class);
            $this->fail('Portfolio seeding must require existing trusted demo identities.');
        } catch (RuntimeException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        $this->assertDatabaseCount('farms', 0);
        $this->assertDatabaseCount('commodities', 0);
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

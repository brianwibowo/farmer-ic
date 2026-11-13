<?php

namespace Tests\Feature\Database;

use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Support\ActivityDetailMap;
use Database\Seeders\CariuBaruLogbook;
use Database\Seeders\CariuBaruSeeder;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Database\Seeders\SopActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/** Integrated PostgreSQL fixture, authorization and rollback tests. */
class CariuBaruSeederTest extends TestCase
{
    use RefreshDatabase;

    private function prerequisites(): void
    {
        $this->seed([DemoIdentitySeeder::class, SopActivityTypeSeeder::class, DemoFarmSeeder::class]);
    }

    public function test_import_preserves_every_source_row_and_builds_a_labeled_complete_scenario(): void
    {
        $this->prerequisites();
        $oldPetugas = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
        $oldFarm = $oldPetugas->assignedFarm;
        $oldAttributes = $oldFarm->getRawOriginal();
        $this->seed(CariuBaruSeeder::class);

        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
        $farm = Farm::query()->findOrFail(CariuBaruSeeder::farmId($team));
        $petugas = User::query()->where('email', CariuBaruSeeder::EMAIL)->firstOrFail();
        $this->assertSame('Kebun Cariu Baru', $farm->name);
        $this->assertSame('1.3900', $farm->total_area_ha);
        $this->assertSame($oldAttributes, $oldFarm->refresh()->getRawOriginal());
        $this->assertSame($oldFarm->id, $oldPetugas->refresh()->assignedFarmId());
        $this->assertSame($farm->id, $petugas->assignedFarmId());
        $this->assertCount(1, $petugas->teams()->where('is_personal', false)->get());

        $reports = FarmActivity::query()->where('farm_id', $farm->id)->with('activityType')->get();
        $this->assertCount(70, $reports); // 42 source rows + 4 derived types + 24 simulated tasks.
        foreach (CariuBaruLogbook::rows() as $row) {
            $matches = $reports->filter(fn ($a) => str_starts_with($a->description ?? '', 'SUMBER SPREADSHEET '.$row['source'].' —'));
            $this->assertCount(1, $matches, $row['source']);
            $activity = $matches->first();
            $this->assertStringContainsString(CariuBaruLogbook::notes($row), $activity->description);
            $this->assertSame($row['workers'], $activity->worker_count);
            $this->assertSame($row['date'], $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString());
            $this->assertSame($row['start'], $activity->activity_date->setTimezone('Asia/Jakarta')->format('H:i'));
            $this->assertSame($row['action'] === '' ? null : $row['action'], $activity->action_taken);
        }
        $derived = $reports->filter(fn ($a) => str_starts_with($a->description ?? '', 'TURUNAN TIPE'));
        $this->assertCount(4, $derived);
        $this->assertTrue($derived->every(fn ($a) => $a->worker_count === null && $a->obstacle_status === 'not_recorded'));
        foreach ($reports as $activity) {
            $table = ActivityDetailMap::table($activity->activityType->detail_key);
            $this->assertNotNull($table);
            $this->assertSame(1, DB::table($table)->where('activity_id', $activity->id)->count());
            $this->assertSame($petugas->id, $activity->performed_by);
        }

        $day20 = DailyActivityLog::query()->where('farm_id', $farm->id)->where('work_date', '2026-10-20')->firstOrFail();
        $this->assertSame('08:00', substr($day20->start_time, 0, 5));
        $this->assertSame('23:30', substr($day20->end_time, 0, 5));
        $this->assertStringContainsString('17.:15', $reports->first(fn ($a) => str_starts_with($a->description ?? '', 'SUMBER SPREADSHEET oktober:7 '))->description);
        $this->assertStringContainsString('187 hektare', $reports->first(fn ($a) => str_starts_with($a->description ?? '', 'SUMBER SPREADSHEET november:4 '))->description);
        $unknown = $reports->first(fn ($a) => str_starts_with($a->description ?? '', 'SUMBER SPREADSHEET november:8 '));
        $this->assertStringContainsString('luas 7 November pada sumber kosong', $unknown->description);
        $this->assertStringContainsString('seluas ... m²', $unknown->description);
        $this->assertNull($unknown->plantingDetail->quantity_kg);

        $cycles = PlantingCycle::query()->where('farm_id', $farm->id)->orderBy('batch_number')->get();
        $this->assertCount(4, $cycles);
        foreach ($cycles as $cycle) {
            $this->assertStringStartsWith('SIMULASI-', $cycle->subblock_label);
            $this->assertSame('harvested', $cycle->status);
            $dates = $reports->filter(fn ($a) => $a->planting_cycle_id === $cycle->id && $a->activityType->code === 'planting')
                ->map(fn ($a) => $a->activity_date->setTimezone('Asia/Jakarta')->toDateString())->sort()->values();
            $this->assertLessThanOrEqual(2, \Carbon\CarbonImmutable::parse($dates->first())->diffInDays($dates->last()));
            $tasks = PlantingCycleTask::query()->where('planting_cycle_id', $cycle->id)->get();
            $this->assertCount(6, $tasks);
            foreach ($tasks as $task) {
                $this->assertSame('completed', $task->status);
                $this->assertStringContainsString('SIMULASI', $task->name);
                $this->assertStringContainsString('SIMULASI', $task->completion_note);
                $this->assertSame($task->scheduled_date->toDateString(), $task->completed_at->setTimezone('Asia/Jakarta')->toDateString());
                $evidence = $reports->firstWhere('planting_cycle_task_id', $task->id);
                $this->assertNotNull($evidence);
                $this->assertStringContainsString('SIMULASI CARIU BARU', $evidence->description);
            }
        }
        $this->assertSame('1.1026', (string) PlantingCycle::query()->where('farm_id', $farm->id)->selectRaw('SUM(planted_area_ha)::numeric(12,4) AS total')->value('total'));
        $this->assertTrue(Gate::forUser($petugas)->allows('view', $farm));
        $this->assertFalse(Gate::forUser($oldPetugas)->allows('view', $farm));
        $this->assertFalse(Gate::forUser($petugas)->allows('view', $oldFarm));
        $harvestTask = PlantingCycleTask::query()->whereIn('planting_cycle_id', $cycles->modelKeys())
            ->where('activity_category', 'harvest')->firstOrFail();
        $this->actingAs($petugas)->get('/petugas/tasks?status=completed')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/Aktivitas')->has('tasks', 24)->where('summary.completed', 24)
            ->where('tasks', fn ($tasks) => collect($tasks)->every(fn ($task) => str_contains($task['name'], 'SIMULASI'))));
        $this->get('/petugas/tasks/'.$harvestTask->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/TaskShow')->where('task.id', $harvestTask->id)->has('reports', 1)
            ->where('reports.0.notes', fn ($notes) => str_contains($notes, 'SIMULASI')));
        $this->get('/petugas/laporan?mine=1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/Laporan')->where('reports.total', 70));
        $this->get('/petugas/laporan/'.$unknown->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/ReportShow')->where('log.activities.0.notes', fn ($notes) => str_contains($notes, 'luas 7 November pada sumber kosong')));
        $this->get('/kebun/'.$oldFarm->id)->assertForbidden();
        $this->actingAs($oldPetugas)->get('/petugas/tasks/'.$harvestTask->id)->assertForbidden();
        $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
        $this->actingAs($admin)->get('/admin/logbook/'.$farm->id.'/'.$day20->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('logbook/Show')->has('log.activities', 3)
            ->where('log.activities.2.notes', fn ($notes) => str_contains($notes, '18.00–23.30')));

        $count = $reports->count();
        $edited = $reports->first();
        $edited->update(['description' => 'Catatan yang diedit pengguna']);
        $this->seed(CariuBaruSeeder::class);
        $this->assertSame('Catatan yang diedit pengguna', $edited->refresh()->description);
        $this->assertSame($count, FarmActivity::query()->where('farm_id', $farm->id)->count());
        $this->assertSame($oldAttributes, $oldFarm->refresh()->getRawOriginal());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_a_failure_rolls_back_all_new_records(): void
    {
        $this->prerequisites();
        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
        $event = 'eloquent.creating: '.FarmActivity::class;
        Event::listen($event, fn () => throw new RuntimeException('Kegagalan impor simulasi'));
        try {
            $this->seed(CariuBaruSeeder::class);
            $this->fail('The import must roll back on failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Kegagalan impor simulasi', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseMissing('farms', ['id' => CariuBaruSeeder::farmId($team)]);
        $this->assertDatabaseMissing('users', ['email' => CariuBaruSeeder::EMAIL]);
        $this->assertDatabaseMissing('sop_templates', ['name' => 'SIMULASI Cariu Baru — SOP Jagung']);
    }

    public function test_existing_name_or_email_is_not_overwritten(): void
    {
        $this->prerequisites();
        $existing = User::factory()->create(['email' => CariuBaruSeeder::EMAIL]);
        $before = $existing->refresh()->getRawOriginal();
        try {
            app(CariuBaruSeeder::class)->run();
            $this->fail('A colliding account must stop the import.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('sudah digunakan', $exception->getMessage());
        }
        $this->assertSame($before, $existing->refresh()->getRawOriginal());
        $this->assertDatabaseMissing('farms', ['name' => 'Kebun Cariu Baru']);
    }

    public function test_environment_guard_does_not_depend_on_database_name_or_host(): void
    {
        $originalEnvironment = $this->app->environment();
        $originalConnection = DB::getDefaultConnection();
        config([
            'database.connections.cariu_guard_pgsql' => [
                'driver' => 'pgsql', 'host' => 'postgres-demo', 'database' => 'another_demo',
                'username' => 'demo', 'password' => '', 'prefix' => '',
            ],
            'database.connections.cariu_guard_other' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);

        try {
            // The guard reads the driver configuration only; neither fake DB is opened.
            DB::setDefaultConnection('cariu_guard_pgsql');
            foreach (['local', 'testing'] as $environment) {
                $this->app->instance('env', $environment);
                $this->assertTrue(CariuBaruSeeder::supportsCurrentDatabase());
            }
            foreach (['production', 'staging'] as $environment) {
                $this->app->instance('env', $environment);
                $this->assertFalse(CariuBaruSeeder::supportsCurrentDatabase());
            }
            $this->app->instance('env', 'local');
            DB::setDefaultConnection('cariu_guard_other');
            $this->assertFalse(CariuBaruSeeder::supportsCurrentDatabase());
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge('cariu_guard_pgsql');
            DB::purge('cariu_guard_other');
            $this->app->instance('env', $originalEnvironment);
        }
    }

    public function test_production_environment_is_rejected_before_writes(): void
    {
        $this->app->instance('env', 'production');
        try {
            app(CariuBaruSeeder::class)->run();
            $this->fail('This import must not run in production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('hanya diizinkan', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseMissing('farms', ['name' => 'Kebun Cariu Baru']);
    }
}

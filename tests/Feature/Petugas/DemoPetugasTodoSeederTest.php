<?php

namespace Tests\Feature\Petugas;

use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Database\Seeders\DemoPetugasTodoSeeder;
use Database\Seeders\DemoSopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** Seeder integration on the guarded, dedicated PostgreSQL test database. */
class DemoPetugasTodoSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function farmNames(): array
    {
        return [
            'current name' => [DemoFarmSeeder::FARM_NAME],
            'legacy name' => ['Kebun Cileuksa Demo'],
        ];
    }

    #[DataProvider('farmNames')]
    public function test_adds_today_preview_once_without_overwriting_existing_schedules(string $farmName): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05T09:00:00+07:00'));
        $this->seed([DemoIdentitySeeder::class, DemoFarmSeeder::class, DemoSopSeeder::class]);
        $farm = Farm::where('name', DemoFarmSeeder::FARM_NAME)->firstOrFail();
        $farm->forceFill(['name' => $farmName])->save();
        $defaultSop = $farm->default_sop_template_id;
        $cycles = PlantingCycle::count();
        $originals = PlantingCycleTask::orderBy('id')->get()->keyBy('id');
        $this->seed(DemoPetugasTodoSeeder::class);
        $this->seed(DemoPetugasTodoSeeder::class);
        $this->assertSame($cycles + 1, PlantingCycle::count());
        $this->assertSame($originals->count() + 4, PlantingCycleTask::count());
        $this->assertSame($defaultSop, $farm->refresh()->default_sop_template_id);
        foreach ($originals as $id => $task) {
            $persisted = PlantingCycleTask::findOrFail($id);
            $this->assertSame($task->scheduled_date?->toDateString(), $persisted->scheduled_date?->toDateString());
            $this->assertSame($task->status, $persisted->status);
        }
        $actor = User::where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
        $this->actingAs($actor)->get('/petugas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/Home')->where('home.tasks.total', 4)->where('home.task_summary.completed', 1)
            ->has('home.tasks.data', 4)->where('home.tasks.data.0.name', 'Pengairan pagi')
            ->where('home.tasks.data.0.status', 'in_progress')->where('home.tasks.data.0.hst', 14)
            ->where('home.tasks.data.0.scheduled_date', '2026-11-05')->where('home.tasks.data.3.status', 'completed'));
    }

    public function test_preview_cannot_be_seeded_in_production(): void
    {
        app()->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Contoh tugas petugas hanya boleh diisi');
        app(DemoPetugasTodoSeeder::class)->run();
    }
}

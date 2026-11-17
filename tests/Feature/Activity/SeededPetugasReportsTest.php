<?php

namespace Tests\Feature\Activity;

use App\Models\FarmActivity;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Full default seed on the isolated PostgreSQL schema, followed by real HTTP reads. */
class SeededPetugasReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_default_seeded_activity_is_readable_by_a_petugas_assigned_to_its_farm(): void
    {
        $this->seed(DatabaseSeeder::class);
        $petugas = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
        $activities = FarmActivity::query()->with(['farm', 'dailyActivityLog'])->orderBy('id')->get();
        $this->assertNotEmpty($activities);
        $this->assertSame(0, $activities->whereNull('daily_activity_log_id')->count(), 'Fresh seed produced activities without daily headers.');
        $farmGroups = $activities->groupBy('farm_id');
        $this->assertGreaterThan(1, $farmGroups->count());
        $tested = 0;

        // First exercise the actual Petugas accounts and assignments from the fresh seed.
        $assignedUsers = User::query()->where('is_active', true)->whereNotNull('assigned_farm_id')
            ->whereHas('role', fn ($query) => $query->where('name', 'petugas'))->get();
        $this->assertNotEmpty($assignedUsers);
        $actualAssignmentChecks = 0;
        foreach ($assignedUsers as $assignedUser) {
            foreach ($activities->where('farm_id', $assignedUser->assigned_farm_id) as $activity) {
                $this->actingAs($assignedUser)->get('/petugas/laporan/'.$activity->id)
                    ->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->component('petugas/ReportShow')->where('log.activities.0.id', $activity->id));
                $actualAssignmentChecks++;
            }
        }

        foreach ($farmGroups as $farmId => $farmActivities) {
            $farm = $farmActivities->first()->farm;
            $this->assertNotNull($farm);
            $this->assertTrue($petugas->teams()->whereKey($farm->team_id)->exists(), 'Seeded farm is outside the test Petugas tenant.');
            // Simulate one valid farm assignment at a time, only inside this test transaction.
            $petugas->forceFill(['assigned_farm_id' => $farmId, 'current_team_id' => $farm->team_id])->save();
            $petugas->unsetRelation('assignedFarm');
            $petugas->unsetRelation('currentTeam');
            $this->actingAs($petugas)->get('/petugas/laporan')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('petugas/Laporan')->where('reports.total', $farmActivities->count()));

            foreach ($farmActivities as $activity) {
                $this->assertNotNull($activity->dailyActivityLog, 'Missing header for '.$activity->id);
                $this->assertSame($farmId, $activity->dailyActivityLog->farm_id);
                $this->get('/petugas/laporan/'.$activity->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->component('petugas/ReportShow')->where('log.activities.0.id', $activity->id));
                $tested++;
            }

            $other = $activities->first(fn (FarmActivity $activity): bool => $activity->farm_id !== $farmId);
            if ($other !== null) {
                $this->get('/petugas/laporan/'.$other->id)->assertForbidden();
            }
        }

        $this->assertSame($activities->count(), $tested);
        fwrite(STDERR, sprintf("Full seed coverage: %d activities across %d farms; %d checks with %d actual assigned Petugas accounts.\n",
            $tested, $farmGroups->count(), $actualAssignmentChecks, $assignedUsers->count()));
    }
}

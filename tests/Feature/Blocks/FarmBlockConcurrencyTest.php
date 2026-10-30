<?php

namespace Tests\Feature\Blocks;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Real committed fixtures and two PHP workers on the guarded PostgreSQL test DB. */
class FarmBlockConcurrencyTest extends TestCase
{
    public function test_simultaneous_batches_share_capacity_and_do_not_duplicate_numbers(): void
    {
        $existingRoleIds = DB::table('roles')->pluck('id')->all();
        $actor = User::factory()->create(['role' => 'petugas']);
        $team = Team::factory()->create();
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
        $farm = new Farm(['name' => 'Kebun Uji Konkurensi', 'total_area_ha' => '3.0000']);
        $farm->forceFill(['created_by' => $actor->id, 'team_id' => $team->id])->save();
        $actor->forceFill(['assigned_farm_id' => $farm->id, 'current_team_id' => $team->id])->save();
        $actor->unsetRelation('teams');
        // Fixture creation only; the HTTP policy denying master-block writes by
        // Petugas is tested separately. Workers explicitly authorize batch creation.
        $block = app(FarmBlockService::class)->create($farm, $actor, $team, ['code' => 'B01', 'name' => 'Blok Bersama', 'area_ha' => '3.0000']);
        $workerName = 'block-concurrency-'.Str::random(8);
        $workers = [];
        try {
            DB::beginTransaction();
            DB::table('farms')->where('id', $farm->id)->lockForUpdate()->first();
            foreach ([1, 2] as $number) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/create-preparation-batch.php'), $actor->id, $block->id, $workerName.'-'.$number], base_path(), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql_testing', 'APP_CONFIG_CACHE' => 'storage/framework/cache/block-concurrency-config.php',
                    'TEST_DB_DATABASE' => (string) config('database.connections.pgsql_testing.database'),
                ]);
                $worker->setTimeout(90);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 30;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::table('pg_stat_activity')->where('application_name', 'like', $workerName.'-%')->where('wait_event_type', 'Lock')->count();
                if ($waiting === 2) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = trim($worker->getOutput());
            }
            $this->assertSame(2, $waiting, 'Both requests must actually contend for the farm lock.');
            $this->assertEqualsCanonicalizing(['created', 'capacity'], $results);
            $this->assertSame(1, PlantingCycle::where('farm_id', $farm->id)->count());
            $this->assertSame(1, PlantingCycle::where('farm_id', $farm->id)->value('batch_number'));
            $this->assertSame('2.0000', PlantingCycle::where('farm_id', $farm->id)->value('reserved_area_ha'));
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::transaction(function () use ($farm, $actor, $team, $existingRoleIds): void {
                DB::table('users')->where('id', $actor->id)->update(['assigned_farm_id' => null, 'current_team_id' => null]);
                $cycles = DB::table('planting_cycles')->where('farm_id', $farm->id)->pluck('id');
                DB::table('planting_cycle_stages')->whereIn('planting_cycle_id', $cycles)->delete();
                DB::table('planting_cycles')->where('farm_id', $farm->id)->delete();
                $blocks = FarmBlock::where('farm_id', $farm->id)->pluck('id');
                DB::table('planting_rounds')->whereIn('block_id', $blocks)->delete();
                DB::table('farm_blocks')->where('farm_id', $farm->id)->delete();
                DB::table('farms')->where('id', $farm->id)->delete();
                $personal = $actor->teams()->where('is_personal', true)->pluck('teams.id');
                DB::table('team_members')->where('user_id', $actor->id)->delete();
                DB::table('teams')->whereIn('id', [...$personal, $team->id])->delete();
                DB::table('users')->where('id', $actor->id)->delete();
                DB::table('roles')->whereNotIn('id', $existingRoleIds)
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users')->whereColumn('users.role_id', 'roles.id'))->delete();
            });
        }
    }
}

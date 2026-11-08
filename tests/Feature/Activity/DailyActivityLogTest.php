<?php

namespace Tests\Feature\Activity;

use App\Enums\TeamRole;
use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Activity\DailyActivityLogService;
use Database\Seeders\BlockMappingActivityTypeSeeder;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DailyActivityLogTest extends TestCase
{
    private ?Connection $database = null;

    private User $recorder;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $name = getenv('FARMER_CRUD_TEST_DATABASE');
        if (! $name) {
            $this->markTestSkipped('Set FARMER_CRUD_TEST_DATABASE to a separate migrated PostgreSQL database.');
        }
        $this->assertStringStartsWith('farmer_ic_crud_test_', $name);
        $connection = config('database.connections.pgsql_testing');
        $connection['url'] = null;
        $connection['database'] = $name;
        config(['database.default' => 'daily_logs_test', 'database.connections.daily_logs_test' => $connection]);
        $this->assertSame($name, DB::scalar('SELECT current_database()'));
        $this->database = DB::connection();
        $this->database->beginTransaction();
        $this->seed(BlockMappingActivityTypeSeeder::class);
        $role = Role::create(['name' => 'petugas']);
        $this->recorder = new User([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'password' => 'Daily-log-test-password',
        ]);
        $this->recorder->role()->associate($role);
        $this->recorder->save();
        $this->farm = $this->makeFarm('Kebun Cileuksa');
        $team = Team::query()->create(['name' => 'Daily Log Tenant', 'is_personal' => false]);
        $this->farm->team()->associate($team);
        $this->farm->save();
        DB::table('team_members')->insert([
            'team_id' => $team->id,
            'user_id' => $this->recorder->id,
            'role' => TeamRole::Member->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->recorder->forceFill(['current_team_id' => $team->id, 'assigned_farm_id' => $this->farm->id])->save();
        $this->recorder->refresh();
        $this->actingAs($this->recorder);
    }

    protected function tearDown(): void
    {
        if ($this->database) {
            while ($this->database->transactionLevel() > 0) {
                $this->database->rollBack();
            }
        }
        parent::tearDown();
    }

    public function test_local_create_and_read_preserve_multiple_mapping_details_and_wib_time(): void
    {
        $payload = $this->payload();
        $payload['activities'][] = $payload['activities'][0];
        $payload['activities'][1]['block_mapping']['block_name'] = 'Blok 2';
        $this->assertAuthenticated();
        $response = $this->postJson($this->url(), $payload)->assertCreated()
            ->assertJsonPath('data.worker_count', 6)->assertJsonPath('data.recorded_by', $this->recorder->id)
            ->assertJsonPath('data.recorder_name', 'Budi Santoso')->assertJsonPath('data.start_time', '08:00')
            ->assertJsonCount(2, 'data.activities')->assertJsonPath('data.activities.0.block_mapping.length_m', '35.000')
            ->assertJsonPath('data.activities.0.block_mapping.row_spacing_cm', '40.00')
            ->assertJsonPath('data.activities.0.block_mapping.form_version', 1)
            ->assertJsonPath('data.activities.1.position', 2);
        $id = $response->json('data.id');
        $this->assertTrue(Str::isUuid($id));
        $this->database->statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->assertDatabaseCount('daily_activity_logs', 1);
        $this->assertDatabaseCount('farm_activities', 2);
        $this->assertDatabaseCount('block_mapping_details', 2);
        $log = DailyActivityLog::findOrFail($id);
        $activity = $log->activities->firstOrFail();
        $this->assertSame($activity->id, $activity->blockMappingDetail->getKey());
        $this->assertSame('2026-10-27 01:00', $activity->activity_date->utc()->format('Y-m-d H:i'));
        $this->getJson($this->url().'/'.$id)->assertOk()->assertJsonPath('data.work_date', '2026-10-27')
            ->assertJsonPath('data.activities.1.block_mapping.block_name', 'Blok 2');
        $this->assertStringNotContainsString('password_hash', $response->getContent());
        $this->assertStringNotContainsString('"password"', $response->getContent());
    }

    public function test_list_filters_pagination_and_parent_farm_boundary(): void
    {
        $first = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id');
        $older = $this->payload();
        $older['work_date'] = '2026-10-12';
        $this->postJson($this->url(), $older)->assertCreated();
        $other = $this->makeFarm('Kebun Cariu');
        $this->postJson($this->url($other), $this->payload())->assertForbidden();
        $this->getJson($this->url().'?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $first)->assertJsonPath('meta.last_page', 2);
        $this->getJson($this->url().'?from_date=2026-10-20&to_date=2026-10-27&recorded_by='.$this->recorder->id)
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($this->url().'?from_date=2027-01-01')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->url($other))->assertForbidden();
    }

    public function test_invalid_header_and_mapping_fields_do_not_create_partial_records(): void
    {
        foreach (
            [
                'work_date' => '2026-02-30',
                'start_time' => '25:00',
                'end_time' => '07:00',
                'worker_count' => 0,
                'recorded_by' => (string) Str::uuid(),
                'activities' => [],
                'activities.0.type' => 'soil_measurement',
                'activities.0.block_mapping.block_name' => ' ',
                'activities.0.block_mapping.length_m' => -1,
                'activities.0.block_mapping.width_m' => '1.1234',
                'activities.0.block_mapping.rows_per_bed' => 0,
            ] as $field => $value
        ) {
            $payload = $this->payload();
            data_set($payload, $field, $value);
            $this->postJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('daily_activity_logs', 0);
        $this->assertDatabaseCount('farm_activities', 0);
    }

    public function test_spacing_is_optional_but_requires_both_values_when_measured(): void
    {
        $payload = $this->payload();
        unset($payload['activities'][0]['block_mapping']['row_spacing_cm'], $payload['activities'][0]['block_mapping']['plant_spacing_cm']);
        $this->postJson($this->url(), $payload)->assertCreated()->assertJsonPath('data.activities.0.block_mapping.row_spacing_cm', null);
        $payload['activities'][0]['block_mapping']['row_spacing_cm'] = 40;
        $this->postJson($this->url(), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('activities.0.block_mapping.plant_spacing_cm');
    }

    public function test_reported_obstacles_require_explanation_and_none_cannot_have_obstacles(): void
    {
        $payload = $this->payload();
        $payload['activities'][0]['obstacle_status'] = 'reported';
        $this->postJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors('activities.0.obstacles');
        $payload['activities'][0]['obstacles'] = 'Batas blok mengikuti lereng lahan.';
        $payload['activities'][0]['proposed_solution'] = 'Sesuaikan panjang bedeng dengan batas lahan.';
        $payload['activities'][0]['action_taken'] = 'Batas blok ditandai di lapangan.';
        $this->postJson($this->url(), $payload)->assertCreated()->assertJsonPath('data.activities.0.obstacles', $payload['activities'][0]['obstacles']);
        $payload['activities'][0]['obstacle_status'] = 'none';
        $this->postJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors('activities.0.obstacles');
    }

    public function test_forged_metadata_and_unknown_detail_fields_are_rejected(): void
    {
        $this->postJson($this->url(), [...$this->payload(), 'id' => (string) Str::uuid(), 'farm_id' => (string) Str::uuid(), 'created_at' => '2026-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors(['id', 'farm_id', 'created_at']);
        $payload = $this->payload();
        $payload['activities'][0]['position'] = 99;
        $this->postJson($this->url(), $payload)->assertUnprocessable();
        unset($payload['activities'][0]['position']);
        $payload['activities'][0]['block_mapping']['form_version'] = 99;
        $this->postJson($this->url(), $payload)->assertUnprocessable();
    }

    public function test_inactive_recorder_cannot_create_but_history_remains_readable(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id');
        $this->recorder->is_active = false;
        $this->recorder->save();
        $this->postJson($this->url(), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('recorded_by');
        $this->getJson($this->url().'/'.$id)->assertOk();
        try {
            app(DailyActivityLogService::class)->create($this->farm, $this->payload());
            $this->fail('An inactive recorder must also be rejected outside HTTP.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recorded_by', $exception->errors());
        }
    }

    public function test_missing_type_reference_returns_a_validation_error(): void
    {
        ActivityType::where('code', 'block_mapping')->delete();
        $this->postJson($this->url(), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('activities');
        $this->assertDatabaseCount('daily_activity_logs', 0);
    }

    public function test_transaction_rolls_back_header_and_first_activity_when_second_detail_fails(): void
    {
        $payload = $this->payload();
        $payload['activities'][] = $payload['activities'][0];
        $payload['activities'][1]['block_mapping']['width_m'] = -1;
        try {
            app(DailyActivityLogService::class)->create($this->farm, $payload);
            $this->fail('Expected the database to reject a negative width.');
        } catch (QueryException $error) {
            $this->assertSame('23514', (string) $error->getCode());
        }
        $this->assertDatabaseCount('daily_activity_logs', 0);
        $this->assertDatabaseCount('farm_activities', 0);
        $this->assertDatabaseCount('block_mapping_details', 0);
    }

    public function test_database_rejects_missing_detail_and_removal_of_required_mapping(): void
    {
        $this->rejectDatabaseOperation(function (): void {
            FarmActivity::create([
                'farm_id' => $this->farm->id,
                'activity_type_id' => ActivityType::firstOrFail()->id,
                'performed_by' => $this->recorder->id,
                'activity_date' => now(),
            ]);
        }, '23514');
        $id = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id');
        $this->database->statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->database->statement('SET CONSTRAINTS ALL DEFERRED');
        $activity = DailyActivityLog::findOrFail($id)->activities->firstOrFail();
        $this->rejectDatabaseOperation(fn () => $activity->blockMappingDetail()->delete(), '23514');
        $this->assertTrue($activity->blockMappingDetail()->exists());
    }

    public function test_database_rejects_a_log_activity_moved_to_another_farm(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id');
        $other = $this->makeFarm('Kebun Cariu');
        $activity = DailyActivityLog::findOrFail($id)->activities->firstOrFail();
        $this->rejectDatabaseOperation(fn () => $activity->update(['farm_id' => $other->id]), '23503');
        $this->assertSame($this->farm->id, $activity->fresh()->farm_id);
    }

    public function test_list_validation_and_unknown_ids(): void
    {
        foreach (['per_page=101', 'page=0', 'from_date=invalid', 'from_date=2026-10-27&to_date=2026-10-01', 'recorded_by=invalid'] as $query) {
            $this->getJson($this->url().'?'.$query)->assertUnprocessable();
        }
        $this->getJson($this->url().'/not-a-uuid')->assertNotFound();
        $this->getJson($this->url().'/'.Str::uuid())->assertNotFound();
        $this->getJson('/api/kebun/'.Str::uuid().'/daily-logs')->assertNotFound();
    }

    public function test_endpoints_are_unavailable_in_production_before_database_binding(): void
    {
        $this->app->instance('env', 'production');
        DB::shouldReceive('connection')->never();
        $this->getJson($this->url())->assertNotFound();
        $this->postJson($this->url(), $this->payload())->assertNotFound();
        $this->getJson($this->url().'/'.Str::uuid())->assertNotFound();
    }

    public function test_endpoints_require_postgresql_before_binding(): void
    {
        config(['database.connections.daily_logs_test.driver' => 'sqlite']);
        DB::shouldReceive('connection')->never();
        $this->getJson($this->url())->assertStatus(503);
    }

    private function rejectDatabaseOperation(callable $operation, string $sqlState): void
    {
        try {
            $this->database->transaction(function () use ($operation): void {
                $operation();
                $this->database->statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Expected SQLSTATE '.$sqlState);
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, (string) $exception->getCode());
        }
    }

    private function makeFarm(string $name): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => '12.5000']);
        $farm->creator()->associate($this->recorder);
        $farm->team()->associate(Team::query()->where('name', 'Daily Log Tenant')->first());
        $farm->save();

        return $farm;
    }

    private function url(?Farm $farm = null): string
    {
        return '/api/kebun/'.($farm ?? $this->farm)->id.'/daily-logs';
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'work_date' => '2026-10-27',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'worker_count' => 6,
            'recorded_by' => $this->recorder->id,
            'activities' => [[
                'type' => 'block_mapping',
                'obstacle_status' => 'none',
                'block_mapping' => [
                    'block_name' => 'Blok 1',
                    'length_m' => 35,
                    'width_m' => 50,
                    'row_spacing_cm' => 40,
                    'plant_spacing_cm' => 20,
                    'rows_per_bed' => 2,
                ],
            ]],
        ];
    }
}

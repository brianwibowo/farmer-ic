<?php

namespace Tests\Feature\Farms;

use App\Models\Farm;
use App\Services\FarmService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FarmManagementTest extends TestCase
{
    private bool $transactionStarted = false;

    private Connection $database;

    private string $creatorId;

    private string $ownerId;

    protected function setUp(): void
    {
        parent::setUp();
        $database = getenv('FARMER_CRUD_TEST_DATABASE');
        if (! $database) {
            $this->markTestSkipped('Set FARMER_CRUD_TEST_DATABASE to a separate migrated PostgreSQL database.');
        }
        $this->assertStringStartsWith('farmer_ic_crud_test_', $database);
        $connection = config('database.connections.pgsql');
        $connection['url'] = null;
        $connection['database'] = $database;
        config(['database.default' => 'farms_test', 'database.connections.farms_test' => $connection]);
        $this->assertSame($database, DB::scalar('SELECT current_database()'));
        $this->database = DB::connection();
        $this->database->beginTransaction();
        $this->transactionStarted = true;
        $this->creatorId = $this->createUser('petugas');
        $this->ownerId = $this->createUser('owner');
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            while ($this->database->transactionLevel() > 0) {
                $this->database->rollBack();
            }
        }
        parent::tearDown();
    }

    public function test_local_crud_works_without_login_and_uses_uuid_records(): void
    {
        $this->assertGuest();
        $created = $this->postJson('/api/kebun', $this->payload())->assertCreated()
            ->assertJsonPath('data.created_by', $this->creatorId)
            ->assertJsonPath('data.total_area_ha', '12.5000');
        $id = $created->json('data.id');
        $this->assertTrue(Str::isUuid($id));
        $this->getJson('/api/kebun/'.$id)->assertOk()->assertJsonPath('data.name', 'Kebun Sumber Makmur');
        $this->getJson('/api/kebun')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson('/api/kebun/'.$id, ['name' => 'Kebun Baru'])->assertOk()
            ->assertJsonPath('data.total_area_ha', '12.5000');
        $this->putJson('/api/kebun/'.$id, ['name' => 'Kebun Baru'])->assertUnprocessable()->assertJsonValidationErrors('total_area_ha');
        $this->putJson('/api/kebun/'.$id, ['name' => 'Kebun Pengganti', 'total_area_ha' => '20.0000'])->assertOk();
        $this->deleteJson('/api/kebun/'.$id)->assertNoContent();
        $this->getJson('/api/kebun/'.$id)->assertNotFound();
        $this->assertDatabaseMissing('farms', ['id' => $id]);
    }

    public function test_list_filters_sorting_pagination_and_literal_search(): void
    {
        $this->farm(['name' => 'Jagung 100%', 'city' => 'Wonosobo', 'province' => 'Jawa Tengah', 'total_area_ha' => '10']);
        $this->farm(['name' => 'Kedelai', 'city' => 'Magelang', 'province' => 'Jawa Tengah', 'total_area_ha' => '20']);
        $this->getJson('/api/kebun?search=jagung&city=Wonosobo&province=Jawa%20Tengah')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/kebun?search=%25')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/kebun?sort=total_area_ha&direction=desc&per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Kedelai')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/kebun?search=TidakAda')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/kebun?per_page=101')->assertUnprocessable();
        $this->getJson('/api/kebun?sort=password')->assertUnprocessable();
        $this->getJson('/api/kebun?page=0')->assertUnprocessable();
    }

    public function test_invalid_farm_values_and_forged_metadata_are_rejected(): void
    {
        $this->postJson('/api/kebun', $this->payload([
            'name' => '', 'total_area_ha' => 0, 'latitude' => 91, 'longitude' => -181,
            'owner_id' => $this->creatorId, 'image_url' => 'javascript:alert(1)', 'id' => Str::uuid()->toString(),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['name', 'total_area_ha', 'latitude', 'longitude', 'owner_id', 'image_url', 'id']);
        $this->postJson('/api/kebun', $this->payload(['total_area_ha' => '1.12345']))->assertUnprocessable();
        $this->postJson('/api/kebun', $this->payload(['total_area_ha' => '100000000']))->assertUnprocessable();
        $this->assertDatabaseCount('farms', 0);
    }

    public function test_recorder_is_required_and_must_be_an_existing_active_user(): void
    {
        $this->postJson('/api/kebun', ['name' => 'Kebun Baru', 'total_area_ha' => '1'])->assertUnprocessable()->assertJsonValidationErrors('created_by');
        $this->postJson('/api/kebun', $this->payload(['created_by' => 'not-a-uuid']))->assertUnprocessable()->assertJsonValidationErrors('created_by');
        $this->postJson('/api/kebun', $this->payload(['created_by' => Str::uuid()->toString()]))->assertUnprocessable()->assertJsonValidationErrors('created_by');
        DB::table('users')->where('id', $this->creatorId)->update(['is_active' => false]);
        $this->postJson('/api/kebun', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('created_by');
    }

    public function test_creator_cannot_be_changed_on_update(): void
    {
        $farm = $this->farm();
        $this->patchJson('/api/kebun/'.$farm->id, ['created_by' => $this->ownerId])->assertUnprocessable()->assertJsonValidationErrors('created_by');
        $this->assertSame($this->creatorId, $farm->fresh()->created_by);
    }

    public function test_coordinates_must_remain_a_pair_on_partial_updates(): void
    {
        $this->postJson('/api/kebun', $this->payload(['latitude' => null]))->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $farm = $this->farm();
        $this->patchJson('/api/kebun/'.$farm->id, ['latitude' => null])->assertUnprocessable();
        $this->patchJson('/api/kebun/'.$farm->id, ['latitude' => '-7.1'])->assertOk()->assertJsonPath('data.longitude', '109.9000000');
        $this->patchJson('/api/kebun/'.$farm->id, ['latitude' => null, 'longitude' => null])->assertOk();
    }

    public function test_area_cannot_be_reduced_below_active_planting_and_history_blocks_delete(): void
    {
        $farm = $this->farm();
        $commodity = Str::uuid()->toString();
        $variety = Str::uuid()->toString();
        DB::table('commodities')->insert(['id' => $commodity, 'name' => 'Jagung']);
        DB::table('crop_varieties')->insert(['id' => $variety, 'commodity_id' => $commodity, 'name' => 'Bisi']);
        DB::table('planting_cycles')->insert([
            'id' => Str::uuid()->toString(), 'farm_id' => $farm->id, 'variety_id' => $variety,
            'planted_at' => '2026-10-01', 'planted_area_ha' => '10.2500', 'status' => 'active', 'created_at' => now(),
        ]);
        $this->patchJson('/api/kebun/'.$farm->id, ['total_area_ha' => '10.2499'])->assertUnprocessable()->assertJsonValidationErrors('total_area_ha');
        $this->assertSame('12.5000', $farm->fresh()->total_area_ha);
        $this->patchJson('/api/kebun/'.$farm->id, ['total_area_ha' => '10.2500'])->assertOk();
        $this->deleteJson('/api/kebun/'.$farm->id)->assertConflict();
        $this->assertDatabaseHas('farms', ['id' => $farm->id]);
    }

    public function test_weather_history_is_not_silently_cascaded_on_delete(): void
    {
        $farm = $this->farm();
        DB::table('weather_records')->insert(['id' => Str::uuid()->toString(), 'farm_id' => $farm->id, 'recorded_at' => now()]);
        $this->deleteJson('/api/kebun/'.$farm->id)->assertConflict();
        $this->assertDatabaseCount('weather_records', 1);
    }

    public function test_invalid_and_unknown_ids_return_not_found(): void
    {
        $this->getJson('/api/kebun/not-a-uuid')->assertNotFound();
        $this->getJson('/api/kebun/'.Str::uuid())->assertNotFound();
        $this->patchJson('/api/kebun/'.Str::uuid(), ['name' => 'Hilang'])->assertNotFound();
        $this->deleteJson('/api/kebun/'.Str::uuid())->assertNotFound();
    }

    public function test_development_api_is_disabled_in_production_before_binding_models(): void
    {
        $this->app->instance('env', 'production');
        DB::shouldReceive('connection')->never();
        $this->getJson('/api/kebun')->assertNotFound();
        $this->postJson('/api/kebun', $this->payload())->assertNotFound();
        $this->getJson('/api/kebun/'.Str::uuid())->assertNotFound();
        $this->patchJson('/api/kebun/'.Str::uuid(), ['name' => 'Tidak boleh'])->assertNotFound();
        $this->deleteJson('/api/kebun/'.Str::uuid())->assertNotFound();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Kebun Sumber Makmur', 'created_by' => $this->creatorId, 'owner_id' => $this->ownerId,
            'address' => 'Desa Kejajar', 'city' => 'Wonosobo', 'district' => 'Kejajar', 'province' => 'Jawa Tengah',
            'total_area_ha' => '12.5000', 'latitude' => '-7.2000000', 'longitude' => '109.9000000',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function farm(array $overrides = []): Farm
    {
        $data = $this->payload($overrides);
        unset($data['created_by']);

        return app(FarmService::class)->create($data, $this->creatorId);
    }

    private function createUser(string $role): string
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id');
        if (! $roleId) {
            $roleId = Str::uuid()->toString();
            DB::table('roles')->insert(['id' => $roleId, 'name' => $role]);
        }
        $id = Str::uuid()->toString();
        DB::table('users')->insert([
            'id' => $id, 'role_id' => $roleId, 'name' => ucfirst($role), 'email' => $id.'@farmer.test',
            'password' => 'not-used-in-these-tests', 'is_active' => true, 'created_at' => now(),
        ]);

        return $id;
    }
}

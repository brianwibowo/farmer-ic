<?php

namespace Tests\Feature\Database;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\AuditLog;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmRisk;
use App\Models\FarmUser;
use App\Models\FertilizationDetail;
use App\Models\FinancialBudget;
use App\Models\FinancialCategory;
use App\Models\FinancialProjection;
use App\Models\FinancialTransaction;
use App\Models\HarvestDetail;
use App\Models\IncidentDetail;
use App\Models\IrrigationDetail;
use App\Models\Notification;
use App\Models\OperationalTarget;
use App\Models\PesticideDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingDetail;
use App\Models\Role;
use App\Models\WeatherRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FarmerModelsTest extends TestCase
{
    private ?Connection $database = null;

    private FarmUser $user;

    private Farm $farm;

    private PlantingCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        $name = getenv('FARMER_CRUD_TEST_DATABASE');
        if (! $name) {
            $this->markTestSkipped('Set FARMER_CRUD_TEST_DATABASE to a separate migrated PostgreSQL database.');
        }
        $this->assertStringStartsWith('farmer_ic_crud_test_', $name);
        $connection = config('database.connections.pgsql');
        $connection['url'] = null;
        $connection['database'] = $name;
        config(['database.default' => 'farmer_models_test', 'database.connections.farmer_models_test' => $connection]);
        $this->assertSame($name, DB::scalar('SELECT current_database()'));
        $this->database = DB::connection();
        $this->database->beginTransaction();

        $role = Role::create(['name' => 'petugas']);
        $this->user = new FarmUser(['name' => 'Petugas Kebun', 'email' => Str::uuid().'@farmer-ic.local', 'password_hash' => 'Model-test-password']);
        $this->user->role()->associate($role);
        $this->user->save();
        $this->farm = new Farm(['name' => 'Kebun Sumber Makmur', 'total_area_ha' => '12.5000']);
        $this->farm->creator()->associate($this->user);
        $this->farm->owner()->associate($this->user);
        $this->farm->save();
        $commodity = Commodity::create(['name' => 'Jagung']);
        $variety = $commodity->varieties()->create(['name' => 'Bisi 18']);
        $this->cycle = $this->farm->plantingCycles()->create([
            'variety_id' => $variety->id, 'planted_at' => '2026-10-01',
            'estimated_harvest_at' => '2027-01-15', 'planted_area_ha' => '5.1250', 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->database !== null) {
            while ($this->database->transactionLevel() > 0) {
                $this->database->rollBack();
            }
        }
        parent::tearDown();
    }

    public function test_uuid_relationships_and_financial_records_round_trip_on_postgresql(): void
    {
        $loaded = Farm::with(['owner.role', 'creator', 'plantingCycles.variety.commodity'])->findOrFail($this->farm->id);
        $this->assertTrue(Str::isUuid($loaded->owner->id));
        $this->assertSame($this->user->id, $loaded->creator->id);
        $this->assertSame('petugas', $loaded->owner->role->name);
        $this->assertSame('Jagung', $loaded->plantingCycles->sole()->variety->commodity->name);
        $this->assertSame('5.1250', $loaded->plantingCycles->sole()->planted_area_ha);
        $this->assertInstanceOf(CarbonImmutable::class, $this->cycle->fresh()->planted_at);
        $this->assertNotNull($this->cycle->created_at);
        $this->assertArrayNotHasKey('updated_at', $this->cycle->getAttributes());
        $this->assertSame($this->farm->id, $this->cycle->farm->id);
        $this->assertSame($this->cycle->id, CropVariety::firstOrFail()->plantingCycles->sole()->id);

        $category = FinancialCategory::create(['name' => 'Penjualan panen', 'transaction_type' => 'income']);
        $transaction = FinancialTransaction::create([
            'farm_id' => $this->farm->id, 'planting_cycle_id' => $this->cycle->id,
            'category_id' => $category->id, 'recorded_by' => $this->user->id,
            'transaction_date' => '2026-10-21', 'transaction_type' => 'income', 'amount' => '8300000.75',
        ]);
        $projection = FinancialProjection::create([
            'farm_id' => $this->farm->id, 'planting_cycle_id' => $this->cycle->id,
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'projected_income' => '10000000.25', 'calculated_at' => now(),
        ]);
        $budget = FinancialBudget::create([
            'farm_id' => $this->farm->id, 'category_id' => $category->id, 'created_by' => $this->user->id,
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'budget_amount' => '9000000.50',
        ]);
        $target = OperationalTarget::create([
            'farm_id' => $this->farm->id, 'planting_cycle_id' => $this->cycle->id,
            'task_name' => 'Panen jagung', 'target_unit' => 'Ton', 'target_value' => '20.125',
            'created_by' => $this->user->id,
        ]);
        $this->assertSame('8300000.75', $transaction->fresh()->amount);
        $this->assertSame($this->user->id, $transaction->recorder->id);
        $this->assertSame($category->id, $transaction->category->id);
        $this->assertSame($transaction->id, $category->transactions->sole()->id);
        $this->assertSame($projection->id, $this->cycle->financialProjections->sole()->id);
        $this->assertSame($budget->id, $category->budgets->sole()->id);
        $this->assertSame($this->user->id, $budget->creator->id);
        $this->assertSame('0.000', $target->fresh()->completed_value);
        $this->assertSame($target->id, $this->farm->operationalTargets->sole()->id);
        $this->assertArrayNotHasKey('created_at', $target->getAttributes());
        $this->assertArrayNotHasKey('updated_at', $budget->getAttributes());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_all_activity_details_use_the_parent_key_and_monitoring_relations_work(): void
    {
        $details = [
            ['planting', PlantingDetail::class, 'plantingDetail', ['seed_name' => 'Bisi 18', 'area_ha' => '5.1250']],
            ['fertilization', FertilizationDetail::class, 'fertilizationDetail', ['quantity_kg' => '12.125']],
            ['irrigation', IrrigationDetail::class, 'irrigationDetail', ['duration_hours' => '2.50']],
            ['pesticide', PesticideDetail::class, 'pesticideDetail', ['dosage_per_tank' => '20 ml', 'tank_count' => 3]],
            ['incident', IncidentDetail::class, 'incidentDetail', ['affected_area_ha' => '0.1250']],
            ['harvest', HarvestDetail::class, 'harvestDetail', ['gross_weight_ton' => '12.125', 'moisture_percentage' => '14.25']],
        ];
        foreach ($details as [$code, $model, $relation, $values]) {
            $activity = $this->activity($code);
            $detail = $activity->{$relation}()->create($values);
            $this->assertSame('activity_id', $detail->getKeyName());
            $this->assertSame($activity->id, $detail->getKey());
            $this->assertArrayNotHasKey('id', $detail->getAttributes());
            $this->assertArrayNotHasKey('created_at', $detail->getAttributes());
            $this->assertSame($activity->id, $model::findOrFail($activity->id)->activity->id);
            $this->assertSame($activity->id, $activity->fresh()->{$relation}->getKey());
            foreach ($values as $key => $value) {
                $this->assertSame($value, $detail->fresh()->{$key});
            }
        }
        $activity = $this->activity('growth_check');
        $observation = $activity->observation()->create([
            'planting_cycle_id' => $this->cycle->id, 'observed_by' => $this->user->id,
            'observed_at' => now(), 'average_height_cm' => '125.25', 'health_status' => 'healthy',
        ]);
        $risk = FarmRisk::create([
            'planting_cycle_id' => $this->cycle->id, 'observation_id' => $observation->id,
            'risk_description' => 'Perlu pemantauan kelembapan', 'detected_at' => now(),
        ]);
        $photo = ActivityPhoto::create(['activity_id' => $activity->id, 'image_url' => '/images/kebun.jpg']);
        $weather = WeatherRecord::create([
            'farm_id' => $this->farm->id, 'recorded_at' => now(), 'temperature_c' => '27.50',
        ]);
        $this->assertSame($observation->id, $activity->fresh()->observation->id);
        $this->assertSame($activity->id, $observation->activity->id);
        $this->assertSame($this->user->id, $observation->observer->id);
        $this->assertSame($this->cycle->id, $observation->plantingCycle->id);
        $this->assertSame($risk->id, $observation->risks->sole()->id);
        $this->assertInstanceOf(CarbonImmutable::class, $photo->fresh()->uploaded_at);
        $this->assertSame($photo->id, $activity->photos->sole()->id);
        $this->assertSame($activity->id, $photo->activity->id);
        $this->assertSame($this->farm->id, $weather->farm->id);
        $this->assertSame($weather->id, $this->farm->weatherRecords->sole()->id);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_password_visibility_boolean_json_casts_and_optional_relations(): void
    {
        $user = $this->user->fresh();
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Model-test-password', $user->password_hash));
        $this->assertArrayNotHasKey('password_hash', $user->toArray());
        $this->assertFalse($user->isFillable('role_id'));
        $this->assertFalse($user->isFillable('is_active'));
        $notification = Notification::create(['user_id' => $user->id, 'title' => 'Jadwal panen']);
        $this->assertFalse($notification->fresh()->is_read);
        $notification->update(['is_read' => true, 'read_at' => now()]);
        $this->assertTrue($notification->fresh()->is_read);
        $this->assertInstanceOf(CarbonImmutable::class, $notification->fresh()->read_at);
        $this->assertSame($notification->id, $user->notifications->sole()->id);
        $audit = AuditLog::create([
            'user_id' => null, 'table_name' => 'farms', 'record_id' => $this->farm->id, 'action' => 'update',
            'old_values' => ['name' => 'Kebun Lama'], 'new_values' => ['name' => 'Kebun Baru', 'active' => true],
        ]);
        $this->assertSame(['name' => 'Kebun Baru', 'active' => true], $audit->fresh()->new_values);
        $this->assertNull($audit->user);
        $this->farm->owner()->dissociate();
        $this->farm->save();
        $this->assertNull($this->farm->fresh()->owner);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    private function activity(string $code): FarmActivity
    {
        $type = ActivityType::create(['code' => $code, 'name' => ucfirst($code)]);

        return FarmActivity::create([
            'farm_id' => $this->farm->id, 'planting_cycle_id' => $this->cycle->id,
            'activity_type_id' => $type->id, 'performed_by' => $this->user->id,
            'activity_date' => '2026-10-21 10:00:00+07:00',
        ]);
    }
}

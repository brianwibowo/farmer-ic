<?php

namespace Tests\Feature\Activity;

use App\Models\ActivityType;
use Database\Seeders\SopActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FertilizationActivityTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_and_seeder_make_base_fertilization_selectable_without_changing_its_identity(): void
    {
        $type = ActivityType::query()->where('code', 'base_fertilization')->firstOrFail();
        $id = $type->id;
        $parent = ActivityType::query()->where('code', 'soil_preparation')->firstOrFail();
        $type->update(['parent_id' => $parent->id, 'is_selectable' => false, 'sort_order' => 24]);

        $migration = require database_path('migrations/2026_11_11_000001_make_base_fertilization_a_root_activity.php');
        $migration->up();
        $this->assertDatabaseHas('activity_types', [
            'id' => $id, 'code' => 'base_fertilization', 'parent_id' => null,
            'is_selectable' => true, 'detail_key' => 'fertilization', 'sort_order' => 15,
        ]);
        $this->seed(SopActivityTypeSeeder::class);
        $this->assertDatabaseHas('activity_types', [
            'id' => $id, 'code' => 'base_fertilization', 'parent_id' => null,
            'is_selectable' => true, 'detail_key' => 'fertilization', 'sort_order' => 24,
        ]);
        $followUp = ActivityType::query()->where('code', 'follow_up_fertilization')->firstOrFail();
        $this->assertSame('maintenance', $followUp->parent->code);
        $this->assertTrue($followUp->is_selectable);
    }

    public function test_order_migration_moves_only_base_fertilization_and_seeding_preserves_the_order(): void
    {
        $type = ActivityType::query()->where('code', 'base_fertilization')->firstOrFail();
        $type->update(['sort_order' => 15]);
        $before = $type->fresh()->getRawOriginal();
        $otherTypes = ActivityType::query()->where('code', '!=', 'base_fertilization')
            ->orderBy('code')->get()->map(fn (ActivityType $other) => $other->getRawOriginal())->all();

        $migration = require database_path('migrations/2026_11_12_000002_order_base_fertilization_after_soil_preparation.php');
        $migration->up();
        $this->assertSame([...$before, 'sort_order' => 24], $type->fresh()->getRawOriginal());
        $this->assertSame($otherTypes, ActivityType::query()->where('code', '!=', 'base_fertilization')
            ->orderBy('code')->get()->map(fn (ActivityType $other) => $other->getRawOriginal())->all());

        $migration->down();
        $this->assertSame(15, $type->fresh()->sort_order);
        $migration->up();
        $this->seed(SopActivityTypeSeeder::class);
        $this->assertSame(24, $type->fresh()->sort_order);
    }
}

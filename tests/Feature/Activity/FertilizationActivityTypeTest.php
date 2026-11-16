<?php

namespace Tests\Feature\Activity;

use App\Models\ActivityType;
use Database\Seeders\SopActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FertilizationActivityTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_keeps_base_fertilization_as_pemupukan_kandang_root_activity(): void
    {
        $type = ActivityType::query()->where('code', 'base_fertilization')->firstOrFail();
        $id = $type->id;
        $this->assertSame('Pemupukan Kandang', $type->name);
        $this->assertNull($type->parent_id);
        $this->assertTrue($type->is_selectable);
        $this->assertSame('fertilization', $type->detail_key);
        $this->assertSame(24, $type->sort_order);

        $this->seed(SopActivityTypeSeeder::class);
        $this->assertDatabaseHas('activity_types', [
            'id' => $id, 'code' => 'base_fertilization', 'name' => 'Pemupukan Kandang',
            'parent_id' => null, 'is_selectable' => true, 'detail_key' => 'fertilization', 'sort_order' => 24,
        ]);
        $this->assertDatabaseMissing('activity_types', ['code' => 'follow_up_fertilization']);
    }

    public function test_simplified_root_categories_order_is_preserved_after_seeding(): void
    {
        $otherTypes = ActivityType::query()->where('code', '!=', 'base_fertilization')
            ->orderBy('code')->get()->map(fn (ActivityType $other) => $other->getRawOriginal())->all();

        $this->seed(SopActivityTypeSeeder::class);
        $this->assertSame(24, ActivityType::query()->where('code', 'base_fertilization')->value('sort_order'));
        $this->assertSame($otherTypes, ActivityType::query()->where('code', '!=', 'base_fertilization')
            ->orderBy('code')->get()->map(fn (ActivityType $other) => $other->getRawOriginal())->all());
    }
}

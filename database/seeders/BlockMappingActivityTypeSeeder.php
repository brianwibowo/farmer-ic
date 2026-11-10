<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

class BlockMappingActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        ActivityType::query()->updateOrCreate(['code' => 'block_mapping'], [
            'name' => 'Pemetaan Blok',
            'description' => 'Pengukuran dimensi blok dan pencatatan rencana jarak tanam serta baris per bedeng.',
            'parent_id' => null,
            'detail_key' => 'block_mapping',
            'is_selectable' => false,
            'sort_order' => 99,
            'is_active' => true,
        ]);
    }
}

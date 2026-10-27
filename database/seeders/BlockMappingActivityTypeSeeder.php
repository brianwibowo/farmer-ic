<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

class BlockMappingActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        ActivityType::firstOrCreate(['code' => 'block_mapping'], [
            'name' => 'Pemetaan Blok',
            'description' => 'Pengukuran dimensi blok dan pencatatan rencana jarak tanam serta baris per bedeng.',
        ]);
    }
}

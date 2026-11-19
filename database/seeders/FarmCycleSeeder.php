<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Installation fixture: attach previously seeded batches without changing explicit cycle choices. */
class FarmCycleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            DB::statement('INSERT INTO farm_cycles (id, farm_id, cycle_number, created_by, created_at)
                SELECT gen_random_uuid(), f.id, 1, f.created_by, COALESCE(MIN(p.created_at), CURRENT_TIMESTAMP)
                FROM farms f JOIN planting_cycles p ON p.farm_id = f.id WHERE p.farm_cycle_id IS NULL
                GROUP BY f.id, f.created_by ON CONFLICT (farm_id, cycle_number) DO NOTHING');
            DB::statement('UPDATE planting_cycles p SET farm_cycle_id = c.id FROM farm_cycles c
                WHERE c.farm_id = p.farm_id AND c.cycle_number = 1 AND p.farm_cycle_id IS NULL');
        });
    }
}

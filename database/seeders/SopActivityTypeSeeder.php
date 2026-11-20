<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

class SopActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'land_cleaning', 'name' => 'Pembersihan Lahan', 'parent' => null, 'detail_key' => 'land_cleaning', 'selectable' => true, 'sort_order' => 10, 'description' => 'Pembersihan gulma, sisa tanaman, dan material pengganggu sebelum pengolahan tanah.'],
            ['code' => 'soil_preparation', 'name' => 'Pengolahan Tanah', 'parent' => null, 'detail_key' => null, 'selectable' => true, 'sort_order' => 20, 'description' => 'Pencatatan pengolahan tanah pada blok lahan.'],
            ['code' => 'base_fertilization', 'name' => 'Pemupukan Kandang', 'parent' => null, 'detail_key' => 'fertilization', 'selectable' => true, 'sort_order' => 24, 'description' => 'Pencatatan pemupukan kandang sebelum penanaman.'],
            ['code' => 'planting', 'name' => 'Penanaman', 'parent' => null, 'detail_key' => 'planting', 'selectable' => true, 'sort_order' => 30, 'description' => 'Pencatatan proses penanaman.'],
            ['code' => 'maintenance', 'name' => 'Perawatan', 'parent' => null, 'detail_key' => null, 'selectable' => true, 'sort_order' => 40, 'description' => 'Pencatatan perawatan tanaman pada subblok.'],
            ['code' => 'growth_observation', 'name' => 'Pengamatan Pertumbuhan', 'parent' => null, 'detail_key' => 'growth_check', 'selectable' => false, 'sort_order' => 45, 'description' => 'Pencatatan pengamatan fase pertumbuhan tanaman pada subblok.'],
            ['code' => 'harvest', 'name' => 'Panen', 'parent' => null, 'detail_key' => 'harvest', 'selectable' => true, 'sort_order' => 50, 'description' => 'Pencatatan hasil panen.'],
            ['code' => 'incident', 'name' => 'Pelaporan Insiden', 'parent' => null, 'detail_key' => 'incident', 'selectable' => true, 'sort_order' => 60],
            ['code' => 'other', 'name' => 'Lainnya', 'parent' => null, 'detail_key' => 'other', 'selectable' => true, 'sort_order' => 70],
        ];

        foreach ($types as $type) {
            ActivityType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'parent_id' => null,
                    'name' => $type['name'],
                    'description' => $type['description'] ?? null,
                    'detail_key' => $type['detail_key'],
                    'is_selectable' => $type['selectable'],
                    'sort_order' => $type['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}

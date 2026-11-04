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
            ['code' => 'soil_preparation', 'name' => 'Pengolahan Tanah', 'parent' => null, 'detail_key' => null, 'selectable' => false, 'sort_order' => 20, 'description' => 'Pembajakan, penggemburan, dan pembentukan bedengan.'],
            ['code' => 'loosening', 'name' => 'Penggemburan', 'parent' => 'soil_preparation', 'detail_key' => 'loosening', 'selectable' => true, 'sort_order' => 21],
            ['code' => 'beds', 'name' => 'Pembuatan Bedengan', 'parent' => 'soil_preparation', 'detail_key' => 'bed_preparation', 'selectable' => true, 'sort_order' => 22],
            ['code' => 'liming', 'name' => 'Penebaran Dolomit', 'parent' => 'soil_preparation', 'detail_key' => 'liming', 'selectable' => true, 'sort_order' => 23],
            ['code' => 'base_fertilization', 'name' => 'Pemupukan Dasar', 'parent' => 'soil_preparation', 'detail_key' => 'fertilization', 'selectable' => true, 'sort_order' => 24],
            ['code' => 'planting', 'name' => 'Penanaman', 'parent' => null, 'detail_key' => 'planting', 'selectable' => true, 'sort_order' => 30, 'description' => 'Pencatatan proses penanaman.'],
            ['code' => 'maintenance', 'name' => 'Perawatan', 'parent' => null, 'detail_key' => null, 'selectable' => false, 'sort_order' => 40],
            ['code' => 'irrigation', 'name' => 'Pengairan', 'parent' => 'maintenance', 'detail_key' => 'irrigation', 'selectable' => true, 'sort_order' => 41, 'description' => 'Pencatatan pengairan lahan.'],
            ['code' => 'weeding', 'name' => 'Pendangiran', 'parent' => 'maintenance', 'detail_key' => 'weeding', 'selectable' => true, 'sort_order' => 42],
            ['code' => 'follow_up_fertilization', 'name' => 'Pemupukan susulan', 'parent' => 'maintenance', 'detail_key' => 'fertilization', 'selectable' => true, 'sort_order' => 43, 'description' => 'Pencatatan pemupukan tanaman.'],
            ['code' => 'growth_check', 'name' => 'Pengecekan pertumbuhan', 'parent' => 'maintenance', 'detail_key' => 'growth_check', 'selectable' => true, 'sort_order' => 44],
            ['code' => 'pest_control', 'name' => 'Pengendalian hama dan penyakit', 'parent' => 'maintenance', 'detail_key' => 'pesticide', 'selectable' => true, 'sort_order' => 45, 'description' => 'Pencatatan aplikasi pengendalian hama dan penyakit.'],
            ['code' => 'harvest', 'name' => 'Panen', 'parent' => null, 'detail_key' => 'harvest', 'selectable' => true, 'sort_order' => 50, 'description' => 'Pencatatan hasil panen.'],
            ['code' => 'incident', 'name' => 'Pelaporan Insiden', 'parent' => null, 'detail_key' => 'incident', 'selectable' => true, 'sort_order' => 60],
            ['code' => 'other', 'name' => 'Lainnya', 'parent' => null, 'detail_key' => 'other', 'selectable' => true, 'sort_order' => 70],
        ];

        foreach ($types as $type) {
            $parentId = $type['parent'] === null
                ? null
                : ActivityType::query()->where('code', $type['parent'])->value('id');

            ActivityType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'parent_id' => $parentId,
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

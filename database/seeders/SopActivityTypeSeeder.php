<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

class SopActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'land_cleaning', 'name' => 'Pembersihan Lahan', 'description' => 'Pembersihan gulma, sisa tanaman, dan material pengganggu sebelum pengolahan tanah.'],
            ['code' => 'soil_preparation', 'name' => 'Pengolahan Tanah', 'description' => 'Pembajakan, penggemburan, dan pembentukan bedengan.'],
            ['code' => 'planting', 'name' => 'Penanaman', 'description' => 'Pencatatan proses penanaman.'],
            ['code' => 'fertilization', 'name' => 'Pemupukan', 'description' => 'Pencatatan pemupukan tanaman.'],
            ['code' => 'irrigation', 'name' => 'Irigasi', 'description' => 'Pencatatan pengairan lahan.'],
            ['code' => 'pesticide', 'name' => 'Pengendalian Hama', 'description' => 'Pencatatan aplikasi pengendalian hama dan penyakit.'],
            ['code' => 'harvest', 'name' => 'Panen', 'description' => 'Pencatatan hasil panen.'],
        ] as $type) {
            ActivityType::query()->firstOrCreate(['code' => $type['code']], $type);
        }
    }
}

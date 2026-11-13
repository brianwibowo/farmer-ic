<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdministrativeRegionSeeder::class,
            DemoIdentitySeeder::class,
            DemoFarmSeeder::class,
            SopActivityTypeSeeder::class,
            DemoSopSeeder::class,
            DemoActivitySeeder::class,
            DemoMonitoringSeeder::class,
            DemoFinanceSeeder::class,
            DemoOperationsSeeder::class,
            DemoOwnerPortfolioSeeder::class,
            PresentationNamesSeeder::class,
            FarmLegalitySeeder::class,
        ]);

        // The imported logbook contains simulated future harvests and demo credentials.
        // The full seed includes it only in local/test PostgreSQL environments.
        if (CariuBaruSeeder::supportsCurrentDatabase()) {
            $this->call(CariuBaruSeeder::class);
        } elseif (app()->environment('local', 'testing')) {
            $this->command?->warn('Seed Cariu Baru dilewati: memerlukan koneksi PostgreSQL.');
        }
    }
}

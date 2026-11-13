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
        // It is never added to an arbitrary or production database by the full seed.
        if (CariuBaruSeeder::supportsCurrentDatabase()) {
            $this->call(CariuBaruSeeder::class);
        } elseif (app()->environment('local')) {
            $this->command?->warn('Seed Cariu Baru dilewati: database bukan farmer_ic_dev lokal yang diizinkan.');
        }
    }
}

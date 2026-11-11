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
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Data demo hanya dapat diisi pada environment local atau testing.');
        }

        $this->call([
            AdministrativeRegionSeeder::class,
            DemoIdentitySeeder::class,
            DemoFarmSeeder::class,
            DemoActivitySeeder::class,
            SopActivityTypeSeeder::class,
            DemoSopSeeder::class,
            DemoMonitoringSeeder::class,
            DemoFinanceSeeder::class,
            DemoOperationsSeeder::class,
            DemoOwnerPortfolioSeeder::class,
            PresentationNamesSeeder::class,
        ]);
    }
}

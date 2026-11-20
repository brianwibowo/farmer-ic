<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->jsonb('boundary_geojson')->nullable();
            $table->decimal('mapped_area_ha', 12, 4)->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE farm_blocks ADD CONSTRAINT blocks_boundary_values_check CHECK ((mapped_area_ha IS NULL OR mapped_area_ha > 0) AND ((boundary_geojson IS NULL) = (mapped_area_ha IS NULL)))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE farm_blocks DROP CONSTRAINT IF EXISTS blocks_boundary_values_check');
        }

        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->dropColumn(['boundary_geojson', 'mapped_area_ha']);
        });
    }
};

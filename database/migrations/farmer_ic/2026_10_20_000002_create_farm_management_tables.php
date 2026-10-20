<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('Farmer IC requires a separate PostgreSQL database. Run with --database=pgsql --path=database/migrations/farmer_ic.');
        }

        Schema::create('farms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('owner_id')->nullable();
            $table->uuid('created_by');
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->decimal('total_area_ha', 12, 4);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('image_url')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index('owner_id');
            $table->index('created_by');
        });
        DB::statement('ALTER TABLE farms ADD CONSTRAINT farms_latitude_range CHECK (latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE farms ADD CONSTRAINT farms_longitude_range CHECK (longitude BETWEEN -180 AND 180)');
        DB::statement('ALTER TABLE farms ADD CONSTRAINT farms_total_area_ha_positive CHECK (total_area_ha > 0)');

        Schema::create('commodities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();

        });

        Schema::create('crop_varieties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('commodity_id');
            $table->string('name', 150);
            $table->text('description')->nullable();

            $table->foreign('commodity_id')->references('id')->on('commodities')->restrictOnDelete();
            $table->unique(['commodity_id', 'name']);
        });

        Schema::create('planting_cycles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id');
            $table->uuid('variety_id');
            $table->date('planted_at');
            $table->date('estimated_harvest_at')->nullable();
            $table->decimal('planted_area_ha', 12, 4);
            $table->decimal('estimated_weight_ton', 14, 3)->nullable();
            $table->string('status', 30);
            $table->timestampTz('created_at');

            $table->foreign('farm_id')->references('id')->on('farms')->restrictOnDelete();
            $table->foreign('variety_id')->references('id')->on('crop_varieties')->restrictOnDelete();
            $table->index('farm_id');
            $table->index('variety_id');
        });
        DB::statement('ALTER TABLE planting_cycles ADD CONSTRAINT planting_cycles_harvest_date_order CHECK (estimated_harvest_at IS NULL OR estimated_harvest_at >= planted_at)');
        DB::statement('ALTER TABLE planting_cycles ADD CONSTRAINT planting_cycles_planted_area_ha_positive CHECK (planted_area_ha > 0)');
        DB::statement('ALTER TABLE planting_cycles ADD CONSTRAINT planting_cycles_estimated_weight_ton_nonnegative CHECK (estimated_weight_ton >= 0)');
        DB::statement('ALTER TABLE planting_cycles ADD CONSTRAINT planting_cycles_status_allowed CHECK (status IN (\'planned\', \'active\', \'harvested\', \'cancelled\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('planting_cycles');
        Schema::dropIfExists('crop_varieties');
        Schema::dropIfExists('commodities');
        Schema::dropIfExists('farms');
    }
};

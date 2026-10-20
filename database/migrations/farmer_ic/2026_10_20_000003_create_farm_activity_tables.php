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

        Schema::create('activity_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();

        });
        DB::statement('ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN (\'planting\', \'fertilization\', \'irrigation\', \'growth_check\', \'pesticide\', \'incident\', \'harvest\'))');

        Schema::create('farm_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id');
            $table->uuid('planting_cycle_id')->nullable();
            $table->uuid('activity_type_id');
            $table->uuid('performed_by');
            $table->timestampTz('activity_date');
            $table->text('description')->nullable();
            $table->string('status', 30)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('farm_id')->references('id')->on('farms')->restrictOnDelete();
            $table->foreign('planting_cycle_id')->references('id')->on('planting_cycles')->restrictOnDelete();
            $table->foreign('activity_type_id')->references('id')->on('activity_types')->restrictOnDelete();
            $table->foreign('performed_by')->references('id')->on('users')->restrictOnDelete();
            $table->index('activity_type_id');
            $table->index('performed_by');
            $table->index(['farm_id', 'activity_date']);
            $table->index(['planting_cycle_id', 'activity_date']);
            $table->index('activity_date');
        });

        Schema::create('activity_photos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('activity_id');
            $table->string('label', 150)->nullable();
            $table->text('caption')->nullable();
            $table->text('image_url');
            $table->timestampTz('uploaded_at');

            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
            $table->index('activity_id');
        });

        Schema::create('planting_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->string('seed_name', 150)->nullable();
            $table->decimal('quantity_kg', 12, 3)->nullable();
            $table->string('planting_pattern', 150)->nullable();
            $table->decimal('area_ha', 12, 4)->nullable();

            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE planting_details ADD CONSTRAINT planting_details_quantity_kg_nonnegative CHECK (quantity_kg >= 0)');
        DB::statement('ALTER TABLE planting_details ADD CONSTRAINT planting_details_area_ha_nonnegative CHECK (area_ha >= 0)');

        Schema::create('fertilization_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->string('fertilization_stage', 100)->nullable();
            $table->string('fertilizer_type', 150)->nullable();
            $table->string('brand_name', 150)->nullable();
            $table->string('subsidy_status', 30)->nullable();
            $table->string('application_method', 150)->nullable();
            $table->decimal('quantity_kg', 12, 3)->nullable();

            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE fertilization_details ADD CONSTRAINT fertilization_details_quantity_kg_nonnegative CHECK (quantity_kg >= 0)');

        Schema::create('irrigation_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->string('water_source', 150)->nullable();
            $table->string('irrigation_method', 150)->nullable();
            $table->string('machine_type', 150)->nullable();
            $table->decimal('duration_hours', 8, 2)->nullable();

            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE irrigation_details ADD CONSTRAINT irrigation_details_duration_hours_nonnegative CHECK (duration_hours >= 0)');

        Schema::create('pesticide_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->string('pesticide_category', 100)->nullable();
            $table->string('purpose', 100)->nullable();
            $table->string('brand_name', 150)->nullable();
            $table->string('active_ingredient', 150)->nullable();
            $table->string('dosage_per_tank', 100)->nullable();
            $table->decimal('tank_size_liters', 8, 2)->nullable();
            $table->integer('tank_count')->nullable();

            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE pesticide_details ADD CONSTRAINT pesticide_details_tank_size_liters_nonnegative CHECK (tank_size_liters >= 0)');
        DB::statement('ALTER TABLE pesticide_details ADD CONSTRAINT pesticide_details_tank_count_nonnegative CHECK (tank_count >= 0)');

        Schema::create('incident_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->string('incident_category', 150)->nullable();
            $table->string('severity', 30)->nullable();
            $table->decimal('affected_area_ha', 12, 4)->nullable();
            $table->text('proposed_solution')->nullable();

            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE incident_details ADD CONSTRAINT incident_details_affected_area_ha_nonnegative CHECK (affected_area_ha >= 0)');

        Schema::create('harvest_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->decimal('harvested_area_ha', 12, 4)->nullable();
            $table->decimal('gross_weight_ton', 14, 3)->nullable();
            $table->decimal('moisture_percentage', 5, 2)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('ripeness', 150)->nullable();
            $table->string('cleanliness', 150)->nullable();

            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE harvest_details ADD CONSTRAINT harvest_details_harvested_area_ha_nonnegative CHECK (harvested_area_ha >= 0)');
        DB::statement('ALTER TABLE harvest_details ADD CONSTRAINT harvest_details_gross_weight_ton_nonnegative CHECK (gross_weight_ton >= 0)');
        DB::statement('ALTER TABLE harvest_details ADD CONSTRAINT harvest_details_moisture_percentage_range CHECK (moisture_percentage BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_details');
        Schema::dropIfExists('incident_details');
        Schema::dropIfExists('pesticide_details');
        Schema::dropIfExists('irrigation_details');
        Schema::dropIfExists('fertilization_details');
        Schema::dropIfExists('planting_details');
        Schema::dropIfExists('activity_photos');
        Schema::dropIfExists('farm_activities');
        Schema::dropIfExists('activity_types');
    }
};

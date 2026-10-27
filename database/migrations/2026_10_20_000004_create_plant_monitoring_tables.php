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
            throw new LogicException('Farmer IC requires PostgreSQL. Run php artisan migrate --database=pgsql.');
        }

        Schema::create('plant_observations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('planting_cycle_id');
            $table->uuid('activity_id')->nullable()->unique();
            $table->uuid('observed_by');
            $table->timestampTz('observed_at');
            $table->string('growth_phase', 100)->nullable();
            $table->decimal('average_height_cm', 8, 2)->nullable();
            $table->string('disease_indication', 100)->nullable();
            $table->string('pest_indication', 100)->nullable();
            $table->string('health_status', 30)->nullable();
            $table->decimal('growth_percentage', 5, 2)->nullable();
            $table->text('plant_condition')->nullable();
            $table->text('notes')->nullable();

            $table->foreign('planting_cycle_id')->references('id')->on('planting_cycles')->restrictOnDelete();
            $table->foreign('activity_id')->references('id')->on('farm_activities')->nullOnDelete();
            $table->foreign('observed_by')->references('id')->on('users')->restrictOnDelete();
            $table->index('observed_by');
            $table->index(['planting_cycle_id', 'observed_at']);
            $table->index('observed_at');
        });
        DB::statement('ALTER TABLE plant_observations ADD CONSTRAINT plant_observations_average_height_cm_nonnegative CHECK (average_height_cm >= 0)');
        DB::statement('ALTER TABLE plant_observations ADD CONSTRAINT plant_observations_growth_percentage_range CHECK (growth_percentage BETWEEN 0 AND 100)');

        Schema::create('weather_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id');
            $table->timestampTz('recorded_at');
            $table->string('condition', 100)->nullable();
            $table->decimal('temperature_c', 5, 2)->nullable();
            $table->decimal('humidity_percentage', 5, 2)->nullable();
            $table->string('data_source', 100)->nullable();

            $table->foreign('farm_id')->references('id')->on('farms')->cascadeOnDelete();
            $table->index(['farm_id', 'recorded_at']);
        });
        DB::statement('ALTER TABLE weather_records ADD CONSTRAINT weather_records_humidity_percentage_range CHECK (humidity_percentage BETWEEN 0 AND 100)');

        Schema::create('farm_risks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('planting_cycle_id');
            $table->uuid('observation_id')->nullable();
            $table->text('risk_description');
            $table->string('severity', 30)->nullable();
            $table->text('recommendation')->nullable();
            $table->string('status', 30)->nullable();
            $table->timestampTz('detected_at');

            $table->foreign('planting_cycle_id')->references('id')->on('planting_cycles')->restrictOnDelete();
            $table->foreign('observation_id')->references('id')->on('plant_observations')->nullOnDelete();
            $table->index('observation_id');
            $table->index(['planting_cycle_id', 'status']);
        });

        Schema::create('ai_insights', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('planting_cycle_id');
            $table->uuid('observation_id')->nullable();
            $table->text('summary')->nullable();
            $table->text('next_step')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('model_name', 100)->nullable();
            $table->timestampTz('generated_at');

            $table->foreign('planting_cycle_id')->references('id')->on('planting_cycles')->restrictOnDelete();
            $table->foreign('observation_id')->references('id')->on('plant_observations')->nullOnDelete();
            $table->index('observation_id');
            $table->index(['planting_cycle_id', 'generated_at']);
        });
        DB::statement('ALTER TABLE ai_insights ADD CONSTRAINT ai_insights_confidence_score_range CHECK (confidence_score BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('farm_risks');
        Schema::dropIfExists('weather_records');
        Schema::dropIfExists('plant_observations');
    }
};

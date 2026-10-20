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

        Schema::create('operational_targets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id');
            $table->uuid('planting_cycle_id')->nullable();
            $table->string('task_name', 200);
            $table->string('target_unit', 20);
            $table->decimal('target_value', 14, 3);
            $table->decimal('completed_value', 14, 3)->default(0);
            $table->date('target_date')->nullable();
            $table->string('status', 30)->nullable();
            $table->uuid('created_by');

            $table->foreign('farm_id')->references('id')->on('farms')->restrictOnDelete();
            $table->foreign('planting_cycle_id')->references('id')->on('planting_cycles')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index('planting_cycle_id');
            $table->index('created_by');
            $table->index(['farm_id', 'target_date']);
        });
        DB::statement('ALTER TABLE operational_targets ADD CONSTRAINT operational_targets_target_value_nonnegative CHECK (target_value >= 0)');
        DB::statement('ALTER TABLE operational_targets ADD CONSTRAINT operational_targets_completed_value_nonnegative CHECK (completed_value >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_targets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A task may only point to a stage of the same subblok.
        Schema::table('planting_cycle_stages', function (Blueprint $table): void {
            $table->unique(['id', 'planting_cycle_id'], 'planting_cycle_stages_id_cycle_unique');
        });

        Schema::create('planting_cycle_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('planting_cycle_id');
            $table->uuid('planting_cycle_stage_id');
            $table->uuid('sop_template_task_id')->nullable();
            $table->string('activity_category', 30);
            $table->string('activity_subcategory', 50)->nullable();
            $table->string('name', 200);
            $table->text('instructions')->nullable();
            $table->integer('offset_days')->default(0);
            $table->integer('tolerance_days')->default(0);
            $table->date('scheduled_date')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('status', 30)->default('pending');
            $table->timestampTz('completed_at')->nullable();
            $table->text('skip_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('planting_cycle_id')->references('id')->on('planting_cycles')->cascadeOnDelete();
            $table->foreign('sop_template_task_id')->references('id')->on('sop_template_tasks')->nullOnDelete();
            $table->foreign(['planting_cycle_stage_id', 'planting_cycle_id'])
                ->references(['id', 'planting_cycle_id'])
                ->on('planting_cycle_stages')
                ->restrictOnDelete();

            $table->unique(['planting_cycle_id', 'sop_template_task_id']);
            $table->index('sop_template_task_id');
            $table->index(['planting_cycle_stage_id', 'planting_cycle_id']);
            $table->index(['planting_cycle_id', 'scheduled_date', 'status']);
            $table->index(['planting_cycle_id', 'offset_days']);
        });

        DB::statement(<<<'SQL'
ALTER TABLE planting_cycle_tasks ADD CONSTRAINT planting_cycle_tasks_values_check CHECK (
    btrim(name) <> ''
    AND activity_category IN ('planting', 'maintenance', 'harvest')
    AND (
        (activity_category = 'maintenance' AND activity_subcategory IN ('irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control'))
        OR (activity_category <> 'maintenance' AND activity_subcategory IS NULL)
    )
    AND offset_days >= 0
    AND tolerance_days >= 0
    AND status IN ('pending', 'in_progress', 'submitted', 'completed', 'skipped', 'cancelled')
    AND (
        (scheduled_date IS NULL AND due_date IS NULL)
        OR (scheduled_date IS NOT NULL AND due_date IS NOT NULL AND due_date >= scheduled_date)
    )
    AND (completed_at IS NULL OR status = 'completed')
)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('planting_cycle_tasks');

        Schema::table('planting_cycle_stages', function (Blueprint $table): void {
            $table->dropUnique('planting_cycle_stages_id_cycle_unique');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planting_cycle_tasks', function (Blueprint $table): void {
            $table->uuid('completed_by')->nullable();
            $table->text('completion_note')->nullable();

            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
            $table->index('completed_by');
        });

        DB::table('planting_cycle_tasks')->where('status', 'submitted')->update(['status' => 'in_progress']);
        DB::table('planting_cycle_tasks')->where('status', 'cancelled')->update([
            'status' => 'skipped',
            'skip_reason' => DB::raw('COALESCE(skip_reason, cancel_reason)'),
        ]);

        DB::statement('ALTER TABLE planting_cycle_tasks DROP CONSTRAINT planting_cycle_tasks_values_check');
        DB::statement(<<<'SQL'
ALTER TABLE planting_cycle_tasks ADD CONSTRAINT planting_cycle_tasks_values_check CHECK (
    btrim(name) <> ''
    AND activity_category IN ('maintenance', 'harvest')
    AND (
        (activity_category = 'maintenance' AND activity_subcategory IN ('irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control'))
        OR (activity_category = 'harvest' AND activity_subcategory IS NULL)
    )
    AND offset_days >= 0
    AND tolerance_days >= 0
    AND status IN ('pending', 'in_progress', 'completed', 'skipped')
    AND (
        (scheduled_date IS NULL AND due_date IS NULL)
        OR (scheduled_date IS NOT NULL AND due_date IS NOT NULL AND due_date >= scheduled_date)
    )
    AND (completed_at IS NULL OR status = 'completed')
    AND (completed_by IS NULL OR status = 'completed')
    AND (completion_note IS NULL OR status = 'completed')
)
SQL);

        Schema::table('planting_cycle_tasks', fn (Blueprint $table) => $table->dropColumn('cancel_reason'));
    }

    public function down(): void
    {
        Schema::table('planting_cycle_tasks', fn (Blueprint $table) => $table->text('cancel_reason')->nullable());

        DB::statement('ALTER TABLE planting_cycle_tasks DROP CONSTRAINT planting_cycle_tasks_values_check');
        DB::statement(<<<'SQL'
ALTER TABLE planting_cycle_tasks ADD CONSTRAINT planting_cycle_tasks_values_check CHECK (
    btrim(name) <> ''
    AND activity_category IN ('maintenance', 'harvest')
    AND (
        (activity_category = 'maintenance' AND activity_subcategory IN ('irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control'))
        OR (activity_category = 'harvest' AND activity_subcategory IS NULL)
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

        Schema::table('planting_cycle_tasks', function (Blueprint $table): void {
            $table->dropForeign(['completed_by']);
            $table->dropIndex(['completed_by']);
            $table->dropColumn(['completed_by', 'completion_note']);
        });
    }
};

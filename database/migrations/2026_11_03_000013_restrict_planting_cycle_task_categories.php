<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Planting is the HST anchor and planting-form step, never a task row.
        DB::table('planting_cycle_tasks')
            ->where('activity_category', 'planting')
            ->delete();

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
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE planting_cycle_tasks DROP CONSTRAINT planting_cycle_tasks_values_check');
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
};

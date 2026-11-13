<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve all rows accepted by the earlier constraint, while also
        // allowing a valid due date based on an earlier Admin override.
        $this->replaceConstraint('due_date >= LEAST(scheduled_date, COALESCE(rescheduled_date, scheduled_date))');
    }

    public function down(): void
    {
        if (DB::table('planting_cycle_tasks')->whereNotNull('scheduled_date')
            ->whereNotNull('due_date')->whereColumn('due_date', '<', 'scheduled_date')->exists()) {
            throw new LogicException('Cannot restore the old task constraint while an earlier reschedule exists; preserve and review those tasks first.');
        }

        $this->replaceConstraint('due_date >= scheduled_date');
    }

    private function replaceConstraint(string $dateCheck): void
    {
        DB::statement('ALTER TABLE planting_cycle_tasks DROP CONSTRAINT planting_cycle_tasks_values_check');
        DB::statement(<<<SQL
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
        OR (scheduled_date IS NOT NULL AND due_date IS NOT NULL AND {$dateCheck})
    )
    AND (completed_at IS NULL OR status = 'completed')
    AND (completed_by IS NULL OR status = 'completed')
    AND (completion_note IS NULL OR status = 'completed')
)
SQL);
    }
};

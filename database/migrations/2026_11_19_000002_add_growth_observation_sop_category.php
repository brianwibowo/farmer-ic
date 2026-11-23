<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceConstraints(['maintenance', 'growth_observation', 'harvest']);
    }

    public function down(): void
    {
        DB::table('sop_template_tasks')
            ->where('activity_category', 'growth_observation')
            ->update(['activity_category' => 'maintenance']);
        DB::table('planting_cycle_tasks')
            ->where('activity_category', 'growth_observation')
            ->update(['activity_category' => 'maintenance']);

        $this->replaceConstraints(['maintenance', 'harvest']);
    }

    /** @param list<string> $categories */
    private function replaceConstraints(array $categories): void
    {
        $list = implode(', ', array_map(static fn (string $c): string => "'{$c}'", $categories));

        DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT IF EXISTS sop_template_tasks_values_check');
        DB::statement(<<<SQL
ALTER TABLE sop_template_tasks ADD CONSTRAINT sop_template_tasks_values_check CHECK (
    btrim(name) <> ''
    AND activity_category IN ({$list})
    AND activity_subcategory IS NULL
    AND tolerance_days >= 0
)
SQL);

        DB::statement('ALTER TABLE planting_cycle_tasks DROP CONSTRAINT IF EXISTS planting_cycle_tasks_values_check');
        DB::statement(<<<SQL
ALTER TABLE planting_cycle_tasks ADD CONSTRAINT planting_cycle_tasks_values_check CHECK (
    btrim(name) <> ''
    AND activity_category IN ({$list})
    AND activity_subcategory IS NULL
    AND offset_days >= 0
    AND tolerance_days >= 0
    AND status IN ('pending', 'in_progress', 'completed', 'skipped')
    AND (
        (scheduled_date IS NULL AND due_date IS NULL)
        OR (scheduled_date IS NOT NULL AND due_date IS NOT NULL AND due_date >= LEAST(scheduled_date, COALESCE(rescheduled_date, scheduled_date)))
    )
    AND (completed_at IS NULL OR status = 'completed')
    AND (completed_by IS NULL OR status = 'completed')
    AND (completion_note IS NULL OR status = 'completed')
)
SQL);
    }
};

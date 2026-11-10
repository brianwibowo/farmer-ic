<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Planting is recorded by the planting activity, not authored as an SOP step.
        DB::table('sop_template_tasks')
            ->where('activity_category', 'planting')
            ->delete();

        DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT sop_template_tasks_values_check');
        DB::statement(<<<'SQL'
ALTER TABLE sop_template_tasks ADD CONSTRAINT sop_template_tasks_values_check CHECK (
    btrim(name) <> ''
    AND activity_category IN ('maintenance', 'harvest')
    AND (
        (activity_category = 'maintenance' AND activity_subcategory IN ('irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control'))
        OR (activity_category = 'harvest' AND activity_subcategory IS NULL)
    )
    AND tolerance_days >= 0
)
SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT sop_template_tasks_values_check');
        DB::statement(<<<'SQL'
ALTER TABLE sop_template_tasks ADD CONSTRAINT sop_template_tasks_values_check CHECK (
    btrim(name) <> ''
    AND activity_category IN ('planting', 'maintenance', 'harvest')
    AND (
        (activity_category = 'maintenance' AND activity_subcategory IN ('irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control'))
        OR (activity_category <> 'maintenance' AND activity_subcategory IS NULL)
    )
    AND tolerance_days >= 0
)
SQL);
    }
};

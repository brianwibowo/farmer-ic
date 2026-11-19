<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sop_template_tasks')->where('is_required', false)->update(['is_required' => true]);
        DB::table('planting_cycle_tasks')->where('is_required', false)->update(['is_required' => true]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT IF EXISTS sop_template_tasks_required_check');
            DB::statement('ALTER TABLE sop_template_tasks ADD CONSTRAINT sop_template_tasks_required_check CHECK (is_required = true)');

            DB::statement('ALTER TABLE planting_cycle_tasks DROP CONSTRAINT IF EXISTS planting_cycle_tasks_required_check');
            DB::statement('ALTER TABLE planting_cycle_tasks ADD CONSTRAINT planting_cycle_tasks_required_check CHECK (is_required = true)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT IF EXISTS sop_template_tasks_required_check');
            DB::statement('ALTER TABLE planting_cycle_tasks DROP CONSTRAINT IF EXISTS planting_cycle_tasks_required_check');
        }
    }
};

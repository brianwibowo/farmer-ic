<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT IF EXISTS sop_template_tasks_values_check');

        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->dropColumn('schedule_anchor');
        });

        DB::statement(<<<'SQL'
ALTER TABLE sop_template_tasks ADD CONSTRAINT sop_template_tasks_values_check CHECK (
    btrim(name) <> ''
    AND stage_code IN ('land_cleaning', 'soil_preparation', 'basic_fertilizer', 'planting', 'maintenance', 'harvest')
    AND tolerance_days >= 0
)
SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT IF EXISTS sop_template_tasks_values_check');

        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->string('schedule_anchor', 30)->nullable();
        });

        DB::statement("UPDATE sop_template_tasks SET schedule_anchor = 'planting_date' WHERE schedule_anchor IS NULL");
        DB::statement('ALTER TABLE sop_template_tasks ALTER COLUMN schedule_anchor SET NOT NULL');

        DB::statement(<<<'SQL'
ALTER TABLE sop_template_tasks ADD CONSTRAINT sop_template_tasks_values_check CHECK (
    btrim(name) <> ''
    AND stage_code IN ('land_cleaning', 'soil_preparation', 'basic_fertilizer', 'planting', 'maintenance', 'harvest')
    AND schedule_anchor IN ('planting_date', 'stage_start')
    AND tolerance_days >= 0
)
SQL);
    }
};

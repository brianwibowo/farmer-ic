<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->dropUnique(['sop_template_version_id', 'position']);
            $table->dropColumn('position');
            $table->index(['sop_template_version_id', 'offset_days']);
        });
    }

    public function down(): void
    {
        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->dropIndex(['sop_template_version_id', 'offset_days']);
            $table->integer('position')->nullable();
        });

        DB::statement(<<<'SQL'
WITH ranked AS (
    SELECT
        id,
        row_number() OVER (
            PARTITION BY sop_template_version_id
            ORDER BY
                offset_days,
                CASE stage_code
                    WHEN 'land_cleaning' THEN 1
                    WHEN 'soil_preparation' THEN 2
                    WHEN 'basic_fertilizer' THEN 3
                    WHEN 'planting' THEN 4
                    WHEN 'maintenance' THEN 5
                    WHEN 'harvest' THEN 6
                    ELSE 7
                END,
                created_at,
                id
        ) AS position
    FROM sop_template_tasks
)
UPDATE sop_template_tasks AS task
SET position = ranked.position
FROM ranked
WHERE ranked.id = task.id
SQL);

        DB::statement('ALTER TABLE sop_template_tasks ALTER COLUMN position SET NOT NULL');

        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->unique(['sop_template_version_id', 'position']);
        });
    }
};

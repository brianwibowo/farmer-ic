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
            $table->string('activity_category', 30)->default('planting');
            $table->string('activity_subcategory', 50)->nullable();
        });

        // Preserve the closest category for existing rows before the old
        // activity type and stage columns are removed.
        DB::statement(<<<'SQL'
UPDATE sop_template_tasks AS task
SET
    activity_category = CASE task.stage_code
        WHEN 'planting' THEN 'planting'
        WHEN 'maintenance' THEN 'maintenance'
        WHEN 'harvest' THEN 'harvest'
        WHEN 'land_cleaning' THEN 'land_cleaning'
        WHEN 'soil_preparation' THEN 'soil_preparation'
        WHEN 'basic_fertilizer' THEN 'soil_preparation'
        ELSE 'planting'
    END,
    activity_subcategory = CASE
        WHEN task.stage_code = 'maintenance' THEN CASE type.code
            WHEN 'irrigation' THEN 'irrigation'
            WHEN 'growth_check' THEN 'growth_check'
            WHEN 'pesticide' THEN 'pest_control'
            WHEN 'fertilization' THEN 'follow_up_fertilization'
            ELSE 'follow_up_fertilization'
        END
        WHEN task.stage_code IN ('soil_preparation', 'basic_fertilizer') THEN 'loosening'
        ELSE NULL
    END
FROM activity_types AS type
WHERE type.id = task.activity_type_id
SQL);

        // SOPs start at planting. Preparation work belongs to the batch and
        // block workflow, not to the SOP task list.
        DB::table('sop_template_tasks')
            ->whereNotIn('activity_category', ['planting', 'maintenance', 'harvest'])
            ->delete();

        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->dropForeign(['activity_type_id']);
            $table->dropIndex(['activity_type_id']);
            $table->dropIndex(['sop_template_version_id', 'stage_code']);
            $table->dropColumn(['activity_type_id', 'stage_code']);
        });

        DB::statement('ALTER TABLE sop_template_tasks ALTER COLUMN activity_category SET NOT NULL');
        DB::statement('ALTER TABLE sop_template_tasks ALTER COLUMN activity_category DROP DEFAULT');

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

        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->index(['sop_template_version_id', 'activity_category']);
            $table->index(['sop_template_version_id', 'activity_subcategory']);
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT IF EXISTS sop_template_tasks_values_check');

        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->uuid('activity_type_id')->nullable();
            $table->string('stage_code', 30)->nullable();
        });

        DB::statement(<<<'SQL'
UPDATE sop_template_tasks AS task
SET
    stage_code = task.activity_category,
    activity_type_id = COALESCE(
        (
            SELECT type.id
            FROM activity_types AS type
            WHERE type.code = CASE
                WHEN task.activity_category = 'planting' THEN 'planting'
                WHEN task.activity_category = 'harvest' THEN 'harvest'
                WHEN task.activity_category = 'maintenance' THEN CASE task.activity_subcategory
                    WHEN 'irrigation' THEN 'irrigation'
                    WHEN 'growth_check' THEN 'growth_check'
                    WHEN 'pest_control' THEN 'pesticide'
                    WHEN 'weeding' THEN 'soil_preparation'
                    ELSE 'fertilization'
                END
                ELSE 'planting'
            END
            LIMIT 1
        ),
        (SELECT type.id FROM activity_types AS type ORDER BY type.code LIMIT 1)
    )
SQL);

        DB::statement('ALTER TABLE sop_template_tasks ALTER COLUMN activity_type_id SET NOT NULL');
        DB::statement('ALTER TABLE sop_template_tasks ALTER COLUMN stage_code SET NOT NULL');

        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->dropIndex(['sop_template_version_id', 'activity_category']);
            $table->dropIndex(['sop_template_version_id', 'activity_subcategory']);
            $table->dropColumn(['activity_category', 'activity_subcategory']);
        });

        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->foreign('activity_type_id')->references('id')->on('activity_types')->restrictOnDelete();
            $table->index('activity_type_id');
            $table->index(['sop_template_version_id', 'stage_code']);
        });

        DB::statement(<<<'SQL'
ALTER TABLE sop_template_tasks ADD CONSTRAINT sop_template_tasks_values_check CHECK (
    btrim(name) <> ''
    AND stage_code IN ('land_cleaning', 'soil_preparation', 'basic_fertilizer', 'planting', 'maintenance', 'harvest')
    AND tolerance_days >= 0
)
SQL);
    }
};

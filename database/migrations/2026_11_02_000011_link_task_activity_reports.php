<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planting_cycle_tasks', fn (Blueprint $table) => $table->unique(['id', 'planting_cycle_id'], 'tasks_id_cycle_unique'));
        Schema::table('farm_activities', function (Blueprint $table): void {
            $table->uuid('planting_cycle_task_id')->nullable()->index();
            $table->jsonb('task_details')->nullable();
            $table->foreign(['planting_cycle_task_id', 'planting_cycle_id'], 'activities_task_cycle_fk')
                ->references(['id', 'planting_cycle_id'])->on('planting_cycle_tasks')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE farm_activities ADD CONSTRAINT activities_task_cycle_required CHECK (planting_cycle_task_id IS NULL OR (planting_cycle_id IS NOT NULL AND task_details IS NOT NULL))');

        // The taxonomy migration also owns the activity code constraint and the
        // detail guard. When it has already run, only add the task-report columns.
        $taxonomyApplied = Schema::hasColumn('activity_types', 'detail_key');

        if (! $taxonomyApplied) {
            DB::statement('ALTER TABLE activity_types DROP CONSTRAINT activity_types_code_allowed');
            DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('planting','fertilization','irrigation','growth_check','pesticide','incident','harvest','block_mapping','land_cleaning','soil_preparation','weeding'))");
        }

        DB::table('activity_types')->insertOrIgnore(['id' => (string) Illuminate\Support\Str::uuid(), 'code' => 'weeding', 'name' => 'Pendangiran', 'description' => 'Pendangiran sesuai tugas SOP.']);

        if (! $taxonomyApplied) {
            // Preserve the existing typed-detail validator for all existing activity types.
            DB::statement('DROP FUNCTION IF EXISTS farmer_ic_check_typed_activity_detail(uuid)');
            DB::statement('ALTER FUNCTION farmer_ic_check_activity_detail(uuid) RENAME TO farmer_ic_check_typed_activity_detail');
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION farmer_ic_check_activity_detail(activity_uuid uuid)
RETURNS void LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM farm_activities a JOIN activity_types t ON t.id = a.activity_type_id WHERE a.id = activity_uuid AND t.code = 'weeding') THEN
        IF NOT EXISTS (SELECT 1 FROM farm_activities WHERE id = activity_uuid AND planting_cycle_task_id IS NOT NULL AND jsonb_typeof(task_details) = 'object')
            OR EXISTS (SELECT 1 FROM planting_details WHERE activity_id = activity_uuid)
            OR EXISTS (SELECT 1 FROM fertilization_details WHERE activity_id = activity_uuid)
            OR EXISTS (SELECT 1 FROM irrigation_details WHERE activity_id = activity_uuid)
            OR EXISTS (SELECT 1 FROM pesticide_details WHERE activity_id = activity_uuid)
            OR EXISTS (SELECT 1 FROM incident_details WHERE activity_id = activity_uuid)
            OR EXISTS (SELECT 1 FROM harvest_details WHERE activity_id = activity_uuid)
            OR EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid)
            OR EXISTS (SELECT 1 FROM block_mapping_details WHERE activity_id = activity_uuid) THEN
            RAISE EXCEPTION 'Weeding requires a task report and no mismatched typed details' USING ERRCODE = '23514';
        END IF;
    ELSE
        PERFORM farmer_ic_check_typed_activity_detail(activity_uuid);
    END IF;
END;
$$;
SQL);
        }
    }

    public function down(): void
    {
        if (DB::table('farm_activities')->whereNotNull('planting_cycle_task_id')->exists()) {
            throw new LogicException('Cannot roll back persisted task reports.');
        }

        if ($this->typedDetailFunctionExists()) {
            DB::statement('DROP FUNCTION IF EXISTS farmer_ic_check_activity_detail(uuid)');
            DB::statement('ALTER FUNCTION farmer_ic_check_typed_activity_detail(uuid) RENAME TO farmer_ic_check_activity_detail');
        }

        if (! Schema::hasColumn('activity_types', 'detail_key')) {
            DB::table('activity_types')->where('code', 'weeding')->delete();
            DB::statement('ALTER TABLE activity_types DROP CONSTRAINT activity_types_code_allowed');
            DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('planting','fertilization','irrigation','growth_check','pesticide','incident','harvest','block_mapping','land_cleaning','soil_preparation'))");
        }

        DB::statement('ALTER TABLE farm_activities DROP CONSTRAINT activities_task_cycle_required');
        Schema::table('farm_activities', function (Blueprint $table): void {
            $table->dropForeign('activities_task_cycle_fk');
            $table->dropColumn(['planting_cycle_task_id', 'task_details']);
        });
        Schema::table('planting_cycle_tasks', fn (Blueprint $table) => $table->dropUnique('tasks_id_cycle_unique'));
    }

    private function typedDetailFunctionExists(): bool
    {
        return DB::scalar("SELECT to_regprocedure('farmer_ic_check_typed_activity_detail(uuid)')") !== null;
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('Daily activity logs require PostgreSQL.');
        }

        Schema::create('daily_activity_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id')->index();
            $table->uuid('recorded_by')->index();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('worker_count');
            $table->text('notes')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at')->nullable();
            $table->foreign('farm_id')->references('id')->on('farms')->restrictOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['id', 'farm_id'], 'daily_logs_id_farm_unique');
            $table->index(['farm_id', 'work_date']);
        });
        DB::statement('ALTER TABLE daily_activity_logs ADD CONSTRAINT daily_logs_work_hours_check CHECK (end_time > start_time)');
        DB::statement('ALTER TABLE daily_activity_logs ADD CONSTRAINT daily_logs_worker_count_check CHECK (worker_count BETWEEN 1 AND 32767)');

        Schema::table('farm_activities', function (Blueprint $table): void {
            $table->uuid('daily_activity_log_id')->nullable()->index();
            $table->integer('position')->nullable();
            $table->string('obstacle_status', 30)->default('not_recorded');
            $table->text('obstacles')->nullable();
            $table->text('proposed_solution')->nullable();
            $table->text('action_taken')->nullable();
            $table->foreign(['daily_activity_log_id', 'farm_id'], 'activities_daily_log_farm_foreign')
                ->references(['id', 'farm_id'])->on('daily_activity_logs')->restrictOnDelete();
            $table->unique(['daily_activity_log_id', 'position'], 'activities_daily_log_position_unique');
        });
        DB::statement('ALTER TABLE farm_activities ADD CONSTRAINT activities_daily_position_check CHECK ((daily_activity_log_id IS NULL AND position IS NULL) OR (daily_activity_log_id IS NOT NULL AND position IS NOT NULL AND position > 0))');
        DB::statement("ALTER TABLE farm_activities ADD CONSTRAINT activities_obstacle_status_check CHECK (obstacle_status IN ('not_recorded', 'none', 'reported'))");
        DB::statement("ALTER TABLE farm_activities ADD CONSTRAINT activities_reported_obstacle_check CHECK (obstacle_status <> 'reported' OR (obstacles IS NOT NULL AND btrim(obstacles) <> ''))");

        Schema::create('block_mapping_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->integer('form_version')->default(1);
            $table->string('block_name', 100);
            $table->decimal('length_m', 10, 3);
            $table->decimal('width_m', 10, 3);
            $table->decimal('row_spacing_cm', 8, 2)->nullable();
            $table->decimal('plant_spacing_cm', 8, 2)->nullable();
            $table->integer('rows_per_bed')->nullable();
            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE block_mapping_details ADD CONSTRAINT block_mapping_dimensions_check CHECK (length_m > 0 AND width_m > 0 AND btrim(block_name) <> '')");
        DB::statement('ALTER TABLE block_mapping_details ADD CONSTRAINT block_mapping_spacing_check CHECK ((row_spacing_cm IS NULL AND plant_spacing_cm IS NULL) OR (row_spacing_cm IS NOT NULL AND plant_spacing_cm IS NOT NULL AND row_spacing_cm > 0 AND plant_spacing_cm > 0))');
        DB::statement('ALTER TABLE block_mapping_details ADD CONSTRAINT block_mapping_rows_check CHECK (rows_per_bed IS NULL OR rows_per_bed BETWEEN 1 AND 32767)');
        DB::statement('ALTER TABLE block_mapping_details ADD CONSTRAINT block_mapping_version_check CHECK (form_version > 0)');

        $this->allowBlockMapping(true);
        DB::unprepared($this->detailFunction(true));
        DB::statement('CREATE CONSTRAINT TRIGGER block_mapping_details_detail_guard AFTER INSERT OR UPDATE OR DELETE ON block_mapping_details DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_validate_activity_details()');
    }

    public function down(): void
    {
        if (DB::table('daily_activity_logs')->exists() || DB::table('block_mapping_details')->exists()
            || DB::table('farm_activities')->whereIn('activity_type_id', DB::table('activity_types')->select('id')->where('code', 'block_mapping'))->exists()) {
            throw new LogicException('Daily log records must be explicitly preserved or removed before rolling back this feature.');
        }
        DB::statement('DROP TRIGGER block_mapping_details_detail_guard ON block_mapping_details');
        DB::unprepared($this->detailFunction(false));
        Schema::dropIfExists('block_mapping_details');
        Schema::table('farm_activities', function (Blueprint $table): void {
            $table->dropForeign('activities_daily_log_farm_foreign');
            $table->dropUnique('activities_daily_log_position_unique');
        });
        foreach (['activities_daily_position_check', 'activities_obstacle_status_check', 'activities_reported_obstacle_check'] as $constraint) {
            DB::statement("ALTER TABLE farm_activities DROP CONSTRAINT {$constraint}");
        }
        Schema::table('farm_activities', fn (Blueprint $table) => $table->dropColumn([
            'daily_activity_log_id', 'position', 'obstacle_status', 'obstacles', 'proposed_solution', 'action_taken',
        ]));
        Schema::dropIfExists('daily_activity_logs');
        DB::table('activity_types')->where('code', 'block_mapping')->delete();
        $this->allowBlockMapping(false);
    }

    private function allowBlockMapping(bool $enabled): void
    {
        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT activity_types_code_allowed');
        $extra = $enabled ? ", 'block_mapping'" : '';
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('planting', 'fertilization', 'irrigation', 'growth_check', 'pesticide', 'incident', 'harvest'{$extra}))");
    }

    /** @return literal-string */
    private function detailFunction(bool $includeMapping): string
    {
        $extraCount = $includeMapping ? '+ (EXISTS (SELECT 1 FROM block_mapping_details WHERE activity_id = activity_uuid))::int' : '';
        $extraCase = $includeMapping ? "WHEN 'block_mapping' THEN EXISTS (SELECT 1 FROM block_mapping_details WHERE activity_id = activity_uuid)" : '';

        return <<<SQL
CREATE OR REPLACE FUNCTION farmer_ic_check_activity_detail(activity_uuid uuid)
RETURNS void LANGUAGE plpgsql AS \$\$
DECLARE
    activity_record record;
    detail_count integer;
    matching_detail boolean;
    planting_date date;
BEGIN
    SELECT a.planting_cycle_id, a.activity_date, t.code INTO activity_record
    FROM farm_activities a JOIN activity_types t ON t.id = a.activity_type_id
    WHERE a.id = activity_uuid;
    IF NOT FOUND THEN RETURN; END IF;

    SELECT
        (EXISTS (SELECT 1 FROM planting_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM fertilization_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM irrigation_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM pesticide_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM incident_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM harvest_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid))::int
        {$extraCount}
    INTO detail_count;

    matching_detail := CASE activity_record.code
        WHEN 'planting' THEN EXISTS (SELECT 1 FROM planting_details WHERE activity_id = activity_uuid)
        WHEN 'fertilization' THEN EXISTS (SELECT 1 FROM fertilization_details WHERE activity_id = activity_uuid)
        WHEN 'irrigation' THEN EXISTS (SELECT 1 FROM irrigation_details WHERE activity_id = activity_uuid)
        WHEN 'pesticide' THEN EXISTS (SELECT 1 FROM pesticide_details WHERE activity_id = activity_uuid)
        WHEN 'incident' THEN EXISTS (SELECT 1 FROM incident_details WHERE activity_id = activity_uuid)
        WHEN 'harvest' THEN EXISTS (SELECT 1 FROM harvest_details WHERE activity_id = activity_uuid)
        WHEN 'growth_check' THEN EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid)
        {$extraCase}
        ELSE false END;

    IF detail_count <> 1 OR NOT matching_detail THEN
        RAISE EXCEPTION 'Activity % must have exactly one detail matching its type %', activity_uuid, activity_record.code
            USING ERRCODE = '23514', CONSTRAINT = 'farm_activities_one_matching_detail_check';
    END IF;
    IF activity_record.code = 'harvest' AND activity_record.planting_cycle_id IS NOT NULL THEN
        SELECT planted_at INTO planting_date FROM planting_cycles WHERE id = activity_record.planting_cycle_id;
        IF activity_record.activity_date::date < planting_date THEN
            RAISE EXCEPTION 'Harvest activity % precedes planting date', activity_uuid
                USING ERRCODE = '23514', CONSTRAINT = 'harvest_activity_date_order_check';
        END IF;
    END IF;
END;
\$\$;
SQL;
    }
};

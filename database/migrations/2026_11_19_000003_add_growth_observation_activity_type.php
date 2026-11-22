<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared($this->detailFunction(true));

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_code_allowed');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('land_cleaning', 'soil_preparation', 'base_fertilization', 'planting', 'maintenance', 'growth_observation', 'harvest', 'incident', 'other', 'block_mapping'))");

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_detail_key_check');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_detail_key_check CHECK (detail_key IS NULL OR detail_key IN ('planting', 'fertilization', 'growth_check', 'incident', 'harvest', 'block_mapping', 'land_cleaning', 'other'))");

        $values = [
            'parent_id' => null,
            'name' => 'Pengamatan Pertumbuhan',
            'description' => 'Pencatatan pengamatan fase pertumbuhan tanaman pada subblok.',
            'detail_key' => 'growth_check',
            'is_selectable' => false,
            'sort_order' => 45,
            'is_active' => true,
        ];

        $growthId = DB::table('activity_types')->where('code', 'growth_observation')->value('id');
        if ($growthId === null) {
            $growthId = Str::uuid()->toString();
            DB::table('activity_types')->insert([
                'id' => $growthId,
                'code' => 'growth_observation',
                ...$values,
            ]);
        } else {
            DB::table('activity_types')->where('id', $growthId)->update($values);
        }

        DB::table('farm_activities')
            ->whereIn('planting_cycle_task_id', DB::table('planting_cycle_tasks')
                ->where('activity_category', 'growth_observation')
                ->select('id'))
            ->whereExists(fn (Builder $query) => $query->from('plant_observations')->whereColumn('plant_observations.activity_id', 'farm_activities.id'))
            ->update(['activity_type_id' => $growthId]);
    }

    public function down(): void
    {
        $growthId = DB::table('activity_types')->where('code', 'growth_observation')->value('id');
        $maintenanceId = DB::table('activity_types')->where('code', 'maintenance')->value('id');

        if ($growthId !== null && $maintenanceId !== null) {
            DB::table('farm_activities')
                ->where('activity_type_id', $growthId)
                ->update(['activity_type_id' => $maintenanceId]);
        }

        DB::table('activity_types')->where('code', 'growth_observation')->delete();

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_detail_key_check');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_detail_key_check CHECK (detail_key IS NULL OR detail_key IN ('planting', 'fertilization', 'incident', 'harvest', 'block_mapping', 'land_cleaning', 'other'))");

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_code_allowed');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('land_cleaning', 'soil_preparation', 'base_fertilization', 'planting', 'maintenance', 'harvest', 'incident', 'other', 'block_mapping'))");

        DB::unprepared($this->detailFunction(false));
    }

    /** @return literal-string */
    private function detailFunction(bool $includeGrowthCheck): string
    {
        $head = <<<'SQL'
CREATE OR REPLACE FUNCTION farmer_ic_check_activity_detail(activity_uuid uuid)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    activity_record record;
    detail_count integer;
    detail_key text;
    matching_detail boolean;
    planting_date date;
BEGIN
    SELECT a.planting_cycle_id, a.activity_date, t.code, t.detail_key
    INTO activity_record
    FROM farm_activities a JOIN activity_types t ON t.id = a.activity_type_id
    WHERE a.id = activity_uuid;
    IF NOT FOUND THEN RETURN; END IF;

    detail_key := COALESCE(activity_record.detail_key, CASE activity_record.code
        WHEN 'planting' THEN 'planting'
        WHEN 'base_fertilization' THEN 'fertilization'
        WHEN 'growth_observation' THEN 'growth_check'
        WHEN 'incident' THEN 'incident'
        WHEN 'harvest' THEN 'harvest'
        WHEN 'block_mapping' THEN 'block_mapping'
        WHEN 'land_cleaning' THEN 'land_cleaning'
        WHEN 'other' THEN 'other'
        ELSE NULL
    END);

    SELECT
        (EXISTS (SELECT 1 FROM planting_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM fertilization_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM irrigation_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM pesticide_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM incident_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM harvest_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM block_mapping_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM land_cleaning_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM loosening_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM weeding_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM bed_preparation_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM liming_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM other_details WHERE activity_id = activity_uuid))::int
    INTO detail_count;

    IF detail_key IS NULL THEN
        IF activity_record.code = 'soil_preparation' THEN
            IF detail_count > 1 OR (detail_count = 1 AND NOT (
                EXISTS (SELECT 1 FROM loosening_details WHERE activity_id = activity_uuid)
                OR EXISTS (SELECT 1 FROM bed_preparation_details WHERE activity_id = activity_uuid)
                OR EXISTS (SELECT 1 FROM liming_details WHERE activity_id = activity_uuid)
            )) THEN
                RAISE EXCEPTION 'Activity % has incompatible typed detail for soil_preparation', activity_uuid
                    USING ERRCODE = '23514', CONSTRAINT = 'farm_activities_one_matching_detail_check';
            END IF;
        ELSIF activity_record.code = 'maintenance' THEN
            IF detail_count > 1 OR (detail_count = 1 AND NOT (
                EXISTS (SELECT 1 FROM irrigation_details WHERE activity_id = activity_uuid)
                OR EXISTS (SELECT 1 FROM weeding_details WHERE activity_id = activity_uuid)
                OR EXISTS (SELECT 1 FROM fertilization_details WHERE activity_id = activity_uuid)
                OR EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid)
                OR EXISTS (SELECT 1 FROM pesticide_details WHERE activity_id = activity_uuid)
            )) THEN
                RAISE EXCEPTION 'Activity % has incompatible typed detail for maintenance', activity_uuid
                    USING ERRCODE = '23514', CONSTRAINT = 'farm_activities_one_matching_detail_check';
            END IF;
        ELSIF detail_count <> 0 THEN
            RAISE EXCEPTION 'Activity % must not have a typed detail for category %', activity_uuid, activity_record.code
                USING ERRCODE = '23514', CONSTRAINT = 'farm_activities_one_matching_detail_check';
        END IF;
    ELSE
        matching_detail := CASE detail_key
            WHEN 'planting' THEN EXISTS (SELECT 1 FROM planting_details WHERE activity_id = activity_uuid)
            WHEN 'fertilization' THEN EXISTS (SELECT 1 FROM fertilization_details WHERE activity_id = activity_uuid)
SQL;

        $growthBranch = $includeGrowthCheck
            ? "\n            WHEN 'growth_check' THEN EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid)"
            : '';

        $tail = <<<'SQL'

            WHEN 'incident' THEN EXISTS (SELECT 1 FROM incident_details WHERE activity_id = activity_uuid)
            WHEN 'harvest' THEN EXISTS (SELECT 1 FROM harvest_details WHERE activity_id = activity_uuid)
            WHEN 'block_mapping' THEN EXISTS (SELECT 1 FROM block_mapping_details WHERE activity_id = activity_uuid)
            WHEN 'land_cleaning' THEN EXISTS (SELECT 1 FROM land_cleaning_details WHERE activity_id = activity_uuid)
            WHEN 'other' THEN EXISTS (SELECT 1 FROM other_details WHERE activity_id = activity_uuid)
            ELSE false
        END;

        IF detail_count <> 1 OR NOT matching_detail THEN
            RAISE EXCEPTION 'Activity % must have exactly one detail matching detail type %', activity_uuid, detail_key
                USING ERRCODE = '23514', CONSTRAINT = 'farm_activities_one_matching_detail_check';
        END IF;
    END IF;

    IF activity_record.code = 'harvest' AND activity_record.planting_cycle_id IS NOT NULL THEN
        SELECT planted_at INTO planting_date FROM planting_cycles WHERE id = activity_record.planting_cycle_id;
        IF activity_record.activity_date::date < planting_date THEN
            RAISE EXCEPTION 'Harvest activity % precedes planting date', activity_uuid
                USING ERRCODE = '23514', CONSTRAINT = 'harvest_activity_date_order_check';
        END IF;
    END IF;
END;
$$;
SQL;

        return $head.$growthBranch.$tail;
    }
};

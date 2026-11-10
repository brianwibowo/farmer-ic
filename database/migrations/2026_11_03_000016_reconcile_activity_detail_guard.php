<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared($this->detailFunction());

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_code_allowed');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('land_cleaning', 'soil_preparation', 'loosening', 'beds', 'liming', 'base_fertilization', 'planting', 'maintenance', 'irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control', 'harvest', 'incident', 'other', 'block_mapping'))");
    }

    public function down(): void
    {
        DB::unprepared($this->previousDetailFunction());
    }

    /** @return literal-string */
    private function detailFunction(): string
    {
        return <<<'SQL'
CREATE OR REPLACE FUNCTION farmer_ic_check_activity_detail(activity_uuid uuid)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    activity_record record;
    detail_count integer;
    detail_key text;
    matching_detail boolean;
    planting_date date;
BEGIN
    SELECT a.planting_cycle_id, a.activity_date, a.planting_cycle_task_id, a.task_details, t.code, t.detail_key
    INTO activity_record
    FROM farm_activities a JOIN activity_types t ON t.id = a.activity_type_id
    WHERE a.id = activity_uuid;
    IF NOT FOUND THEN RETURN; END IF;

    detail_key := COALESCE(activity_record.detail_key, CASE activity_record.code
        WHEN 'planting' THEN 'planting'
        WHEN 'irrigation' THEN 'irrigation'
        WHEN 'fertilization' THEN 'fertilization'
        WHEN 'follow_up_fertilization' THEN 'fertilization'
        WHEN 'growth_check' THEN 'growth_check'
        WHEN 'pesticide' THEN 'pesticide'
        WHEN 'pest_control' THEN 'pesticide'
        WHEN 'incident' THEN 'incident'
        WHEN 'harvest' THEN 'harvest'
        WHEN 'block_mapping' THEN 'block_mapping'
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
        (EXISTS (SELECT 1 FROM block_mapping_details WHERE activity_id = activity_uuid))::int
    INTO detail_count;

    IF activity_record.code = 'weeding' THEN
        IF activity_record.planting_cycle_task_id IS NULL
            OR activity_record.task_details IS NULL
            OR jsonb_typeof(activity_record.task_details) <> 'object'
            OR detail_count <> 0 THEN
            RAISE EXCEPTION 'Weeding activity % requires a task-linked JSON report and no typed detail', activity_uuid
                USING ERRCODE = '23514', CONSTRAINT = 'farm_activities_one_matching_detail_check';
        END IF;
    ELSIF detail_key IS NULL THEN
        IF detail_count <> 0 THEN
            RAISE EXCEPTION 'Activity % must not have a typed detail for category %', activity_uuid, activity_record.code
                USING ERRCODE = '23514', CONSTRAINT = 'farm_activities_one_matching_detail_check';
        END IF;
    ELSE
        matching_detail := CASE detail_key
            WHEN 'planting' THEN EXISTS (SELECT 1 FROM planting_details WHERE activity_id = activity_uuid)
            WHEN 'fertilization' THEN EXISTS (SELECT 1 FROM fertilization_details WHERE activity_id = activity_uuid)
            WHEN 'irrigation' THEN EXISTS (SELECT 1 FROM irrigation_details WHERE activity_id = activity_uuid)
            WHEN 'pesticide' THEN EXISTS (SELECT 1 FROM pesticide_details WHERE activity_id = activity_uuid)
            WHEN 'incident' THEN EXISTS (SELECT 1 FROM incident_details WHERE activity_id = activity_uuid)
            WHEN 'harvest' THEN EXISTS (SELECT 1 FROM harvest_details WHERE activity_id = activity_uuid)
            WHEN 'growth_check' THEN EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid)
            WHEN 'block_mapping' THEN EXISTS (SELECT 1 FROM block_mapping_details WHERE activity_id = activity_uuid)
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
    }

    /** @return literal-string */
    private function previousDetailFunction(): string
    {
        return <<<'SQL'
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
        WHEN 'irrigation' THEN 'irrigation'
        WHEN 'fertilization' THEN 'fertilization'
        WHEN 'follow_up_fertilization' THEN 'fertilization'
        WHEN 'growth_check' THEN 'growth_check'
        WHEN 'pesticide' THEN 'pesticide'
        WHEN 'pest_control' THEN 'pesticide'
        WHEN 'incident' THEN 'incident'
        WHEN 'harvest' THEN 'harvest'
        WHEN 'block_mapping' THEN 'block_mapping'
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
        (EXISTS (SELECT 1 FROM block_mapping_details WHERE activity_id = activity_uuid))::int
    INTO detail_count;

    IF detail_key IS NULL THEN
        IF detail_count <> 0 THEN
            RAISE EXCEPTION 'Activity % must not have a typed detail for category %', activity_uuid, activity_record.code
                USING ERRCODE = '23514', CONSTRAINT = 'farm_activities_one_matching_detail_check';
        END IF;
    ELSE
        matching_detail := CASE detail_key
            WHEN 'planting' THEN EXISTS (SELECT 1 FROM planting_details WHERE activity_id = activity_uuid)
            WHEN 'fertilization' THEN EXISTS (SELECT 1 FROM fertilization_details WHERE activity_id = activity_uuid)
            WHEN 'irrigation' THEN EXISTS (SELECT 1 FROM irrigation_details WHERE activity_id = activity_uuid)
            WHEN 'pesticide' THEN EXISTS (SELECT 1 FROM pesticide_details WHERE activity_id = activity_uuid)
            WHEN 'incident' THEN EXISTS (SELECT 1 FROM incident_details WHERE activity_id = activity_uuid)
            WHEN 'harvest' THEN EXISTS (SELECT 1 FROM harvest_details WHERE activity_id = activity_uuid)
            WHEN 'growth_check' THEN EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid)
            WHEN 'block_mapping' THEN EXISTS (SELECT 1 FROM block_mapping_details WHERE activity_id = activity_uuid)
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
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const LEGACY_SOIL_CODES = ['loosening', 'beds', 'liming'];

    private const LEGACY_MAINTENANCE_CODES = ['irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control'];

    public function up(): void
    {
        // Install the simplified detail guard before remapping historical activities.
        DB::unprepared($this->simplifiedDetailFunction());

        $this->upsertType('land_cleaning', 'Pembersihan Lahan', null, 'land_cleaning', true, 10, 'Pembersihan gulma, sisa tanaman, dan material pengganggu sebelum pengolahan tanah.');
        $this->upsertType('soil_preparation', 'Pengolahan Tanah', null, null, true, 20, 'Pencatatan pengolahan tanah pada blok lahan.');
        $this->upsertType('base_fertilization', 'Pemupukan Kandang', null, 'fertilization', true, 24, 'Pencatatan pemupukan kandang sebelum penanaman.');
        $this->upsertType('planting', 'Penanaman', null, 'planting', true, 30, 'Pencatatan proses penanaman.');
        $this->upsertType('maintenance', 'Perawatan', null, null, true, 40, 'Pencatatan perawatan tanaman pada subblok.');
        $this->upsertType('harvest', 'Panen', null, 'harvest', true, 50, 'Pencatatan hasil panen.');
        $this->upsertType('incident', 'Pelaporan Insiden', null, 'incident', true, 60);
        $this->upsertType('other', 'Lainnya', null, 'other', true, 70);
        $this->upsertType('block_mapping', 'Pemetaan Blok', null, 'block_mapping', false, 99, 'Pengukuran dimensi blok dan pencatatan rencana jarak tanam serta baris per bedeng.');

        $soilId = DB::table('activity_types')->where('code', 'soil_preparation')->value('id');
        $maintenanceId = DB::table('activity_types')->where('code', 'maintenance')->value('id');

        if ($soilId !== null) {
            DB::table('farm_activities')
                ->whereIn('activity_type_id', DB::table('activity_types')->whereIn('code', self::LEGACY_SOIL_CODES)->select('id'))
                ->update(['activity_type_id' => $soilId]);
        }

        if ($maintenanceId !== null) {
            DB::table('farm_activities')
                ->whereIn('activity_type_id', DB::table('activity_types')->whereIn('code', self::LEGACY_MAINTENANCE_CODES)->select('id'))
                ->update(['activity_type_id' => $maintenanceId]);
        }

        DB::table('activity_types')
            ->whereIn('code', [...self::LEGACY_SOIL_CODES, ...self::LEGACY_MAINTENANCE_CODES])
            ->delete();

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_code_allowed');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('land_cleaning', 'soil_preparation', 'base_fertilization', 'planting', 'maintenance', 'harvest', 'incident', 'other', 'block_mapping'))");

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_detail_key_check');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_detail_key_check CHECK (detail_key IS NULL OR detail_key IN ('planting', 'fertilization', 'incident', 'harvest', 'block_mapping', 'land_cleaning', 'other'))");

        DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT IF EXISTS sop_template_tasks_values_check');
        DB::table('sop_template_tasks')->whereNotNull('activity_subcategory')->update(['activity_subcategory' => null]);
        DB::statement(<<<'SQL'
ALTER TABLE sop_template_tasks ADD CONSTRAINT sop_template_tasks_values_check CHECK (
    btrim(name) <> ''
    AND activity_category IN ('maintenance', 'harvest')
    AND activity_subcategory IS NULL
    AND tolerance_days >= 0
)
SQL);

        DB::statement('ALTER TABLE planting_cycle_tasks DROP CONSTRAINT IF EXISTS planting_cycle_tasks_values_check');
        DB::table('planting_cycle_tasks')->whereNotNull('activity_subcategory')->update(['activity_subcategory' => null]);
        DB::statement(<<<'SQL'
ALTER TABLE planting_cycle_tasks ADD CONSTRAINT planting_cycle_tasks_values_check CHECK (
    btrim(name) <> ''
    AND activity_category IN ('maintenance', 'harvest')
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

    public function down(): void
    {
        DB::statement('ALTER TABLE planting_cycle_tasks DROP CONSTRAINT IF EXISTS planting_cycle_tasks_values_check');
        DB::table('planting_cycle_tasks')
            ->where('activity_category', 'maintenance')
            ->whereNull('activity_subcategory')
            ->update(['activity_subcategory' => 'irrigation']);
        DB::statement(<<<'SQL'
ALTER TABLE planting_cycle_tasks ADD CONSTRAINT planting_cycle_tasks_values_check CHECK (
    btrim(name) <> ''
    AND activity_category IN ('maintenance', 'harvest')
    AND (
        (activity_category = 'maintenance' AND activity_subcategory IN ('irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control'))
        OR (activity_category = 'harvest' AND activity_subcategory IS NULL)
    )
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

        DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT IF EXISTS sop_template_tasks_values_check');
        DB::table('sop_template_tasks')
            ->where('activity_category', 'maintenance')
            ->whereNull('activity_subcategory')
            ->update(['activity_subcategory' => 'irrigation']);
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

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_detail_key_check');
        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_code_allowed');

        $this->upsertType('soil_preparation', 'Pengolahan Tanah', null, null, false, 20, 'Pembajakan, penggemburan, dan pembentukan bedengan.');
        $this->upsertType('loosening', 'Penggemburan', 'soil_preparation', 'loosening', true, 21);
        $this->upsertType('beds', 'Pembuatan Bedengan', 'soil_preparation', 'bed_preparation', true, 22);
        $this->upsertType('liming', 'Penebaran Dolomit', 'soil_preparation', 'liming', true, 23);
        $this->upsertType('base_fertilization', 'Pemupukan Dasar', null, 'fertilization', true, 24);
        $this->upsertType('maintenance', 'Perawatan', null, null, false, 40);
        $this->upsertType('irrigation', 'Pengairan', 'maintenance', 'irrigation', true, 41, 'Pencatatan pengairan lahan.');
        $this->upsertType('weeding', 'Pendangiran', 'maintenance', 'weeding', true, 42);
        $this->upsertType('follow_up_fertilization', 'Pemupukan susulan', 'maintenance', 'fertilization', true, 43, 'Pencatatan pemupukan tanaman.');
        $this->upsertType('growth_check', 'Pengecekan pertumbuhan', 'maintenance', 'growth_check', true, 44);
        $this->upsertType('pest_control', 'Pengendalian hama dan penyakit', 'maintenance', 'pesticide', true, 45, 'Pencatatan aplikasi pengendalian hama dan penyakit.');

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('land_cleaning', 'soil_preparation', 'loosening', 'beds', 'liming', 'base_fertilization', 'planting', 'maintenance', 'irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control', 'harvest', 'incident', 'other', 'block_mapping'))");
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_detail_key_check CHECK (detail_key IS NULL OR detail_key IN ('planting', 'irrigation', 'fertilization', 'growth_check', 'pesticide', 'incident', 'harvest', 'block_mapping', 'land_cleaning', 'loosening', 'bed_preparation', 'liming', 'weeding', 'other'))");

        DB::unprepared($this->previousDetailFunction());
    }

    private function upsertType(
        string $code,
        string $name,
        ?string $parentCode,
        ?string $detailKey,
        bool $selectable,
        int $sortOrder,
        ?string $description = null,
    ): void {
        $parentId = $parentCode === null
            ? null
            : DB::table('activity_types')->where('code', $parentCode)->value('id');

        $values = [
            'parent_id' => $parentId,
            'name' => $name,
            'description' => $description,
            'detail_key' => $detailKey,
            'is_selectable' => $selectable,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ];

        $existing = DB::table('activity_types')->where('code', $code)->first();
        if ($existing === null) {
            DB::table('activity_types')->insert([
                'id' => Str::uuid()->toString(),
                'code' => $code,
                ...$values,
            ]);
        } else {
            DB::table('activity_types')->where('id', $existing->id)->update($values);
        }
    }

    /** @return literal-string */
    private function simplifiedDetailFunction(): string
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
        WHEN 'base_fertilization' THEN 'fertilization'
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
        WHEN 'base_fertilization' THEN 'fertilization'
        WHEN 'growth_check' THEN 'growth_check'
        WHEN 'pesticide' THEN 'pesticide'
        WHEN 'pest_control' THEN 'pesticide'
        WHEN 'incident' THEN 'incident'
        WHEN 'harvest' THEN 'harvest'
        WHEN 'block_mapping' THEN 'block_mapping'
        WHEN 'land_cleaning' THEN 'land_cleaning'
        WHEN 'loosening' THEN 'loosening'
        WHEN 'beds' THEN 'bed_preparation'
        WHEN 'liming' THEN 'liming'
        WHEN 'weeding' THEN 'weeding'
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
            WHEN 'land_cleaning' THEN EXISTS (SELECT 1 FROM land_cleaning_details WHERE activity_id = activity_uuid)
            WHEN 'loosening' THEN EXISTS (SELECT 1 FROM loosening_details WHERE activity_id = activity_uuid)
            WHEN 'weeding' THEN EXISTS (SELECT 1 FROM weeding_details WHERE activity_id = activity_uuid)
            WHEN 'bed_preparation' THEN EXISTS (SELECT 1 FROM bed_preparation_details WHERE activity_id = activity_uuid)
            WHEN 'liming' THEN EXISTS (SELECT 1 FROM liming_details WHERE activity_id = activity_uuid)
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
    }
};

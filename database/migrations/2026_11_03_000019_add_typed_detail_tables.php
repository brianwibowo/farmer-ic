<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The old guard rejects typed rows for categories whose detail_key is
        // still null. Allow the backfill, then install the final guard once the
        // taxonomy and every existing activity have matching typed storage.
        DB::unprepared($this->permissiveFunction());

        Schema::create('land_cleaning_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->decimal('area_ha', 12, 4)->nullable();
            $table->string('method', 50)->nullable();
            $table->string('product_name', 150)->nullable();
            $table->string('dosage_per_tank', 100)->nullable();
            $table->decimal('water_liters', 8, 2)->nullable();
            $table->integer('tank_count')->nullable();
            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        $this->addDetailConstraints('land_cleaning_details', ['area_ha', 'water_liters', 'tank_count']);

        Schema::create('loosening_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->decimal('area_ha', 12, 4)->nullable();
            $table->string('method', 50)->nullable();
            $table->decimal('depth_cm', 8, 2)->nullable();
            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        $this->addDetailConstraints('loosening_details', ['area_ha', 'depth_cm']);

        Schema::create('weeding_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->decimal('area_ha', 12, 4)->nullable();
            $table->string('method', 50)->nullable();
            $table->decimal('depth_cm', 8, 2)->nullable();
            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        $this->addDetailConstraints('weeding_details', ['area_ha', 'depth_cm']);

        Schema::create('bed_preparation_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->integer('bed_count')->nullable();
            $table->decimal('length_m', 10, 3)->nullable();
            $table->decimal('width_m', 10, 3)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->decimal('bed_spacing_cm', 8, 2)->nullable();
            $table->decimal('area_ha', 12, 4)->nullable();
            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        $this->addDetailConstraints('bed_preparation_details', ['bed_count', 'length_m', 'width_m', 'height_cm', 'bed_spacing_cm', 'area_ha']);

        Schema::create('liming_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->decimal('area_ha', 12, 4)->nullable();
            $table->string('material_type', 100)->nullable();
            $table->string('brand_name', 150)->nullable();
            $table->decimal('quantity_value', 14, 3)->nullable();
            $table->string('quantity_unit', 20)->nullable();
            $table->decimal('sack_size_kg', 10, 3)->nullable();
            $table->string('application_method', 150)->nullable();
            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        $this->addDetailConstraints('liming_details', ['area_ha', 'quantity_value', 'sack_size_kg']);

        Schema::create('other_details', function (Blueprint $table): void {
            $table->uuid('activity_id')->primary();
            $table->text('description')->nullable();
            $table->decimal('area_ha', 12, 4)->nullable();
            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
        });
        $this->addDetailConstraints('other_details', ['area_ha']);

        Schema::table('fertilization_details', function (Blueprint $table): void {
            $table->decimal('area_ha', 12, 4)->nullable();
            $table->string('quantity_unit', 20)->nullable();
            $table->decimal('sack_size_kg', 10, 3)->nullable();
        });
        DB::statement('ALTER TABLE fertilization_details ADD CONSTRAINT fertilization_details_area_ha_nonnegative CHECK (area_ha IS NULL OR area_ha >= 0)');
        DB::statement('ALTER TABLE fertilization_details ADD CONSTRAINT fertilization_details_sack_size_kg_nonnegative CHECK (sack_size_kg IS NULL OR sack_size_kg >= 0)');

        $this->backfillTypedDetails();

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_detail_key_check');
        DB::table('activity_types')->whereIn('code', [
            'land_cleaning', 'loosening', 'beds', 'liming', 'base_fertilization', 'weeding', 'other',
        ])->update([
            'detail_key' => DB::raw("CASE code
                WHEN 'land_cleaning' THEN 'land_cleaning'
                WHEN 'loosening' THEN 'loosening'
                WHEN 'beds' THEN 'bed_preparation'
                WHEN 'liming' THEN 'liming'
                WHEN 'base_fertilization' THEN 'fertilization'
                WHEN 'weeding' THEN 'weeding'
                WHEN 'other' THEN 'other'
            END"),
        ]);

        // The detail_key update queued deferred activity_types triggers. Flush
        // them while the permissive guard is still active before ALTER TABLE.
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_detail_key_check CHECK (detail_key IS NULL OR detail_key IN ('planting', 'irrigation', 'fertilization', 'growth_check', 'pesticide', 'incident', 'harvest', 'block_mapping', 'land_cleaning', 'loosening', 'bed_preparation', 'liming', 'weeding', 'other'))");
        DB::unprepared($this->detailFunction());

        // Revalidate every activity affected by the detail_key change, including
        // rows whose typed details were inserted above.
        DB::statement(<<<'SQL'
SELECT farmer_ic_check_activity_detail(activity.id)
FROM farm_activities AS activity
JOIN activity_types AS type ON type.id = activity.activity_type_id
WHERE type.code IN ('land_cleaning', 'loosening', 'beds', 'liming', 'base_fertilization', 'weeding', 'other')
SQL);
    }

    public function down(): void
    {
        DB::unprepared($this->permissiveFunction());
        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT IF EXISTS activity_types_detail_key_check');
        DB::table('activity_types')->whereIn('code', [
            'land_cleaning', 'loosening', 'beds', 'liming', 'base_fertilization', 'weeding', 'other',
        ])->update(['detail_key' => null]);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_detail_key_check CHECK (detail_key IS NULL OR detail_key IN ('planting', 'irrigation', 'fertilization', 'growth_check', 'pesticide', 'incident', 'harvest', 'block_mapping'))");

        Schema::dropIfExists('other_details');
        Schema::dropIfExists('liming_details');
        Schema::dropIfExists('bed_preparation_details');
        Schema::dropIfExists('weeding_details');
        Schema::dropIfExists('loosening_details');
        Schema::dropIfExists('land_cleaning_details');

        DB::statement('ALTER TABLE fertilization_details DROP CONSTRAINT fertilization_details_area_ha_nonnegative');
        DB::statement('ALTER TABLE fertilization_details DROP CONSTRAINT fertilization_details_sack_size_kg_nonnegative');
        Schema::table('fertilization_details', fn (Blueprint $table) => $table->dropColumn(['area_ha', 'quantity_unit', 'sack_size_kg']));

        $previous = require database_path('migrations/2026_11_03_000016_reconcile_activity_detail_guard.php');
        $previous->up();
    }

    /** @param list<string> $nonnegative */
    private function addDetailConstraints(string $table, array $nonnegative): void
    {
        foreach ($nonnegative as $column) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_nonnegative CHECK ({$column} IS NULL OR {$column} >= 0)");
        }

        DB::statement("CREATE CONSTRAINT TRIGGER {$table}_detail_guard AFTER INSERT OR UPDATE OR DELETE ON {$table} DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_validate_activity_details()");
    }

    private function backfillTypedDetails(): void
    {
        // Every category receiving a non-null detail_key must already have one
        // typed row per existing activity, otherwise the final deferred guard
        // would reject historical records on their next update.
        foreach ([
            'weeding' => 'weeding_details',
            'base_fertilization' => 'fertilization_details',
            'land_cleaning' => 'land_cleaning_details',
            'loosening' => 'loosening_details',
            'beds' => 'bed_preparation_details',
            'liming' => 'liming_details',
            'other' => 'other_details',
        ] as $code => $table) {
            $stage = $code === 'base_fertilization' ? ", 'dasar'" : '';
            $stageColumn = $code === 'base_fertilization' ? ', fertilization_stage' : '';
            DB::statement(<<<SQL
INSERT INTO {$table} (activity_id{$stageColumn})
SELECT activity.id{$stage}
FROM farm_activities AS activity
JOIN activity_types AS type ON type.id = activity.activity_type_id
WHERE type.code = '{$code}'
ON CONFLICT (activity_id) DO NOTHING
SQL);
        }

        DB::statement(<<<'SQL'
UPDATE weeding_details AS detail
SET area_ha = CASE WHEN activity.task_details->>'area_ha' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'area_ha')::numeric ELSE detail.area_ha END,
    method = COALESCE(NULLIF(activity.task_details->>'method', ''), detail.method),
    depth_cm = CASE WHEN activity.task_details->>'depth_cm' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'depth_cm')::numeric ELSE detail.depth_cm END
FROM farm_activities AS activity
JOIN activity_types AS type ON type.id = activity.activity_type_id
WHERE detail.activity_id = activity.id
  AND type.code = 'weeding'
SQL);

        DB::statement(<<<'SQL'
UPDATE fertilization_details AS detail
SET area_ha = CASE WHEN activity.task_details->>'area_ha' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'area_ha')::numeric ELSE detail.area_ha END,
    fertilizer_type = COALESCE(NULLIF(activity.task_details->>'fertilizer', ''), NULLIF(activity.task_details->>'fertilizer_type', ''), detail.fertilizer_type),
    application_method = COALESCE(NULLIF(activity.task_details->>'method', ''), NULLIF(activity.task_details->>'application_method', ''), detail.application_method),
    quantity_kg = CASE WHEN activity.task_details->>'quantity_kg' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'quantity_kg')::numeric ELSE detail.quantity_kg END,
    quantity_unit = COALESCE(NULLIF(activity.task_details->>'quantity_unit', ''), detail.quantity_unit),
    sack_size_kg = CASE WHEN activity.task_details->>'sack_size_kg' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'sack_size_kg')::numeric ELSE detail.sack_size_kg END
FROM farm_activities AS activity
JOIN activity_types AS type ON type.id = activity.activity_type_id
WHERE detail.activity_id = activity.id
  AND type.code = 'base_fertilization'
SQL);

        DB::statement(<<<'SQL'
UPDATE land_cleaning_details AS detail
SET area_ha = CASE WHEN activity.task_details->>'area_ha' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'area_ha')::numeric ELSE detail.area_ha END,
    method = COALESCE(NULLIF(activity.task_details->>'method', ''), detail.method),
    product_name = COALESCE(NULLIF(activity.task_details->>'product_name', ''), NULLIF(activity.task_details->>'product', ''), detail.product_name),
    dosage_per_tank = COALESCE(NULLIF(activity.task_details->>'dosage_per_tank', ''), NULLIF(activity.task_details->>'dosage', ''), detail.dosage_per_tank),
    water_liters = CASE WHEN activity.task_details->>'water_liters' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'water_liters')::numeric ELSE detail.water_liters END,
    tank_count = CASE WHEN activity.task_details->>'tank_count' ~ '^[0-9]+$' THEN (activity.task_details->>'tank_count')::integer ELSE detail.tank_count END
FROM farm_activities AS activity
JOIN activity_types AS type ON type.id = activity.activity_type_id
WHERE detail.activity_id = activity.id
  AND type.code = 'land_cleaning'
SQL);

        DB::statement(<<<'SQL'
UPDATE loosening_details AS detail
SET area_ha = CASE WHEN activity.task_details->>'area_ha' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'area_ha')::numeric ELSE detail.area_ha END,
    method = COALESCE(NULLIF(activity.task_details->>'method', ''), detail.method),
    depth_cm = CASE WHEN activity.task_details->>'depth_cm' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'depth_cm')::numeric ELSE detail.depth_cm END
FROM farm_activities AS activity
JOIN activity_types AS type ON type.id = activity.activity_type_id
WHERE detail.activity_id = activity.id
  AND type.code = 'loosening'
SQL);

        DB::statement(<<<'SQL'
UPDATE bed_preparation_details AS detail
SET bed_count = CASE WHEN activity.task_details->>'bed_count' ~ '^[0-9]+$' THEN (activity.task_details->>'bed_count')::integer ELSE detail.bed_count END,
    length_m = CASE WHEN activity.task_details->>'length_m' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'length_m')::numeric ELSE detail.length_m END,
    width_m = CASE WHEN activity.task_details->>'width_m' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'width_m')::numeric ELSE detail.width_m END,
    height_cm = CASE WHEN activity.task_details->>'height_cm' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'height_cm')::numeric ELSE detail.height_cm END,
    bed_spacing_cm = CASE WHEN activity.task_details->>'bed_spacing_cm' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'bed_spacing_cm')::numeric ELSE detail.bed_spacing_cm END,
    area_ha = CASE WHEN activity.task_details->>'area_ha' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'area_ha')::numeric ELSE detail.area_ha END
FROM farm_activities AS activity
JOIN activity_types AS type ON type.id = activity.activity_type_id
WHERE detail.activity_id = activity.id
  AND type.code = 'beds'
SQL);

        DB::statement(<<<'SQL'
UPDATE liming_details AS detail
SET area_ha = CASE WHEN activity.task_details->>'area_ha' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'area_ha')::numeric ELSE detail.area_ha END,
    material_type = COALESCE(NULLIF(activity.task_details->>'material_type', ''), detail.material_type),
    brand_name = COALESCE(NULLIF(activity.task_details->>'brand_name', ''), detail.brand_name),
    quantity_value = CASE WHEN activity.task_details->>'quantity_value' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'quantity_value')::numeric ELSE detail.quantity_value END,
    quantity_unit = COALESCE(NULLIF(activity.task_details->>'quantity_unit', ''), detail.quantity_unit),
    sack_size_kg = CASE WHEN activity.task_details->>'sack_size_kg' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'sack_size_kg')::numeric ELSE detail.sack_size_kg END,
    application_method = COALESCE(NULLIF(activity.task_details->>'application_method', ''), NULLIF(activity.task_details->>'method', ''), detail.application_method)
FROM farm_activities AS activity
JOIN activity_types AS type ON type.id = activity.activity_type_id
WHERE detail.activity_id = activity.id
  AND type.code = 'liming'
SQL);

        DB::statement(<<<'SQL'
UPDATE other_details AS detail
SET description = COALESCE(NULLIF(activity.task_details->>'description', ''), activity.description, detail.description),
    area_ha = CASE WHEN activity.task_details->>'area_ha' ~ '^[0-9]+(\.[0-9]+)?$' THEN (activity.task_details->>'area_ha')::numeric ELSE detail.area_ha END
FROM farm_activities AS activity
JOIN activity_types AS type ON type.id = activity.activity_type_id
WHERE detail.activity_id = activity.id
  AND type.code = 'other'
SQL);
    }

    /** @return literal-string */
    private function permissiveFunction(): string
    {
        return <<<'SQL'
CREATE OR REPLACE FUNCTION farmer_ic_check_activity_detail(activity_uuid uuid)
RETURNS void LANGUAGE plpgsql AS $$
BEGIN
    RETURN;
END;
$$;
SQL;
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table): void {
            $table->uuid('parent_id')->nullable()->index();
            $table->string('detail_key', 50)->nullable();
            $table->boolean('is_selectable')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::table('activity_types', function (Blueprint $table): void {
            $table->foreign('parent_id', 'activity_types_parent_id_foreign')
                ->references('id')->on('activity_types')->restrictOnDelete();
        });

        // Replace the detail guard before changing codes so deferred checks use
        // the new detail_key-aware function.
        DB::unprepared($this->detailFunction());

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT activity_types_code_allowed');

        // Preserve the existing type IDs so farm_activities keep their FK.
        DB::table('activity_types')->where('code', 'fertilization')->update([
            'code' => 'follow_up_fertilization',
            'name' => 'Pemupukan susulan',
        ]);
        DB::table('activity_types')->where('code', 'pesticide')->update([
            'code' => 'pest_control',
            'name' => 'Pengendalian hama dan penyakit',
        ]);

        $this->upsertType('land_cleaning', 'Pembersihan Lahan', null, null, true, 10, 'Pembersihan gulma, sisa tanaman, dan material pengganggu sebelum pengolahan tanah.');
        $this->upsertType('soil_preparation', 'Pengolahan Tanah', null, null, false, 20, 'Pembajakan, penggemburan, dan pembentukan bedengan.');
        $this->upsertType('loosening', 'Penggemburan', 'soil_preparation', null, true, 21);
        $this->upsertType('beds', 'Pembuatan Bedengan', 'soil_preparation', null, true, 22);
        $this->upsertType('liming', 'Penebaran Dolomit', 'soil_preparation', null, true, 23);
        $this->upsertType('base_fertilization', 'Pemupukan Dasar', 'soil_preparation', null, true, 24);
        $this->upsertType('planting', 'Penanaman', null, 'planting', true, 30, 'Pencatatan proses penanaman.');
        $this->upsertType('maintenance', 'Perawatan', null, null, false, 40);
        $this->upsertType('irrigation', 'Pengairan', 'maintenance', 'irrigation', true, 41, 'Pencatatan pengairan lahan.');
        $this->upsertType('weeding', 'Pendangiran', 'maintenance', null, true, 42);
        $this->upsertType('follow_up_fertilization', 'Pemupukan susulan', 'maintenance', 'fertilization', true, 43, 'Pencatatan pemupukan tanaman.');
        $this->upsertType('growth_check', 'Pengecekan pertumbuhan', 'maintenance', 'growth_check', true, 44);
        $this->upsertType('pest_control', 'Pengendalian hama dan penyakit', 'maintenance', 'pesticide', true, 45, 'Pencatatan aplikasi pengendalian hama dan penyakit.');
        $this->upsertType('harvest', 'Panen', null, 'harvest', true, 50, 'Pencatatan hasil panen.');
        $this->upsertType('incident', 'Pelaporan Insiden', null, 'incident', true, 60);
        $this->upsertType('other', 'Lainnya', null, null, true, 70);
        $this->upsertType('block_mapping', 'Pemetaan Blok', null, 'block_mapping', false, 99, 'Pengukuran dimensi blok dan pencatatan rencana jarak tanam serta baris per bedeng.');

        // Flush the deferred activity-detail checks before altering the table.
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('land_cleaning', 'soil_preparation', 'loosening', 'beds', 'liming', 'base_fertilization', 'planting', 'maintenance', 'irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control', 'harvest', 'incident', 'other', 'block_mapping'))");
        DB::statement('ALTER TABLE activity_types ADD CONSTRAINT activity_types_parent_not_self_check CHECK (parent_id IS NULL OR parent_id <> id)');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_detail_key_check CHECK (detail_key IS NULL OR detail_key IN ('planting', 'irrigation', 'fertilization', 'growth_check', 'pesticide', 'incident', 'harvest', 'block_mapping'))");

    }

    public function down(): void
    {
        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT activity_types_detail_key_check');
        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT activity_types_parent_not_self_check');
        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT activity_types_code_allowed');

        $newCodes = ['land_cleaning', 'soil_preparation', 'loosening', 'beds', 'liming', 'base_fertilization', 'weeding', 'maintenance', 'other'];
        $newTypeIds = DB::table('activity_types')->whereIn('code', $newCodes)->pluck('id');
        if (DB::table('farm_activities')->whereIn('activity_type_id', $newTypeIds)->exists()) {
            throw new LogicException('Activities reference the new taxonomy; reassign them before rolling back this migration.');
        }

        DB::table('activity_types')->update(['parent_id' => null]);
        DB::table('activity_types')->whereIn('code', ['land_cleaning', 'soil_preparation', 'loosening', 'beds', 'liming', 'base_fertilization', 'weeding'])->delete();
        DB::table('activity_types')->whereIn('code', ['maintenance', 'other'])->delete();

        DB::table('activity_types')->where('code', 'follow_up_fertilization')->update([
            'code' => 'fertilization',
            'name' => 'Pemupukan',
        ]);
        DB::table('activity_types')->where('code', 'pest_control')->update([
            'code' => 'pesticide',
            'name' => 'Pengendalian Hama',
        ]);

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        DB::statement('ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN (\'planting\', \'fertilization\', \'irrigation\', \'growth_check\', \'pesticide\', \'incident\', \'harvest\', \'block_mapping\', \'land_cleaning\', \'soil_preparation\'))');

        Schema::table('activity_types', function (Blueprint $table): void {
            $table->dropForeign('activity_types_parent_id_foreign');
            $table->dropColumn(['parent_id', 'detail_key', 'is_selectable', 'sort_order', 'is_active']);
        });

        DB::unprepared($this->legacyDetailFunction());
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

    /** @return literal-string */
    private function legacyDetailFunction(): string
    {
        return <<<'SQL'
CREATE OR REPLACE FUNCTION farmer_ic_check_activity_detail(activity_uuid uuid)
RETURNS void LANGUAGE plpgsql AS $$
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
        (EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM block_mapping_details WHERE activity_id = activity_uuid))::int
    INTO detail_count;

    matching_detail := CASE activity_record.code
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
$$;
SQL;
    }
};

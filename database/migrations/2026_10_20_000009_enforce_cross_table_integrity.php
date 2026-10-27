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
            throw new LogicException('Farmer IC requires PostgreSQL. Run php artisan migrate --database=pgsql.');
        }

        // PostgreSQL requires these candidate keys for the composite FKs below.
        // The extra keys enforce consistency without adding columns to the DBML.
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->unique(['id', 'farm_id'], 'planting_cycles_id_farm_unique');
        });
        Schema::table('farm_activities', function (Blueprint $table): void {
            $table->unique(['id', 'planting_cycle_id'], 'farm_activities_id_cycle_unique');
        });
        Schema::table('plant_observations', function (Blueprint $table): void {
            $table->unique(['id', 'planting_cycle_id'], 'plant_observations_id_cycle_unique');
        });
        Schema::table('financial_categories', function (Blueprint $table): void {
            $table->unique(['id', 'transaction_type'], 'financial_categories_id_type_unique');
        });

        foreach ($this->compositeForeignKeys() as [$table, $name, $columns, $parent, $references]) {
            // MATCH SIMPLE preserves the DBML's nullable associations. NO ACTION
            // is deferred so individual SET NULL FKs can complete on deletion.
            DB::statement(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE NO ACTION DEFERRABLE INITIALLY DEFERRED',
                $table, $name, $columns, $parent, $references,
            ));
        }

        // migrate:fresh drops tables and triggers, but PostgreSQL functions remain.
        // Replace the same signatures so rebuilding the schema works repeatedly.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION farmer_ic_check_activity_detail(activity_uuid uuid)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    activity_record record;
    detail_count integer;
    matching_detail boolean;
    planting_date date;
BEGIN
    SELECT a.planting_cycle_id, a.activity_date, t.code
    INTO activity_record
    FROM farm_activities a JOIN activity_types t ON t.id = a.activity_type_id
    WHERE a.id = activity_uuid;

    IF NOT FOUND THEN
        RETURN;
    END IF;

    SELECT
        (EXISTS (SELECT 1 FROM planting_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM fertilization_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM irrigation_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM pesticide_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM incident_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM harvest_details WHERE activity_id = activity_uuid))::int +
        (EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid))::int
    INTO detail_count;

    matching_detail := CASE activity_record.code
        WHEN 'planting' THEN EXISTS (SELECT 1 FROM planting_details WHERE activity_id = activity_uuid)
        WHEN 'fertilization' THEN EXISTS (SELECT 1 FROM fertilization_details WHERE activity_id = activity_uuid)
        WHEN 'irrigation' THEN EXISTS (SELECT 1 FROM irrigation_details WHERE activity_id = activity_uuid)
        WHEN 'pesticide' THEN EXISTS (SELECT 1 FROM pesticide_details WHERE activity_id = activity_uuid)
        WHEN 'incident' THEN EXISTS (SELECT 1 FROM incident_details WHERE activity_id = activity_uuid)
        WHEN 'harvest' THEN EXISTS (SELECT 1 FROM harvest_details WHERE activity_id = activity_uuid)
        WHEN 'growth_check' THEN EXISTS (SELECT 1 FROM plant_observations WHERE activity_id = activity_uuid)
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

CREATE OR REPLACE FUNCTION farmer_ic_validate_activity_details()
RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    related_activity uuid;
BEGIN
    IF TG_TABLE_NAME = 'activity_types' THEN
        FOR related_activity IN SELECT id FROM farm_activities WHERE activity_type_id = NEW.id LOOP
            PERFORM farmer_ic_check_activity_detail(related_activity);
        END LOOP;
    ELSIF TG_TABLE_NAME = 'planting_cycles' THEN
        FOR related_activity IN SELECT id FROM farm_activities WHERE planting_cycle_id = NEW.id LOOP
            PERFORM farmer_ic_check_activity_detail(related_activity);
        END LOOP;
    ELSIF TG_TABLE_NAME = 'farm_activities' THEN
        IF TG_OP <> 'DELETE' THEN
            PERFORM farmer_ic_check_activity_detail(NEW.id);
        END IF;
        IF TG_OP <> 'INSERT' THEN
            PERFORM farmer_ic_check_activity_detail(OLD.id);
        END IF;
    ELSE
        IF TG_OP <> 'DELETE' AND NEW.activity_id IS NOT NULL THEN
            PERFORM farmer_ic_check_activity_detail(NEW.activity_id);
        END IF;
        IF TG_OP <> 'INSERT' AND OLD.activity_id IS NOT NULL THEN
            PERFORM farmer_ic_check_activity_detail(OLD.activity_id);
        END IF;
    END IF;
    RETURN NULL;
END;
$$;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION farmer_ic_validate_active_area()
RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    farm_ids uuid[];
    farm_record record;
    active_area numeric;
BEGIN
    IF TG_TABLE_NAME = 'farms' THEN
        farm_ids := ARRAY[NEW.id];
    ELSIF TG_OP = 'INSERT' THEN
        farm_ids := ARRAY[NEW.farm_id];
    ELSIF TG_OP = 'DELETE' THEN
        farm_ids := ARRAY[OLD.farm_id];
    ELSE
        farm_ids := ARRAY[OLD.farm_id, NEW.farm_id];
    END IF;

    -- Serialize checks for the same farm, also covering concurrent inserts.
    FOR farm_record IN
        SELECT id, total_area_ha FROM farms WHERE id = ANY(farm_ids) ORDER BY id FOR UPDATE
    LOOP
        SELECT COALESCE(SUM(planted_area_ha), 0) INTO active_area
        FROM planting_cycles WHERE farm_id = farm_record.id AND status = 'active';
        IF active_area > farm_record.total_area_ha THEN
            RAISE EXCEPTION 'Active planting area exceeds farm % capacity', farm_record.id
                USING ERRCODE = '23514', CONSTRAINT = 'planting_cycles_active_area_capacity_check';
        END IF;
    END LOOP;
    RETURN NULL;
END;
$$;

CREATE CONSTRAINT TRIGGER planting_cycles_area_guard
AFTER INSERT OR UPDATE OR DELETE ON planting_cycles
DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
EXECUTE FUNCTION farmer_ic_validate_active_area();

CREATE CONSTRAINT TRIGGER farms_area_guard
AFTER UPDATE ON farms
DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
EXECUTE FUNCTION farmer_ic_validate_active_area();
SQL);

        // Deferred checks allow the activity and its detail to be saved in one
        // transaction, while rejecting missing, duplicate, or mismatched details.
        foreach ($this->activityTables() as $table) {
            DB::statement("CREATE CONSTRAINT TRIGGER {$table}_detail_guard AFTER INSERT OR UPDATE OR DELETE ON {$table} DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_validate_activity_details()");
        }
        foreach (['activity_types', 'planting_cycles'] as $table) {
            DB::statement("CREATE CONSTRAINT TRIGGER {$table}_detail_guard AFTER UPDATE ON {$table} DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_validate_activity_details()");
        }
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS farms_area_guard ON farms');
        DB::statement('DROP TRIGGER IF EXISTS planting_cycles_area_guard ON planting_cycles');
        DB::statement('DROP FUNCTION IF EXISTS farmer_ic_validate_active_area()');

        foreach ([...$this->activityTables(), 'activity_types', 'planting_cycles'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_detail_guard ON {$table}");
        }
        DB::statement('DROP FUNCTION IF EXISTS farmer_ic_validate_activity_details()');
        DB::statement('DROP FUNCTION IF EXISTS farmer_ic_check_activity_detail(uuid)');

        foreach (array_reverse($this->compositeForeignKeys()) as [$table, $name]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
        }
        foreach ([
            'financial_categories' => 'financial_categories_id_type_unique',
            'plant_observations' => 'plant_observations_id_cycle_unique',
            'farm_activities' => 'farm_activities_id_cycle_unique',
            'planting_cycles' => 'planting_cycles_id_farm_unique',
        ] as $table => $name) {
            Schema::table($table, fn (Blueprint $table) => $table->dropUnique($name));
        }
    }

    /** @return list<array{string, string, string, string, string}> */
    private function compositeForeignKeys(): array
    {
        return [
            ['farm_activities', 'activities_cycle_farm_fk', 'planting_cycle_id, farm_id', 'planting_cycles', 'id, farm_id'],
            ['financial_transactions', 'transactions_cycle_farm_fk', 'planting_cycle_id, farm_id', 'planting_cycles', 'id, farm_id'],
            ['financial_projections', 'projections_cycle_farm_fk', 'planting_cycle_id, farm_id', 'planting_cycles', 'id, farm_id'],
            ['operational_targets', 'targets_cycle_farm_fk', 'planting_cycle_id, farm_id', 'planting_cycles', 'id, farm_id'],
            ['plant_observations', 'observations_activity_cycle_fk', 'activity_id, planting_cycle_id', 'farm_activities', 'id, planting_cycle_id'],
            ['farm_risks', 'risks_observation_cycle_fk', 'observation_id, planting_cycle_id', 'plant_observations', 'id, planting_cycle_id'],
            ['ai_insights', 'insights_observation_cycle_fk', 'observation_id, planting_cycle_id', 'plant_observations', 'id, planting_cycle_id'],
            ['financial_transactions', 'transactions_category_type_fk', 'category_id, transaction_type', 'financial_categories', 'id, transaction_type'],
        ];
    }

    /** @return list<string> */
    private function activityTables(): array
    {
        return ['farm_activities', 'planting_details', 'fertilization_details', 'irrigation_details', 'pesticide_details', 'incident_details', 'harvest_details', 'plant_observations'];
    }
};

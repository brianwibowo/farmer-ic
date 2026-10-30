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
            throw new LogicException('Farm blocks and preparation batches require PostgreSQL.');
        }
        Schema::create('farm_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id')->index();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->decimal('area_ha', 12, 4);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by');
            $table->timestampTz('land_clearing_started_at');
            $table->timestampTz('land_clearing_completed_at')->nullable();
            $table->timestampsTz();
            $table->foreign('farm_id')->references('id')->on('farms')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['farm_id', 'code']);
        });
        DB::statement("ALTER TABLE farm_blocks ADD CONSTRAINT blocks_values_check CHECK (area_ha > 0 AND code ~ '^[A-Z0-9][A-Z0-9_-]*$' AND btrim(name) <> '' AND (land_clearing_completed_at IS NULL OR land_clearing_completed_at >= land_clearing_started_at))");

        Schema::create('planting_rounds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('block_id')->index();
            $table->integer('round_number');
            $table->string('status', 30)->default('active');
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->foreign('block_id')->references('id')->on('farm_blocks')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['block_id', 'round_number']);
        });
        DB::statement("ALTER TABLE planting_rounds ADD CONSTRAINT rounds_values_check CHECK (round_number > 0 AND status IN ('active', 'closed', 'cancelled'))");
        DB::statement("CREATE UNIQUE INDEX rounds_one_active_per_block ON planting_rounds(block_id) WHERE status = 'active'");

        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->uuid('planting_round_id')->nullable()->index();
            $table->integer('batch_number')->nullable();
            $table->string('subblock_label', 30)->nullable();
            $table->decimal('reserved_area_ha', 12, 4)->nullable();
            $table->uuid('created_by')->nullable();
            $table->foreign('planting_round_id')->references('id')->on('planting_rounds')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['farm_id', 'batch_number']);
            $table->unique(['planting_round_id', 'subblock_label']);
        });
        DB::statement('ALTER TABLE planting_cycles ALTER COLUMN planted_at DROP NOT NULL, ALTER COLUMN variety_id DROP NOT NULL');
        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT planting_cycles_planted_area_ha_positive');
        DB::statement("ALTER TABLE planting_cycles ADD CONSTRAINT planting_cycles_planted_area_ha_positive CHECK (planted_area_ha > 0 OR (planting_round_id IS NOT NULL AND status IN ('planned', 'cancelled') AND planted_area_ha = 0))");
        DB::statement("ALTER TABLE planting_cycles ADD CONSTRAINT batches_preparation_check CHECK ((planted_at IS NOT NULL AND variety_id IS NOT NULL) OR (planting_round_id IS NOT NULL AND status IN ('planned', 'cancelled') AND planted_area_ha = 0))");
        DB::statement('ALTER TABLE planting_cycles ADD CONSTRAINT batches_metadata_check CHECK ((planting_round_id IS NULL AND batch_number IS NULL AND subblock_label IS NULL AND reserved_area_ha IS NULL) OR (planting_round_id IS NOT NULL AND batch_number IS NOT NULL AND batch_number > 0 AND subblock_label IS NOT NULL AND btrim(subblock_label) <> \'\' AND reserved_area_ha IS NOT NULL AND reserved_area_ha > 0 AND planted_area_ha <= reserved_area_ha AND created_by IS NOT NULL))');

        Schema::create('planting_cycle_stages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('planting_cycle_id')->index();
            $table->string('code', 30);
            $table->integer('position');
            $table->string('status', 30);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->foreign('planting_cycle_id')->references('id')->on('planting_cycles')->restrictOnDelete();
            $table->unique(['planting_cycle_id', 'code']);
            $table->unique(['planting_cycle_id', 'position']);
        });
        DB::statement("ALTER TABLE planting_cycle_stages ADD CONSTRAINT stages_values_check CHECK (code IN ('land_clearing', 'soil_preparation', 'basic_fertilizer', 'planting', 'maintenance', 'harvest') AND position BETWEEN 1 AND 6 AND status IN ('upcoming', 'in_progress', 'completed') AND ((status = 'upcoming' AND started_at IS NULL AND completed_at IS NULL) OR (status = 'in_progress' AND started_at IS NOT NULL AND completed_at IS NULL) OR (status = 'completed' AND started_at IS NOT NULL AND completed_at IS NOT NULL AND completed_at >= started_at)))");
        DB::statement("ALTER TABLE planting_cycle_stages ADD CONSTRAINT stages_order_check CHECK (position = CASE code WHEN 'land_clearing' THEN 1 WHEN 'soil_preparation' THEN 2 WHEN 'basic_fertilizer' THEN 3 WHEN 'planting' THEN 4 WHEN 'maintenance' THEN 5 WHEN 'harvest' THEN 6 END)");
        DB::statement("CREATE UNIQUE INDEX stages_one_running_per_batch ON planting_cycle_stages(planting_cycle_id) WHERE status = 'in_progress'");
        Schema::table('block_mapping_details', function (Blueprint $table): void {
            $table->uuid('block_id')->nullable()->index();
            $table->foreign('block_id')->references('id')->on('farm_blocks')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION farmer_ic_check_block_capacity() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE farm_ids uuid[]; f record; b record; occupied numeric;
BEGIN
    IF TG_TABLE_NAME = 'farms' THEN
        farm_ids := ARRAY[NEW.id];
    ELSIF TG_TABLE_NAME = 'planting_rounds' THEN
        IF TG_OP = 'INSERT' THEN SELECT ARRAY[farm_id] INTO farm_ids FROM farm_blocks WHERE id = NEW.block_id;
        ELSE SELECT array_agg(DISTINCT farm_id) INTO farm_ids FROM farm_blocks WHERE id IN (OLD.block_id, NEW.block_id); END IF;
    ELSIF TG_OP = 'INSERT' THEN farm_ids := ARRAY[NEW.farm_id];
    ELSIF TG_OP = 'DELETE' THEN farm_ids := ARRAY[OLD.farm_id];
    ELSE farm_ids := ARRAY[OLD.farm_id, NEW.farm_id]; END IF;
    FOR f IN SELECT id, total_area_ha FROM farms WHERE id = ANY(farm_ids) ORDER BY id FOR UPDATE LOOP
        IF (SELECT COALESCE(SUM(area_ha), 0) FROM farm_blocks WHERE farm_id = f.id AND is_active) > f.total_area_ha THEN
            RAISE EXCEPTION 'Block area exceeds farm capacity' USING ERRCODE = '23514', CONSTRAINT = 'blocks_farm_capacity_check';
        END IF;
        SELECT COALESCE(SUM(CASE WHEN planting_round_id IS NULL THEN planted_area_ha ELSE reserved_area_ha END), 0)
        INTO occupied FROM planting_cycles WHERE farm_id = f.id AND
            ((planting_round_id IS NULL AND status = 'active') OR (planting_round_id IS NOT NULL AND status IN ('planned', 'active')));
        IF occupied > f.total_area_ha THEN
            RAISE EXCEPTION 'Batch reservations exceed farm capacity' USING ERRCODE = '23514', CONSTRAINT = 'batches_farm_capacity_check';
        END IF;
        FOR b IN SELECT * FROM farm_blocks WHERE farm_id = f.id ORDER BY id FOR UPDATE LOOP
            SELECT COALESCE(SUM(c.reserved_area_ha), 0) INTO occupied FROM planting_cycles c
                JOIN planting_rounds r ON r.id = c.planting_round_id WHERE r.block_id = b.id AND c.status IN ('planned', 'active');
            IF occupied > b.area_ha OR (NOT b.is_active AND occupied > 0) THEN
                RAISE EXCEPTION 'Invalid block capacity or inactive block with running batches' USING ERRCODE = '23514', CONSTRAINT = 'batches_block_capacity_check';
            END IF;
        END LOOP;
    END LOOP;
    RETURN NULL;
END; $$;
CREATE CONSTRAINT TRIGGER blocks_capacity_guard AFTER INSERT OR UPDATE OR DELETE ON farm_blocks DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();
CREATE CONSTRAINT TRIGGER farms_blocks_capacity_guard AFTER UPDATE ON farms DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();
CREATE CONSTRAINT TRIGGER batches_capacity_guard AFTER INSERT OR UPDATE OR DELETE ON planting_cycles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();
CREATE CONSTRAINT TRIGGER rounds_capacity_guard AFTER INSERT OR UPDATE ON planting_rounds DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();

CREATE OR REPLACE FUNCTION farmer_ic_check_block_links() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM planting_cycles c JOIN planting_rounds r ON r.id = c.planting_round_id JOIN farm_blocks b ON b.id = r.block_id WHERE c.farm_id <> b.farm_id)
    OR EXISTS (SELECT 1 FROM planting_cycles c JOIN planting_rounds r ON r.id = c.planting_round_id WHERE c.status IN ('planned', 'active') AND r.status <> 'active')
    OR EXISTS (SELECT 1 FROM block_mapping_details d JOIN farm_activities a ON a.id = d.activity_id JOIN farm_blocks b ON b.id = d.block_id WHERE a.farm_id <> b.farm_id)
    OR EXISTS (SELECT 1 FROM planting_cycles c JOIN farm_activities a ON a.planting_cycle_id = c.id JOIN activity_types t ON t.id = a.activity_type_id WHERE c.planted_at IS NULL AND t.code IN ('planting', 'harvest')) THEN
        RAISE EXCEPTION 'Invalid block farm link or planting/harvest without planting date' USING ERRCODE = '23514', CONSTRAINT = 'blocks_trusted_links_check';
    END IF;
    RETURN NULL;
END; $$;
CREATE CONSTRAINT TRIGGER blocks_links_guard AFTER UPDATE ON farm_blocks DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER rounds_links_guard AFTER INSERT OR UPDATE ON planting_rounds DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER cycles_blocks_links_guard AFTER INSERT OR UPDATE ON planting_cycles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER mapping_blocks_links_guard AFTER INSERT OR UPDATE ON block_mapping_details DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER activities_blocks_links_guard AFTER INSERT OR UPDATE ON farm_activities DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();

CREATE OR REPLACE FUNCTION farmer_ic_check_batch_stages() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM planting_cycles c WHERE c.planting_round_id IS NOT NULL AND (SELECT COUNT(*) FROM planting_cycle_stages s WHERE s.planting_cycle_id = c.id) <> 6) THEN
        RAISE EXCEPTION 'Preparation batches require exactly six timeline stages' USING ERRCODE = '23514', CONSTRAINT = 'batches_six_stages_check';
    END IF;
    RETURN NULL;
END; $$;
CREATE CONSTRAINT TRIGGER cycles_stages_guard AFTER INSERT OR UPDATE ON planting_cycles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_batch_stages();
CREATE CONSTRAINT TRIGGER stages_count_guard AFTER INSERT OR UPDATE OR DELETE ON planting_cycle_stages DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_batch_stages();
SQL);
    }

    public function down(): void
    {
        if (DB::table('farm_blocks')->exists() || DB::table('planting_rounds')->exists() || DB::table('planting_cycle_stages')->exists()) {
            throw new LogicException('Preserve block and batch history before rolling back this migration.');
        }
        foreach (['farm_blocks' => ['blocks_capacity_guard', 'blocks_links_guard'], 'farms' => ['farms_blocks_capacity_guard'], 'planting_rounds' => ['rounds_links_guard', 'rounds_capacity_guard'], 'planting_cycles' => ['batches_capacity_guard', 'cycles_blocks_links_guard', 'cycles_stages_guard'], 'planting_cycle_stages' => ['stages_count_guard'], 'block_mapping_details' => ['mapping_blocks_links_guard'], 'farm_activities' => ['activities_blocks_links_guard']] as $table => $triggers) {
            foreach ($triggers as $trigger) {
                DB::statement("DROP TRIGGER {$trigger} ON {$table}");
            }
        }
        DB::statement('DROP FUNCTION farmer_ic_check_block_capacity()');
        DB::statement('DROP FUNCTION farmer_ic_check_block_links()');
        DB::statement('DROP FUNCTION farmer_ic_check_batch_stages()');
        Schema::table('block_mapping_details', function (Blueprint $table): void {
            $table->dropForeign(['block_id']);
            $table->dropColumn('block_id');
        });
        Schema::drop('planting_cycle_stages');
        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT batches_metadata_check, DROP CONSTRAINT batches_preparation_check, DROP CONSTRAINT planting_cycles_planted_area_ha_positive');
        DB::statement('ALTER TABLE planting_cycles ALTER COLUMN planted_at SET NOT NULL, ALTER COLUMN variety_id SET NOT NULL, ADD CONSTRAINT planting_cycles_planted_area_ha_positive CHECK (planted_area_ha > 0)');
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->dropForeign(['planting_round_id']);
            $table->dropForeign(['created_by']);
            $table->dropUnique(['farm_id', 'batch_number']);
            $table->dropUnique(['planting_round_id', 'subblock_label']);
            $table->dropColumn(['planting_round_id', 'batch_number', 'subblock_label', 'reserved_area_ha', 'created_by']);
        });
        Schema::drop('planting_rounds');
        Schema::drop('farm_blocks');
    }
};

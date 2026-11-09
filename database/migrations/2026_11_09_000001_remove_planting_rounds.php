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
            throw new LogicException('Removing planting rounds requires PostgreSQL.');
        }

        // The activity-to-block link migration may run before this one on a fresh
        // database; replace its round-based guards with direct block-cycle guards.
        DB::statement('DROP TRIGGER IF EXISTS activity_block_cycle_guard ON farm_activities');
        DB::statement('DROP TRIGGER IF EXISTS activity_cycle_move_guard ON planting_cycles');
        DB::statement('DROP TRIGGER IF EXISTS activity_round_move_guard ON planting_rounds');
        DB::statement('DROP FUNCTION IF EXISTS farmer_ic_check_activity_block()');
        DB::statement('DROP FUNCTION IF EXISTS farmer_ic_check_cycle_block_move()');
        DB::statement('DROP FUNCTION IF EXISTS farmer_ic_check_round_block_move()');

        foreach ([
            'farm_blocks' => ['blocks_capacity_guard', 'blocks_links_guard'],
            'farms' => ['farms_blocks_capacity_guard'],
            'planting_rounds' => ['rounds_capacity_guard', 'rounds_links_guard'],
            'planting_cycles' => ['batches_capacity_guard', 'cycles_blocks_links_guard', 'cycles_stages_guard'],
            'planting_cycle_stages' => ['stages_count_guard'],
            'block_mapping_details' => ['mapping_blocks_links_guard'],
            'farm_activities' => ['activities_blocks_links_guard'],
        ] as $table => $triggers) {
            foreach ($triggers as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON {$table}");
            }
        }

        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->uuid('block_id')->nullable()->index();
        });
        DB::statement('UPDATE planting_cycles AS c SET block_id = r.block_id FROM planting_rounds AS r WHERE r.id = c.planting_round_id');
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT IF EXISTS planting_cycles_planted_area_ha_positive');
        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT IF EXISTS batches_preparation_check');
        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT IF EXISTS batches_metadata_check');
        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT IF EXISTS planting_cycles_planting_round_id_subblock_label_unique');
        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT IF EXISTS planting_cycles_planting_round_id_foreign');
        DB::statement('ALTER TABLE planting_cycles DROP COLUMN IF EXISTS planting_round_id');
        Schema::dropIfExists('planting_rounds');

        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->foreign('block_id')->references('id')->on('farm_blocks')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE planting_cycles ADD CONSTRAINT planting_cycles_planted_area_ha_positive CHECK (planted_area_ha > 0 OR (block_id IS NOT NULL AND status IN ('planned', 'cancelled') AND planted_area_ha = 0))");
        DB::statement("ALTER TABLE planting_cycles ADD CONSTRAINT batches_preparation_check CHECK ((planted_at IS NOT NULL AND variety_id IS NOT NULL) OR (block_id IS NOT NULL AND status IN ('planned', 'cancelled') AND planted_area_ha = 0))");
        DB::statement("ALTER TABLE planting_cycles ADD CONSTRAINT batches_metadata_check CHECK ((block_id IS NULL AND batch_number IS NULL AND subblock_label IS NULL AND reserved_area_ha IS NULL) OR (block_id IS NOT NULL AND batch_number IS NOT NULL AND batch_number > 0 AND subblock_label IS NOT NULL AND btrim(subblock_label) <> '' AND reserved_area_ha IS NOT NULL AND reserved_area_ha > 0 AND planted_area_ha <= reserved_area_ha AND created_by IS NOT NULL))");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION farmer_ic_check_block_capacity() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE farm_ids uuid[]; f record; b record; occupied numeric;
BEGIN
    IF TG_TABLE_NAME = 'farms' THEN
        farm_ids := ARRAY[NEW.id];
    ELSIF TG_OP = 'INSERT' THEN farm_ids := ARRAY[NEW.farm_id];
    ELSIF TG_OP = 'DELETE' THEN farm_ids := ARRAY[OLD.farm_id];
    ELSE farm_ids := ARRAY[OLD.farm_id, NEW.farm_id]; END IF;
    FOR f IN SELECT id, total_area_ha FROM farms WHERE id = ANY(farm_ids) ORDER BY id FOR UPDATE LOOP
        IF (SELECT COALESCE(SUM(area_ha), 0) FROM farm_blocks WHERE farm_id = f.id AND is_active) > f.total_area_ha THEN
            RAISE EXCEPTION 'Block area exceeds farm capacity' USING ERRCODE = '23514', CONSTRAINT = 'blocks_farm_capacity_check';
        END IF;
        SELECT COALESCE(SUM(CASE WHEN block_id IS NULL THEN planted_area_ha ELSE reserved_area_ha END), 0)
        INTO occupied FROM planting_cycles WHERE farm_id = f.id AND
            ((block_id IS NULL AND status = 'active') OR (block_id IS NOT NULL AND status IN ('planned', 'active')));
        IF occupied > f.total_area_ha THEN
            RAISE EXCEPTION 'Batch reservations exceed farm capacity' USING ERRCODE = '23514', CONSTRAINT = 'batches_farm_capacity_check';
        END IF;
        FOR b IN SELECT * FROM farm_blocks WHERE farm_id = f.id ORDER BY id FOR UPDATE LOOP
            SELECT COALESCE(SUM(c.reserved_area_ha), 0) INTO occupied FROM planting_cycles c
                WHERE c.block_id = b.id AND c.status IN ('planned', 'active');
            IF occupied > b.area_ha OR (NOT b.is_active AND occupied > 0) THEN
                RAISE EXCEPTION 'Invalid block capacity or inactive block with running batches' USING ERRCODE = '23514', CONSTRAINT = 'batches_block_capacity_check';
            END IF;
        END LOOP;
    END LOOP;
    RETURN NULL;
END; $$;

CREATE OR REPLACE FUNCTION farmer_ic_check_block_links() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM planting_cycles c JOIN farm_blocks b ON b.id = c.block_id WHERE c.farm_id <> b.farm_id)
    OR EXISTS (SELECT 1 FROM planting_cycles c JOIN farm_blocks b ON b.id = c.block_id WHERE c.status IN ('planned', 'active') AND NOT b.is_active)
    OR EXISTS (SELECT 1 FROM block_mapping_details d JOIN farm_activities a ON a.id = d.activity_id JOIN farm_blocks b ON b.id = d.block_id WHERE a.farm_id <> b.farm_id)
    OR EXISTS (SELECT 1 FROM planting_cycles c JOIN farm_activities a ON a.planting_cycle_id = c.id JOIN activity_types t ON t.id = a.activity_type_id WHERE c.planted_at IS NULL AND t.code IN ('planting', 'harvest')) THEN
        RAISE EXCEPTION 'Invalid block farm link or planting/harvest without planting date' USING ERRCODE = '23514', CONSTRAINT = 'blocks_trusted_links_check';
    END IF;
    RETURN NULL;
END; $$;

CREATE OR REPLACE FUNCTION farmer_ic_check_batch_stages() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM planting_cycles c WHERE c.block_id IS NOT NULL AND (SELECT COUNT(*) FROM planting_cycle_stages s WHERE s.planting_cycle_id = c.id) <> 6) THEN
        RAISE EXCEPTION 'Preparation batches require exactly six timeline stages' USING ERRCODE = '23514', CONSTRAINT = 'batches_six_stages_check';
    END IF;
    RETURN NULL;
END; $$;

CREATE CONSTRAINT TRIGGER blocks_capacity_guard AFTER INSERT OR UPDATE OR DELETE ON farm_blocks DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();
CREATE CONSTRAINT TRIGGER farms_blocks_capacity_guard AFTER UPDATE ON farms DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();
CREATE CONSTRAINT TRIGGER batches_capacity_guard AFTER INSERT OR UPDATE OR DELETE ON planting_cycles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();
CREATE CONSTRAINT TRIGGER blocks_links_guard AFTER UPDATE ON farm_blocks DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER cycles_blocks_links_guard AFTER INSERT OR UPDATE ON planting_cycles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER mapping_blocks_links_guard AFTER INSERT OR UPDATE ON block_mapping_details DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER activities_blocks_links_guard AFTER INSERT OR UPDATE ON farm_activities DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER cycles_stages_guard AFTER INSERT OR UPDATE ON planting_cycles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_batch_stages();
CREATE CONSTRAINT TRIGGER stages_count_guard AFTER INSERT OR UPDATE OR DELETE ON planting_cycle_stages DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_batch_stages();

CREATE OR REPLACE FUNCTION farmer_ic_check_activity_block() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.block_id IS NOT NULL AND NEW.planting_cycle_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM planting_cycles c WHERE c.id = NEW.planting_cycle_id AND c.block_id = NEW.block_id
    ) THEN
        RAISE EXCEPTION 'Activity block does not match planting cycle' USING ERRCODE = '23514', CONSTRAINT = 'activity_block_cycle_check';
    END IF;
    RETURN NEW;
END; $$;
CREATE TRIGGER activity_block_cycle_guard BEFORE INSERT OR UPDATE OF block_id, planting_cycle_id ON farm_activities
FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_activity_block();

CREATE OR REPLACE FUNCTION farmer_ic_check_cycle_block_move() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM farm_activities a
        WHERE a.planting_cycle_id = NEW.id AND a.block_id IS NOT NULL
        AND (NEW.block_id IS NULL OR a.block_id <> NEW.block_id)) THEN
        RAISE EXCEPTION 'Cycle move conflicts with activity block' USING ERRCODE = '23514', CONSTRAINT = 'activity_block_cycle_check';
    END IF;
    RETURN NEW;
END; $$;
CREATE TRIGGER activity_cycle_move_guard BEFORE UPDATE OF block_id ON planting_cycles
FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_cycle_block_move();
SQL);
    }

    public function down(): void
    {
        if (DB::table('planting_cycles')->whereNotNull('block_id')->exists()) {
            throw new LogicException('Preserve block-linked planting history before rolling back the planting-round removal.');
        }

        DB::statement('DROP TRIGGER IF EXISTS activity_block_cycle_guard ON farm_activities');
        DB::statement('DROP TRIGGER IF EXISTS activity_cycle_move_guard ON planting_cycles');
        DB::statement('DROP FUNCTION IF EXISTS farmer_ic_check_activity_block()');
        DB::statement('DROP FUNCTION IF EXISTS farmer_ic_check_cycle_block_move()');

        foreach ([
            'farm_blocks' => ['blocks_capacity_guard', 'blocks_links_guard'],
            'farms' => ['farms_blocks_capacity_guard'],
            'planting_cycles' => ['batches_capacity_guard', 'cycles_blocks_links_guard', 'cycles_stages_guard'],
            'planting_cycle_stages' => ['stages_count_guard'],
            'block_mapping_details' => ['mapping_blocks_links_guard'],
            'farm_activities' => ['activities_blocks_links_guard'],
        ] as $table => $triggers) {
            foreach ($triggers as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON {$table}");
            }
        }

        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT IF EXISTS planting_cycles_planted_area_ha_positive');
        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT IF EXISTS batches_preparation_check');
        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT IF EXISTS batches_metadata_check');
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->dropForeign(['block_id']);
            $table->dropColumn('block_id');
        });

        DB::unprepared(<<<'SQL'
CREATE TABLE planting_rounds (
    id uuid NOT NULL PRIMARY KEY,
    block_id uuid NOT NULL,
    round_number integer NOT NULL,
    status varchar(30) NOT NULL DEFAULT 'active',
    created_by uuid NOT NULL,
    created_at timestamp(0) with time zone NULL,
    updated_at timestamp(0) with time zone NULL
);
CREATE INDEX planting_rounds_block_id_index ON planting_rounds(block_id);
CREATE INDEX planting_rounds_created_by_index ON planting_rounds(created_by);
ALTER TABLE planting_rounds ADD CONSTRAINT planting_rounds_block_id_foreign FOREIGN KEY (block_id) REFERENCES farm_blocks(id) ON DELETE RESTRICT;
ALTER TABLE planting_rounds ADD CONSTRAINT planting_rounds_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT;
ALTER TABLE planting_rounds ADD CONSTRAINT planting_rounds_block_id_round_number_unique UNIQUE (block_id, round_number);
SQL);
        DB::statement("ALTER TABLE planting_rounds ADD CONSTRAINT rounds_values_check CHECK (round_number > 0 AND status IN ('active', 'closed', 'cancelled'))");
        DB::statement('CREATE UNIQUE INDEX rounds_one_active_per_block ON planting_rounds(block_id) WHERE status = \'active\'');

        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->uuid('planting_round_id')->nullable()->index();
            $table->foreign('planting_round_id')->references('id')->on('planting_rounds')->restrictOnDelete();
            $table->unique(['planting_round_id', 'subblock_label']);
        });

        DB::statement("ALTER TABLE planting_cycles ADD CONSTRAINT planting_cycles_planted_area_ha_positive CHECK (planted_area_ha > 0 OR (planting_round_id IS NOT NULL AND status IN ('planned', 'cancelled') AND planted_area_ha = 0))");
        DB::statement("ALTER TABLE planting_cycles ADD CONSTRAINT batches_preparation_check CHECK ((planted_at IS NOT NULL AND variety_id IS NOT NULL) OR (planting_round_id IS NOT NULL AND status IN ('planned', 'cancelled') AND planted_area_ha = 0))");
        DB::statement("ALTER TABLE planting_cycles ADD CONSTRAINT batches_metadata_check CHECK ((planting_round_id IS NULL AND batch_number IS NULL AND subblock_label IS NULL AND reserved_area_ha IS NULL) OR (planting_round_id IS NOT NULL AND batch_number IS NOT NULL AND batch_number > 0 AND subblock_label IS NOT NULL AND btrim(subblock_label) <> '' AND reserved_area_ha IS NOT NULL AND reserved_area_ha > 0 AND planted_area_ha <= reserved_area_ha AND created_by IS NOT NULL))");

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

CREATE OR REPLACE FUNCTION farmer_ic_check_batch_stages() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM planting_cycles c WHERE c.planting_round_id IS NOT NULL AND (SELECT COUNT(*) FROM planting_cycle_stages s WHERE s.planting_cycle_id = c.id) <> 6) THEN
        RAISE EXCEPTION 'Preparation batches require exactly six timeline stages' USING ERRCODE = '23514', CONSTRAINT = 'batches_six_stages_check';
    END IF;
    RETURN NULL;
END; $$;

CREATE CONSTRAINT TRIGGER blocks_capacity_guard AFTER INSERT OR UPDATE OR DELETE ON farm_blocks DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();
CREATE CONSTRAINT TRIGGER farms_blocks_capacity_guard AFTER UPDATE ON farms DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();
CREATE CONSTRAINT TRIGGER batches_capacity_guard AFTER INSERT OR UPDATE OR DELETE ON planting_cycles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();
CREATE CONSTRAINT TRIGGER rounds_capacity_guard AFTER INSERT OR UPDATE ON planting_rounds DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_capacity();
CREATE CONSTRAINT TRIGGER blocks_links_guard AFTER UPDATE ON farm_blocks DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER rounds_links_guard AFTER INSERT OR UPDATE ON planting_rounds DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER cycles_blocks_links_guard AFTER INSERT OR UPDATE ON planting_cycles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER mapping_blocks_links_guard AFTER INSERT OR UPDATE ON block_mapping_details DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER activities_blocks_links_guard AFTER INSERT OR UPDATE ON farm_activities DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_block_links();
CREATE CONSTRAINT TRIGGER cycles_stages_guard AFTER INSERT OR UPDATE ON planting_cycles DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_batch_stages();
CREATE CONSTRAINT TRIGGER stages_count_guard AFTER INSERT OR UPDATE OR DELETE ON planting_cycle_stages DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_batch_stages();

CREATE OR REPLACE FUNCTION farmer_ic_check_activity_block() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.block_id IS NOT NULL AND NEW.planting_cycle_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM planting_cycles c JOIN planting_rounds r ON r.id = c.planting_round_id
        WHERE c.id = NEW.planting_cycle_id AND r.block_id = NEW.block_id
    ) THEN
        RAISE EXCEPTION 'Activity block does not match planting cycle' USING ERRCODE = '23514', CONSTRAINT = 'activity_block_cycle_check';
    END IF;
    RETURN NEW;
END; $$;
CREATE TRIGGER activity_block_cycle_guard BEFORE INSERT OR UPDATE OF block_id, planting_cycle_id ON farm_activities
FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_activity_block();

CREATE OR REPLACE FUNCTION farmer_ic_check_cycle_block_move() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM farm_activities a JOIN planting_rounds r ON r.id = NEW.planting_round_id
        WHERE a.planting_cycle_id = NEW.id AND a.block_id IS NOT NULL AND a.block_id <> r.block_id) THEN
        RAISE EXCEPTION 'Cycle move conflicts with activity block' USING ERRCODE = '23514', CONSTRAINT = 'activity_block_cycle_check';
    END IF;
    RETURN NEW;
END; $$;
CREATE TRIGGER activity_cycle_move_guard BEFORE UPDATE OF planting_round_id ON planting_cycles
FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_cycle_block_move();

CREATE OR REPLACE FUNCTION farmer_ic_check_round_block_move() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM farm_activities a JOIN planting_cycles c ON c.id = a.planting_cycle_id
        WHERE c.planting_round_id = NEW.id AND a.block_id IS NOT NULL AND a.block_id <> NEW.block_id) THEN
        RAISE EXCEPTION 'Round move conflicts with activity block' USING ERRCODE = '23514', CONSTRAINT = 'activity_block_cycle_check';
    END IF;
    RETURN NEW;
END; $$;
CREATE TRIGGER activity_round_move_guard BEFORE UPDATE OF block_id ON planting_rounds
FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_round_block_move();
SQL);
    }
};

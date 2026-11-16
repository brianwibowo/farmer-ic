<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('Dynamic subblock area constraints require PostgreSQL.');
        }

        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT IF EXISTS batches_metadata_check');
        DB::statement("ALTER TABLE planting_cycles ADD CONSTRAINT batches_metadata_check CHECK ((block_id IS NULL AND batch_number IS NULL AND subblock_label IS NULL AND reserved_area_ha IS NULL) OR (block_id IS NOT NULL AND batch_number IS NOT NULL AND batch_number > 0 AND subblock_label IS NOT NULL AND btrim(subblock_label) <> '' AND reserved_area_ha IS NOT NULL AND reserved_area_ha >= 0 AND planted_area_ha >= 0 AND created_by IS NOT NULL))");
        DB::statement('UPDATE planting_cycles SET reserved_area_ha = planted_area_ha WHERE block_id IS NOT NULL');

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
        SELECT COALESCE(SUM(planted_area_ha), 0)
        INTO occupied FROM planting_cycles WHERE farm_id = f.id AND
            ((block_id IS NULL AND status = 'active') OR (block_id IS NOT NULL AND status IN ('planned', 'active')));
        IF occupied > f.total_area_ha THEN
            RAISE EXCEPTION 'Batch reservations exceed farm capacity' USING ERRCODE = '23514', CONSTRAINT = 'batches_farm_capacity_check';
        END IF;
        FOR b IN SELECT * FROM farm_blocks WHERE farm_id = f.id ORDER BY id FOR UPDATE LOOP
            SELECT COALESCE(SUM(c.planted_area_ha), 0) INTO occupied FROM planting_cycles c
                WHERE c.block_id = b.id AND c.status IN ('planned', 'active');
            IF occupied > b.area_ha OR (NOT b.is_active AND occupied > 0) THEN
                RAISE EXCEPTION 'Invalid block capacity or inactive block with running batches' USING ERRCODE = '23514', CONSTRAINT = 'batches_block_capacity_check';
            END IF;
        END LOOP;
    END LOOP;
    RETURN NULL;
END; $$;
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('Rolling back dynamic subblock area constraints requires PostgreSQL.');
        }

        if (DB::table('planting_cycles')->whereNotNull('block_id')->where('reserved_area_ha', '<=', 0)->exists()) {
            throw new LogicException('Cannot restore positive reserved_area_ha constraint while subblocks have zero reserved area.');
        }

        DB::statement('ALTER TABLE planting_cycles DROP CONSTRAINT IF EXISTS batches_metadata_check');
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
SQL);
    }
};

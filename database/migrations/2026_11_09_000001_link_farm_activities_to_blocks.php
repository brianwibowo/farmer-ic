<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_blocks', fn (Blueprint $table) => $table->unique(['id', 'farm_id'], 'blocks_id_farm_unique'));
        Schema::table('farm_activities', function (Blueprint $table): void {
            $table->uuid('block_id')->nullable()->index();
            $table->foreign(['block_id', 'farm_id'], 'activities_block_farm_fk')->references(['id', 'farm_id'])->on('farm_blocks')->restrictOnDelete();
        });
        // Only persist locations provable from a cycle or an existing mapping.
        DB::statement('UPDATE farm_activities a SET block_id = r.block_id FROM planting_cycles c JOIN planting_rounds r ON r.id = c.planting_round_id WHERE a.planting_cycle_id = c.id');
        DB::statement('UPDATE farm_activities a SET block_id = d.block_id FROM block_mapping_details d JOIN farm_blocks b ON b.id = d.block_id WHERE d.activity_id = a.id AND a.block_id IS NULL AND a.farm_id = b.farm_id');
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION farmer_ic_check_activity_block() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.block_id IS NOT NULL AND NEW.planting_cycle_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM planting_cycles c JOIN planting_rounds r ON r.id = c.planting_round_id
        WHERE c.id = NEW.planting_cycle_id AND r.block_id = NEW.block_id
    ) THEN
        RAISE EXCEPTION 'Activity block does not match planting cycle' USING ERRCODE = '23514', CONSTRAINT = 'activity_block_cycle_check';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER activity_block_cycle_guard BEFORE INSERT OR UPDATE OF block_id, planting_cycle_id ON farm_activities
FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_activity_block();
CREATE OR REPLACE FUNCTION farmer_ic_check_cycle_block_move() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM farm_activities a JOIN planting_rounds r ON r.id = NEW.planting_round_id
        WHERE a.planting_cycle_id = NEW.id AND a.block_id IS NOT NULL AND a.block_id <> r.block_id) THEN
        RAISE EXCEPTION 'Cycle move conflicts with activity block' USING ERRCODE = '23514', CONSTRAINT = 'activity_block_cycle_check';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER activity_cycle_move_guard BEFORE UPDATE OF planting_round_id ON planting_cycles
FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_cycle_block_move();
CREATE OR REPLACE FUNCTION farmer_ic_check_round_block_move() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM farm_activities a JOIN planting_cycles c ON c.id = a.planting_cycle_id
        WHERE c.planting_round_id = NEW.id AND a.block_id IS NOT NULL AND a.block_id <> NEW.block_id) THEN
        RAISE EXCEPTION 'Round move conflicts with activity block' USING ERRCODE = '23514', CONSTRAINT = 'activity_block_cycle_check';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER activity_round_move_guard BEFORE UPDATE OF block_id ON planting_rounds
FOR EACH ROW EXECUTE FUNCTION farmer_ic_check_round_block_move();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER activity_block_cycle_guard ON farm_activities');
        DB::statement('DROP FUNCTION farmer_ic_check_activity_block()');
        DB::statement('DROP TRIGGER activity_cycle_move_guard ON planting_cycles');
        DB::statement('DROP FUNCTION farmer_ic_check_cycle_block_move()');
        DB::statement('DROP TRIGGER activity_round_move_guard ON planting_rounds');
        DB::statement('DROP FUNCTION farmer_ic_check_round_block_move()');
        Schema::table('farm_activities', function (Blueprint $table): void {
            $table->dropForeign('activities_block_farm_fk');
            $table->dropColumn('block_id');
        });
        Schema::table('farm_blocks', fn (Blueprint $table) => $table->dropUnique('blocks_id_farm_unique'));
    }
};

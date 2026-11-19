<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_cycles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('farm_id')->constrained('farms')->restrictOnDelete();
            $table->unsignedInteger('cycle_number');
            $table->foreignUuid('created_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->unique(['farm_id', 'cycle_number']);
            $table->unique(['id', 'farm_id']);
        });
        DB::statement('ALTER TABLE farm_cycles ADD CONSTRAINT farm_cycles_number_positive CHECK (cycle_number > 0)');
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->uuid('farm_cycle_id')->nullable()->index();
            $table->foreign(['farm_cycle_id', 'farm_id'], 'planting_cycles_farm_cycle_boundary')
                ->references(['id', 'farm_id'])->on('farm_cycles')->restrictOnDelete();
        });

        // Existing batches have no provable historical grouping: retain them in cycle 1.
        DB::statement('INSERT INTO farm_cycles (id, farm_id, cycle_number, created_by, created_at)
            SELECT gen_random_uuid(), f.id, 1, f.created_by, COALESCE(MIN(p.created_at), CURRENT_TIMESTAMP)
            FROM farms f JOIN planting_cycles p ON p.farm_id = f.id GROUP BY f.id, f.created_by');
        DB::statement('UPDATE planting_cycles p SET farm_cycle_id = c.id FROM farm_cycles c WHERE c.farm_id = p.farm_id AND c.cycle_number = 1');
    }

    public function down(): void
    {
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->dropForeign('planting_cycles_farm_cycle_boundary');
            $table->dropColumn('farm_cycle_id');
        });
        Schema::dropIfExists('farm_cycles');
    }
};

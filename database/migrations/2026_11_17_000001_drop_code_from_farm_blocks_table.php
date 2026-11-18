<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE farm_blocks DROP CONSTRAINT IF EXISTS blocks_values_check');
        }

        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->dropUnique('farm_blocks_farm_id_code_unique');
            $table->dropColumn('code');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE farm_blocks ADD CONSTRAINT blocks_values_check CHECK (area_ha > 0 AND btrim(name) <> '' AND (land_clearing_completed_at IS NULL OR land_clearing_completed_at >= land_clearing_started_at))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE farm_blocks DROP CONSTRAINT IF EXISTS blocks_values_check');
        }

        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->string('code', 30)->nullable()->after('farm_id');
        });

        DB::statement("UPDATE farm_blocks SET code = UPPER(REGEXP_REPLACE(btrim(name), '[^A-Za-z0-9_-]+', '-', 'g')) WHERE code IS NULL");

        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->string('code', 30)->nullable(false)->change();
            $table->unique(['farm_id', 'code']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE farm_blocks ADD CONSTRAINT blocks_values_check CHECK (area_ha > 0 AND code ~ '^[A-Z0-9][A-Z0-9_-]*$' AND btrim(name) <> '' AND (land_clearing_completed_at IS NULL OR land_clearing_completed_at >= land_clearing_started_at))");
        }
    }
};

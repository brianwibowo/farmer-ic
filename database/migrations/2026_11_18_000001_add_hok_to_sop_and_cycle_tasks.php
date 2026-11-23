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
            throw new LogicException('SOP HOK migration requires PostgreSQL.');
        }

        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->integer('hok')->nullable()->after('tolerance_days');
        });
        DB::statement('ALTER TABLE sop_template_tasks ADD CONSTRAINT sop_template_tasks_hok_check CHECK (hok IS NULL OR hok >= 1)');

        Schema::table('planting_cycle_tasks', function (Blueprint $table): void {
            $table->integer('hok')->nullable()->after('tolerance_days');
        });
        DB::statement('ALTER TABLE planting_cycle_tasks ADD CONSTRAINT planting_cycle_tasks_hok_check CHECK (hok IS NULL OR hok >= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE planting_cycle_tasks DROP CONSTRAINT IF EXISTS planting_cycle_tasks_hok_check');
        Schema::table('planting_cycle_tasks', function (Blueprint $table): void {
            $table->dropColumn('hok');
        });

        DB::statement('ALTER TABLE sop_template_tasks DROP CONSTRAINT IF EXISTS sop_template_tasks_hok_check');
        Schema::table('sop_template_tasks', function (Blueprint $table): void {
            $table->dropColumn('hok');
        });
    }
};

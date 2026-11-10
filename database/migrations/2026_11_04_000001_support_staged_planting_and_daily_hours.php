<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_activity_logs', fn (Blueprint $table) => $table->time('end_time')->nullable()->change());
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->date('planting_started_on')->nullable();
            $table->timestampTz('planting_window_checked_at')->nullable();
        });
        DB::statement('UPDATE planting_cycles SET planting_started_on = planted_at::date WHERE planted_at IS NOT NULL');
    }

    public function down(): void
    {
        // Never invent finished hours to satisfy a rollback.
        if (DB::table('daily_activity_logs')->whereNull('end_time')->exists()) {
            throw new RuntimeException('Complete daily end times before rolling back.');
        }
        Schema::table('daily_activity_logs', fn (Blueprint $table) => $table->time('end_time')->nullable(false)->change());
        Schema::table('planting_cycles', fn (Blueprint $table) => $table->dropColumn(['planting_started_on', 'planting_window_checked_at']));
    }
};

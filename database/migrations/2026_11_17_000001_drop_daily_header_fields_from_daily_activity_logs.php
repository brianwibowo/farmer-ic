<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE daily_activity_logs DROP CONSTRAINT IF EXISTS daily_logs_work_hours_check');
        }

        Schema::table('daily_activity_logs', fn (Blueprint $table) => $table->dropColumn(['start_time', 'end_time', 'notes']));
    }

    public function down(): void
    {
        Schema::table('daily_activity_logs', function (Blueprint $table): void {
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('notes')->nullable();
        });

        DB::table('daily_activity_logs')->whereNull('start_time')->update(['start_time' => '08:00:00']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE daily_activity_logs ALTER COLUMN start_time SET NOT NULL');
            DB::statement('ALTER TABLE daily_activity_logs ADD CONSTRAINT daily_logs_work_hours_check CHECK (end_time > start_time)');
        }
    }
};

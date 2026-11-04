<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_activities', fn (Blueprint $table) => $table->integer('worker_count')->nullable());

        DB::statement(<<<'SQL'
UPDATE farm_activities AS activity
SET worker_count = log.worker_count
FROM daily_activity_logs AS log
WHERE activity.daily_activity_log_id = log.id
  AND activity.worker_count IS NULL
SQL);

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        DB::statement('ALTER TABLE farm_activities ADD CONSTRAINT activities_worker_count_check CHECK (worker_count IS NULL OR worker_count BETWEEN 1 AND 32767)');

        Schema::table('daily_activity_logs', fn (Blueprint $table) => $table->dropColumn('worker_count'));
    }

    public function down(): void
    {
        Schema::table('daily_activity_logs', fn (Blueprint $table) => $table->integer('worker_count')->nullable());

        DB::statement(<<<'SQL'
UPDATE daily_activity_logs AS log
SET worker_count = COALESCE((
    SELECT MAX(activity.worker_count)
    FROM farm_activities AS activity
    WHERE activity.daily_activity_log_id = log.id
), 1)
SQL);

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        DB::statement('ALTER TABLE daily_activity_logs ALTER COLUMN worker_count SET NOT NULL');
        DB::statement('ALTER TABLE daily_activity_logs ADD CONSTRAINT daily_logs_worker_count_check CHECK (worker_count BETWEEN 1 AND 32767)');
        DB::statement('ALTER TABLE farm_activities DROP CONSTRAINT activities_worker_count_check');

        Schema::table('farm_activities', fn (Blueprint $table) => $table->dropColumn('worker_count'));
    }
};

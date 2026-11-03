<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Never silently destroy or merge historical operational records.
        if (DB::table('daily_activity_logs')->select('farm_id', 'work_date')->groupBy('farm_id', 'work_date')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new LogicException('Duplicate farm/date daily logs exist. Resolve them explicitly before applying this migration. No reports have been deleted.');
        }
        Schema::table('daily_activity_logs', function (Blueprint $table): void {
            $table->unique(['farm_id', 'work_date'], 'daily_logs_farm_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('daily_activity_logs', fn (Blueprint $table) => $table->dropUnique('daily_logs_farm_date_unique'));
    }
};

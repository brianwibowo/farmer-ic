<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('type', 32)->nullable();
            $table->index(['user_id', 'type', 'created_at']);
        });
        // Classify existing operational inbox entries without rewriting their text.
        DB::table('notifications')->whereIn('title', ['Report Harian Kebun dari Petugas', 'Report Harian Petugas'])
            ->update(['type' => 'daily_report']);
        DB::table('notifications')->where('title', 'like', 'Panen Reminder %')
            ->update(['type' => 'harvest_reminder']);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'type', 'created_at']);
            $table->dropColumn('type');
        });
    }
};

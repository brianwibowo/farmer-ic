<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->index('team_id');
            $table->index(['user_id', 'team_id', 'created_at']);
        });

        // Match the existing per-tenant report identity, never guess from message text.
        DB::table('notifications')->where('type', 'daily_report')->orderBy('id')->chunkById(100, function ($notifications): void {
            foreach ($notifications as $notification) {
                $date = CarbonImmutable::parse($notification->created_at)->setTimezone('Asia/Jakarta')->toDateString();
                $teams = DB::table('team_members')->join('teams', 'teams.id', '=', 'team_members.team_id')
                    ->where('team_members.user_id', $notification->user_id)->where('teams.is_personal', false)
                    ->whereNull('teams.deleted_at')->pluck('teams.id');
                foreach ($teams as $teamId) {
                    $hash = hash('sha256', json_encode(['daily_report', $notification->user_id, (int) $teamId, $date], JSON_THROW_ON_ERROR));
                    $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
                    if ($id === $notification->id) {
                        DB::table('notifications')->where('id', $id)->update(['team_id' => $teamId]);
                        break;
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'team_id', 'created_at']);
            $table->dropIndex(['team_id']);
            $table->dropConstrainedForeignId('team_id');
        });
    }
};

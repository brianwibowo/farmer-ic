<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->text('cancellation_reason')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('incident_tickets', function (Blueprint $table): void {
            $table->string('cancellation_role', 20)->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE incident_tickets ADD CONSTRAINT incident_cancellation_role_check CHECK (cancellation_role IS NULL OR cancellation_role IN ('trigger', 'related'))");
            DB::statement("CREATE UNIQUE INDEX incident_one_cancellation_trigger ON incident_tickets (planting_cycle_id) WHERE cancellation_role = 'trigger'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS incident_one_cancellation_trigger');
            DB::statement('ALTER TABLE incident_tickets DROP CONSTRAINT IF EXISTS incident_cancellation_role_check');
        }
        Schema::table('incident_tickets', function (Blueprint $table): void {
            $table->dropColumn('cancellation_role');
        });
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });
    }
};

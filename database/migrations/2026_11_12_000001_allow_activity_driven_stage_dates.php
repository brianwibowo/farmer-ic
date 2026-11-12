<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE planting_cycle_stages DROP CONSTRAINT stages_values_check');
        DB::statement("ALTER TABLE planting_cycle_stages ADD CONSTRAINT stages_values_check CHECK (
            code IN ('land_clearing', 'soil_preparation', 'basic_fertilizer', 'planting', 'maintenance', 'harvest')
            AND position BETWEEN 1 AND 6 AND status IN ('upcoming', 'in_progress', 'completed')
            AND ((status = 'upcoming' AND started_at IS NULL AND completed_at IS NULL)
                OR (status = 'in_progress' AND started_at IS NOT NULL AND (completed_at IS NULL OR completed_at >= started_at))
                OR (status = 'completed' AND ((started_at IS NULL AND completed_at IS NULL)
                    OR (started_at IS NOT NULL AND (completed_at IS NULL OR completed_at >= started_at)))))
        )");
        // Preserve legacy in-progress stages whose final report date is unknown.
    }

    public function down(): void
    {
        $incompatible = DB::table('planting_cycle_stages')->where(function ($query): void {
            $query->where(fn ($q) => $q->where('status', 'in_progress')->whereNotNull('completed_at'))
                ->orWhere(fn ($q) => $q->where('status', 'completed')->where(fn ($q) => $q->whereNull('started_at')->orWhereNull('completed_at')));
        })->exists();
        if ($incompatible) {
            throw new LogicException('Cannot restore the old stage constraint while activity-driven dates exist.');
        }
        DB::statement('ALTER TABLE planting_cycle_stages DROP CONSTRAINT stages_values_check');
        DB::statement("ALTER TABLE planting_cycle_stages ADD CONSTRAINT stages_values_check CHECK (code IN ('land_clearing', 'soil_preparation', 'basic_fertilizer', 'planting', 'maintenance', 'harvest') AND position BETWEEN 1 AND 6 AND status IN ('upcoming', 'in_progress', 'completed') AND ((status = 'upcoming' AND started_at IS NULL AND completed_at IS NULL) OR (status = 'in_progress' AND started_at IS NOT NULL AND completed_at IS NULL) OR (status = 'completed' AND started_at IS NOT NULL AND completed_at IS NOT NULL AND completed_at >= started_at)))");
    }
};

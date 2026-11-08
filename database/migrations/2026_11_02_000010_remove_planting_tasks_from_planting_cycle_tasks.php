<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Planting is the HST anchor and the generation trigger, not a follow-up
        // task. Remove rows created before this rule was applied.
        DB::table('planting_cycle_tasks')
            ->where('activity_category', 'planting')
            ->delete();
    }

    public function down(): void
    {
        // The removed rows were semantically invalid; they cannot be restored.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->decimal('target_volume_ton', 12, 3)->nullable();
        });
        DB::statement('ALTER TABLE farms ADD CONSTRAINT farms_target_volume_positive CHECK (target_volume_ton > 0)');
    }

    public function down(): void
    {
        Schema::table('farms', fn (Blueprint $table) => $table->dropColumn('target_volume_ton'));
    }
};

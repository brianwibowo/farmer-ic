<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->index('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->dropIndex(['cancelled_by']);
        });
    }
};

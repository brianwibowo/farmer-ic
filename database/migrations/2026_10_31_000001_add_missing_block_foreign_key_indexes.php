<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->index('created_by');
        });
        Schema::table('planting_rounds', function (Blueprint $table): void {
            $table->index('created_by');
        });
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->dropIndex(['created_by']);
        });
        Schema::table('planting_rounds', function (Blueprint $table): void {
            $table->dropIndex(['created_by']);
        });
        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->dropIndex(['created_by']);
        });
    }
};

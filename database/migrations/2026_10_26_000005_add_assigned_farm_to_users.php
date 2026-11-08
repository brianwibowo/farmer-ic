<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'assigned_farm_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->uuid('assigned_farm_id')->nullable()->index();
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('assigned_farm_id')->references('id')->on('farms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['assigned_farm_id']);
            $table->dropColumn('assigned_farm_id');
        });
    }
};

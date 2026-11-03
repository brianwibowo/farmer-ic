<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->index('district_code');
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropIndex(['district_code']);
        });
    }
};

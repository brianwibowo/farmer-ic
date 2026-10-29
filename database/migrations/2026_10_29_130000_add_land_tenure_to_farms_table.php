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
            // Existing farms remain unknown until their tenure is confirmed.
            $table->string('land_tenure', 20)->nullable();
        });
        DB::statement("ALTER TABLE farms ADD CONSTRAINT farms_land_tenure_check CHECK (land_tenure IN ('owned', 'rented'))");
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropColumn('land_tenure');
        });
    }
};

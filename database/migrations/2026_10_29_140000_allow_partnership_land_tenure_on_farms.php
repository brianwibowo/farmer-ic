<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE farms DROP CONSTRAINT farms_land_tenure_check, ADD CONSTRAINT farms_land_tenure_check CHECK (land_tenure IN ('owned', 'rented', 'partnership'))");
    }

    public function down(): void
    {
        // PostgreSQL rejects rollback while partnership records exist; never relabel them silently.
        DB::statement("ALTER TABLE farms DROP CONSTRAINT farms_land_tenure_check, ADD CONSTRAINT farms_land_tenure_check CHECK (land_tenure IN ('owned', 'rented'))");
    }
};

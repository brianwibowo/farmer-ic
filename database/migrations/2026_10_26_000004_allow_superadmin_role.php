<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_name_allowed');
        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_name_allowed CHECK (name IN ('owner', 'superadmin', 'admin', 'petugas'))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_name_allowed');
        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_name_allowed CHECK (name IN ('owner', 'admin', 'petugas'))");
    }
};

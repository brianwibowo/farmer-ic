<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('Farmer IC requires PostgreSQL. Run php artisan migrate --database=pgsql.');
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 30)->unique();
            $table->text('description')->nullable();
        });
        DB::statement('ALTER TABLE roles ADD CONSTRAINT roles_name_allowed CHECK (name IN (\'owner\', \'admin\', \'petugas\'))');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->string('name', 150);
            $table->string('email', 255)->unique();
            $table->text('password_hash');
            $table->text('avatar_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
            $table->index('role_id');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};

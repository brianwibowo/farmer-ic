<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyExists = Schema::hasColumn('users', 'password_hash');
        $passwordExists = Schema::hasColumn('users', 'password');
        if ($legacyExists && $passwordExists) {
            throw new LogicException('Both users.password and users.password_hash exist. Reconcile the columns explicitly before migration; no password data was changed.');
        }
        if (! $legacyExists && ! $passwordExists) {
            throw new LogicException('The users table has no password column to migrate.');
        }
        if ($legacyExists) {
            Schema::table('users', function (Blueprint $table): void {
                $table->renameColumn('password_hash', 'password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'password_hash')) {
            throw new LogicException('Cannot roll back password rename while users.password_hash already exists.');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('password', 'password_hash');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Repair the earlier bigint definition only when no IDs need mapping.
        // Never discard an existing session's user identity during conversion.
        if (Schema::getColumnType('sessions', 'user_id') !== 'uuid') {
            if (DB::table('sessions')->whereNotNull('user_id')->exists()) {
                throw new LogicException('sessions.user_id must be UUID, but existing non-null IDs require an explicit mapping before migration. No session records were removed.');
            }
            DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE uuid USING NULL::uuid');
        }

        // Keep hashes intact when an installation used varchar instead of text.
        $passwordColumn = Schema::hasColumn('users', 'password') ? 'password' : 'password_hash';
        if (Schema::getColumnType('users', $passwordColumn) !== 'text') {
            Schema::table('users', function (Blueprint $table) use ($passwordColumn): void {
                $table->text($passwordColumn)->change();
            });
        }

        if (! Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('email_verified_at')->nullable();
            });
        }
        if (! Schema::hasColumn('users', 'two_factor_secret')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->text('two_factor_secret')->nullable();
            });
        }
        if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->text('two_factor_recovery_codes')->nullable();
            });
        }
        if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('two_factor_confirmed_at')->nullable();
            });
        }
        if (! Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->rememberToken();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'email_verified_at', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'remember_token',
            ]);
        });
        // Keep user IDs as UUID and hashes as text, matching the base schema.
    }
};

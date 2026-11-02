<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_number', 15)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('phone_number')->exists()) {
            throw new LogicException('Refusing to discard saved user phone numbers. Use a data-preserving migration.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone_number');
        });
    }
};

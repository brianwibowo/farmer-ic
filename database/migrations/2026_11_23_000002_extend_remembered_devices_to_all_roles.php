<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('owner_remembered_devices', 'remembered_devices');
        Schema::table('remembered_devices', function (Blueprint $table): void {
            $table->uuid('role_id')->nullable();
        });
        DB::table('remembered_devices')->orderBy('id')->each(function ($device): void {
            $roleId = DB::table('users')->where('id', $device->user_id)->value('role_id');
            DB::table('remembered_devices')->where('id', $device->id)->update(['role_id' => $roleId]);
        });
    }

    public function down(): void
    {
        Schema::table('remembered_devices', function (Blueprint $table): void {
            $table->dropColumn('role_id');
        });
        Schema::rename('remembered_devices', 'owner_remembered_devices');
    }
};

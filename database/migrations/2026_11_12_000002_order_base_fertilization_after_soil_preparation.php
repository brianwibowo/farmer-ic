<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_types')->where('code', 'base_fertilization')->update(['sort_order' => 24]);
    }

    public function down(): void
    {
        DB::table('activity_types')->where('code', 'base_fertilization')->where('sort_order', 24)->update(['sort_order' => 15]);
    }
};

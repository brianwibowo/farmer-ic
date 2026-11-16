<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_types')->where('code', 'base_fertilization')->update([
            'parent_id' => null,
            'is_selectable' => true,
            'detail_key' => 'fertilization',
            'sort_order' => 15,
        ]);
    }

    public function down(): void
    {
        $parentId = DB::table('activity_types')->where('code', 'soil_preparation')->value('id');
        DB::table('activity_types')->where('code', 'base_fertilization')->update([
            'parent_id' => $parentId,
            'sort_order' => 24,
        ]);
    }
};

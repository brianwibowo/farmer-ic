<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->foreignUuid('commodity_id')->nullable()->constrained('commodities')->restrictOnDelete();
            $table->foreignUuid('default_sop_template_id')->nullable()->constrained('sop_templates')->restrictOnDelete();
        });

        DB::statement('UPDATE farm_blocks SET commodity_id = farms.commodity_id, default_sop_template_id = farms.default_sop_template_id FROM farms WHERE farm_blocks.farm_id = farms.id');
    }

    public function down(): void
    {
        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_sop_template_id');
            $table->dropConstrainedForeignId('commodity_id');
        });
    }
};

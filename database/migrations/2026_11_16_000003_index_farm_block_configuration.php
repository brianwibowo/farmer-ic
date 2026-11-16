<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->index('commodity_id');
            $table->index('default_sop_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('farm_blocks', function (Blueprint $table): void {
            $table->dropIndex(['commodity_id']);
            $table->dropIndex(['default_sop_template_id']);
        });
    }
};

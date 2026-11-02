<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->uuid('default_sop_template_id')->nullable();
            $table->foreign('default_sop_template_id')->references('id')->on('sop_templates')->nullOnDelete();
            $table->index('default_sop_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropForeign(['default_sop_template_id']);
            $table->dropIndex(['default_sop_template_id']);
            $table->dropColumn('default_sop_template_id');
        });
    }
};

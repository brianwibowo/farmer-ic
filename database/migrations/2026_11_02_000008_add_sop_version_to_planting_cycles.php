<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->uuid('sop_template_version_id')->nullable();
            $table->foreign('sop_template_version_id')->references('id')->on('sop_template_versions')->restrictOnDelete();
            $table->index('sop_template_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('planting_cycles', function (Blueprint $table): void {
            $table->dropForeign(['sop_template_version_id']);
            $table->dropIndex(['sop_template_version_id']);
            $table->dropColumn('sop_template_version_id');
        });
    }
};

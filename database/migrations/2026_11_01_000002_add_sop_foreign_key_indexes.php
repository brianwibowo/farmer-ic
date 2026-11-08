<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->index('created_by');
        });
        Schema::table('sop_template_versions', function (Blueprint $table): void {
            $table->index('created_by');
            $table->index('published_by');
        });
    }

    public function down(): void
    {
        Schema::table('sop_template_versions', function (Blueprint $table): void {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['published_by']);
        });
        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->dropIndex(['created_by']);
        });
    }
};

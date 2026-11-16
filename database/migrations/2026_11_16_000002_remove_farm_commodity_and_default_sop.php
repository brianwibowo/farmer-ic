<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('commodity_id');
            $table->dropForeign(['default_sop_template_id']);
            $table->dropIndex(['default_sop_template_id']);
            $table->dropColumn('default_sop_template_id');
        });
    }

    public function down(): void
    {
        // Block configurations can diverge; restoring a single farm value is ambiguous.
        Schema::table('farms', function (Blueprint $table): void {
            $table->foreignUuid('commodity_id')->nullable()->constrained('commodities')->restrictOnDelete();
            $table->index('commodity_id');
            $table->foreignUuid('default_sop_template_id')->nullable()->constrained('sop_templates')->nullOnDelete();
            $table->index('default_sop_template_id');
        });
    }
};

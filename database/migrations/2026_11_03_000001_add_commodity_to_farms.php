<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->foreignUuid('commodity_id')->nullable()->constrained('commodities')->restrictOnDelete();
        });
        DB::statement('UPDATE farms SET commodity_id = sop_templates.commodity_id FROM sop_templates WHERE farms.default_sop_template_id = sop_templates.id AND farms.team_id = sop_templates.team_id');
    }

    public function down(): void
    {
        Schema::table('farms', fn (Blueprint $table) => $table->dropConstrainedForeignId('commodity_id'));
    }
};

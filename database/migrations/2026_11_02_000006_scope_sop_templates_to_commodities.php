<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->uuid('commodity_id')->nullable();
        });

        DB::statement(<<<'SQL'
UPDATE sop_templates AS template
SET commodity_id = variety.commodity_id
FROM crop_varieties AS variety
WHERE variety.id = template.variety_id
SQL);

        DB::statement('ALTER TABLE sop_templates ALTER COLUMN commodity_id SET NOT NULL');

        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->dropForeign(['variety_id']);
            $table->dropIndex(['variety_id']);
            $table->dropIndex(['team_id', 'variety_id']);
            $table->dropColumn('variety_id');
            $table->foreign('commodity_id')->references('id')->on('commodities')->restrictOnDelete();
            $table->index('commodity_id');
            $table->index(['team_id', 'commodity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->uuid('variety_id')->nullable();
        });

        DB::statement(<<<'SQL'
UPDATE sop_templates AS template
SET variety_id = (
    SELECT variety.id
    FROM crop_varieties AS variety
    WHERE variety.commodity_id = template.commodity_id
    ORDER BY variety.name, variety.id
    LIMIT 1
)
SQL);

        DB::statement('ALTER TABLE sop_templates ALTER COLUMN variety_id SET NOT NULL');

        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->dropForeign(['commodity_id']);
            $table->dropIndex(['commodity_id']);
            $table->dropIndex(['team_id', 'commodity_id']);
            $table->dropColumn('commodity_id');
            $table->foreign('variety_id')->references('id')->on('crop_varieties')->restrictOnDelete();
            $table->index('variety_id');
            $table->index(['team_id', 'variety_id']);
        });
    }
};

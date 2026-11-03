<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('SOP tenant ownership requires PostgreSQL.');
        }

        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_id')->nullable();
        });

        DB::statement('UPDATE sop_templates SET team_id = farms.team_id FROM farms WHERE farms.id = sop_templates.farm_id');

        if (DB::table('sop_templates')->whereNull('team_id')->exists()) {
            throw new LogicException('Every SOP template must have a resolvable tenant before farm ownership can be removed.');
        }

        DB::statement('ALTER TABLE sop_templates ALTER COLUMN team_id SET NOT NULL');

        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->dropForeign(['farm_id']);
            $table->dropIndex(['farm_id']);
            $table->dropIndex(['farm_id', 'variety_id']);
            $table->dropColumn('farm_id');
            $table->foreign('team_id')->references('id')->on('teams')->restrictOnDelete();
            $table->index(['team_id', 'variety_id']);
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('SOP tenant ownership requires PostgreSQL.');
        }

        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->uuid('farm_id')->nullable();
        });

        DB::statement('UPDATE sop_templates SET farm_id = (SELECT farms.id FROM farms WHERE farms.team_id = sop_templates.team_id ORDER BY farms.id LIMIT 1)');

        if (DB::table('sop_templates')->whereNull('farm_id')->exists()) {
            throw new LogicException('Cannot restore farm ownership because a tenant has no farm to attach its SOP templates to.');
        }

        DB::statement('ALTER TABLE sop_templates ALTER COLUMN farm_id SET NOT NULL');

        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id', 'variety_id']);
            $table->dropColumn('team_id');
            $table->foreign('farm_id')->references('id')->on('farms')->restrictOnDelete();
            $table->index('farm_id');
            $table->index(['farm_id', 'variety_id']);
        });
    }
};

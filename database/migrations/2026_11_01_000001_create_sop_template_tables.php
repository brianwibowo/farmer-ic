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
            throw new LogicException('SOP templates require PostgreSQL.');
        }

        Schema::create('sop_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id')->index();
            $table->uuid('variety_id')->index();
            $table->uuid('copied_from_version_id')->nullable()->index();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by');
            $table->timestampsTz();
            $table->foreign('farm_id')->references('id')->on('farms')->restrictOnDelete();
            $table->foreign('variety_id')->references('id')->on('crop_varieties')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['farm_id', 'variety_id']);
        });
        DB::statement("ALTER TABLE sop_templates ADD CONSTRAINT sop_templates_values_check CHECK (btrim(name) <> '')");

        Schema::create('sop_template_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sop_template_id')->index();
            $table->integer('version');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft');
            $table->uuid('created_by');
            $table->uuid('published_by')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->foreign('sop_template_id')->references('id')->on('sop_templates')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['sop_template_id', 'version']);
            $table->index(['sop_template_id', 'status']);
        });
        DB::statement(<<<'SQL'
ALTER TABLE sop_template_versions ADD CONSTRAINT sop_template_versions_values_check CHECK (
    version > 0
    AND status IN ('draft', 'published', 'retired')
    AND (
        (status = 'draft' AND published_by IS NULL AND published_at IS NULL)
        OR (status IN ('published', 'retired') AND published_by IS NOT NULL AND published_at IS NOT NULL)
    )
)
SQL);

        Schema::create('sop_template_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sop_template_version_id')->index();
            $table->uuid('activity_type_id')->index();
            $table->string('stage_code', 30);
            $table->string('name', 200);
            $table->text('instructions')->nullable();
            $table->string('schedule_anchor', 30);
            $table->integer('offset_days')->default(0);
            $table->integer('tolerance_days')->default(0);
            $table->integer('position');
            $table->boolean('is_required')->default(true);
            $table->timestampsTz();
            $table->foreign('sop_template_version_id')->references('id')->on('sop_template_versions')->restrictOnDelete();
            $table->foreign('activity_type_id')->references('id')->on('activity_types')->restrictOnDelete();
            $table->unique(['sop_template_version_id', 'position']);
            $table->index(['sop_template_version_id', 'stage_code']);
        });
        DB::statement(<<<'SQL'
ALTER TABLE sop_template_tasks ADD CONSTRAINT sop_template_tasks_values_check CHECK (
    btrim(name) <> ''
    AND stage_code IN ('land_cleaning', 'soil_preparation', 'basic_fertilizer', 'planting', 'maintenance', 'harvest')
    AND schedule_anchor IN ('planting_date', 'stage_start')
    AND tolerance_days >= 0
    AND position > 0
)
SQL);

        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->foreign('copied_from_version_id')->references('id')->on('sop_template_versions')->nullOnDelete();
        });

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT activity_types_code_allowed');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('planting', 'fertilization', 'irrigation', 'growth_check', 'pesticide', 'incident', 'harvest', 'block_mapping', 'land_cleaning', 'soil_preparation'))");
    }

    public function down(): void
    {
        if (DB::table('sop_templates')->exists() || DB::table('sop_template_versions')->exists() || DB::table('sop_template_tasks')->exists()) {
            throw new LogicException('Preserve SOP template history before rolling back this migration.');
        }
        if (DB::table('activity_types')->whereIn('code', ['land_cleaning', 'soil_preparation'])->exists()) {
            throw new LogicException('Remove SOP-only activity types before rolling back this migration.');
        }

        DB::statement('ALTER TABLE activity_types DROP CONSTRAINT activity_types_code_allowed');
        DB::statement("ALTER TABLE activity_types ADD CONSTRAINT activity_types_code_allowed CHECK (code IN ('planting', 'fertilization', 'irrigation', 'growth_check', 'pesticide', 'incident', 'harvest', 'block_mapping'))");

        Schema::table('sop_templates', function (Blueprint $table): void {
            $table->dropForeign(['copied_from_version_id']);
        });
        Schema::drop('sop_template_tasks');
        Schema::drop('sop_template_versions');
        Schema::drop('sop_templates');
    }
};

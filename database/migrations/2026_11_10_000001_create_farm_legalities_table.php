<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_legalities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('farm_id')->unique()->constrained('farms')->cascadeOnDelete();
            $table->date('pks_started_at')->nullable();
            $table->date('pks_ends_at')->nullable();
            $table->string('institution', 255);
            $table->text('summary');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE farm_legalities ADD CONSTRAINT farm_legalities_pks_dates_check CHECK (pks_ends_at IS NULL OR pks_started_at IS NULL OR pks_ends_at >= pks_started_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_legalities');
    }
};

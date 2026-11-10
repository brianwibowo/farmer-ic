<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_legality_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('farm_legality_id')->index()->constrained('farm_legalities')->cascadeOnDelete();
            $table->string('disk', 30)->default('legality');
            $table->string('path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedBigInteger('size_bytes');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_legality_documents');
    }
};

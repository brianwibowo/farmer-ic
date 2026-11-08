<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_regions', function (Blueprint $table): void {
            $table->string('code', 8);
            $table->primary('code');
            $table->string('name', 100);
            $table->unsignedSmallInteger('level')->index();
            $table->string('parent_code', 8)->nullable()->index();
            $table->foreign('parent_code')->references('code')->on('administrative_regions')->restrictOnDelete();
        });
        Schema::table('farms', function (Blueprint $table): void {
            $table->string('district_code', 8)->nullable();
            $table->foreign('district_code')->references('code')->on('administrative_regions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropForeign(['district_code']);
            $table->dropColumn('district_code');
        });
        Schema::dropIfExists('administrative_regions');
    }
};

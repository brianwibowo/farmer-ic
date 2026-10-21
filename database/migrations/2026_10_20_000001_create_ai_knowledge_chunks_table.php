<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_knowledge_document_id')
                ->constrained('ai_knowledge_documents')
                ->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            $table->string('content_hash', 64);
            $table->string('chunking_version', 32);
            $table->string('embedding_model', 128)->nullable();
            $table->string('qdrant_point_id', 128)->nullable();
            $table->unsignedInteger('token_count')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->unique([
                'ai_knowledge_document_id',
                'chunk_index',
                'content_hash',
            ], 'ai_knowledge_chunks_identity');
            $table->index(['team_id', 'status']);
            $table->index(['ai_knowledge_document_id', 'chunk_index']);
        });

        Schema::table('ai_knowledge_documents', function (Blueprint $table): void {
            $table->string('locale', 16)->default('id-ID')->after('source');
            $table->string('domain', 64)->nullable()->after('locale');
            $table->string('index_status', 32)->default('pending')->after('domain');
            $table->string('index_version', 64)->nullable()->after('index_status');
            $table->timestamp('indexed_at')->nullable()->after('index_version');
            $table->text('index_error')->nullable()->after('indexed_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_knowledge_documents', function (Blueprint $table): void {
            $table->dropColumn([
                'locale',
                'domain',
                'index_status',
                'index_version',
                'indexed_at',
                'index_error',
            ]);
        });

        Schema::dropIfExists('ai_knowledge_chunks');
    }
};

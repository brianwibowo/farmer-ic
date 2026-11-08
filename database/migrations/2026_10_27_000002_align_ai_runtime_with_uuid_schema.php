<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table): void {
            $table->foreignId('team_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('active');
            $table->jsonb('knowledge_snapshot')->nullable();
            $table->timestampTz('last_message_at')->nullable();
            $table->index(['team_id', 'user_id', 'updated_at']);
        });
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->renameColumn('conversation_id', 'ai_conversation_id');
            $table->renameColumn('sender', 'role');
            $table->renameColumn('model_name', 'model');
            $table->renameColumn('response_status', 'status');
        });
        DB::table('ai_messages')->whereNull('status')->update(['status' => 'completed']);
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->string('model', 128)->nullable()->change();
            $table->string('status', 32)->default('completed')->change();
            $table->uuid('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('provider', 64)->nullable();
            $table->string('request_id')->nullable()->index();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('updated_at')->nullable();
        });
        Schema::table('ai_attachments', function (Blueprint $table): void {
            $table->dropForeign('ai_attachments_message_id_foreign');
            $table->renameColumn('message_id', 'ai_message_id');
            $table->renameColumn('file_type', 'mime_type');
            $table->renameColumn('file_url', 'path');
        });
        Schema::table('ai_attachments', function (Blueprint $table): void {
            $table->uuid('ai_message_id')->nullable()->change();
            $table->foreign('ai_message_id')->references('id')->on('ai_messages')->nullOnDelete();
            $table->string('mime_type', 128)->nullable()->change();
            $table->uuid('ai_conversation_id')->nullable()->index();
            $table->foreign('ai_conversation_id')->references('id')->on('ai_conversations')->cascadeOnDelete();
            $table->uuid('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('disk', 64)->default('public');
            $table->string('original_name')->nullable();
            $table->bigInteger('size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('kind', 32)->default('image');
            $table->string('status', 32)->default('ready');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->index(['ai_conversation_id', 'ai_message_id']);
            $table->index(['sha256', 'status']);
        });
        DB::statement('UPDATE ai_attachments a SET ai_conversation_id = m.ai_conversation_id, user_id = COALESCE(m.user_id, c.user_id) FROM ai_messages m JOIN ai_conversations c ON c.id = m.ai_conversation_id WHERE a.ai_message_id = m.id');
        Schema::table('ai_knowledge_documents', function (Blueprint $table): void {
            $table->renameColumn('uploaded_by', 'created_by');
            $table->foreignId('team_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->uuid('reviewed_by')->nullable()->index();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('source')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->integer('version')->default(1);
            $table->timestampTz('published_at')->nullable();
            $table->string('locale', 16)->default('id-ID');
            $table->string('domain', 64)->nullable();
            $table->string('index_status', 32)->default('pending');
            $table->string('index_version', 64)->nullable();
            $table->timestampTz('indexed_at')->nullable();
            $table->text('index_error')->nullable();
            $table->index(['team_id', 'status', 'updated_at']);
            $table->index(['team_id', 'content_hash']);
        });
        DB::table('ai_knowledge_documents')->select(['id', 'content'])->orderBy('id')->chunkById(100, function ($documents): void {
            foreach ($documents as $document) {
                DB::table('ai_knowledge_documents')->where('id', $document->id)->update(['content_hash' => hash('sha256', (string) $document->content)]);
            }
        });
        Schema::create('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ai_knowledge_document_id')->index();
            $table->foreign('ai_knowledge_document_id')->references('id')->on('ai_knowledge_documents')->cascadeOnDelete();
            $table->foreignId('team_id')->index()->constrained()->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->text('content');
            $table->string('content_hash', 64);
            $table->string('chunking_version', 32);
            $table->string('embedding_model', 128)->nullable();
            $table->string('qdrant_point_id', 128)->nullable();
            $table->integer('token_count')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestampTz('indexed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['ai_knowledge_document_id', 'chunk_index', 'content_hash'], 'ai_knowledge_chunks_identity');
            $table->index(['team_id', 'status']);
        });
        Schema::create('ai_usage_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->uuid('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->uuid('ai_conversation_id')->nullable()->index();
            $table->foreign('ai_conversation_id')->references('id')->on('ai_conversations')->nullOnDelete();
            $table->uuid('ai_message_id')->nullable()->index();
            $table->foreign('ai_message_id')->references('id')->on('ai_messages')->nullOnDelete();
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->string('event_type', 64);
            $table->string('request_id')->nullable()->index();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('ai_usage_events')->exists() || DB::table('ai_knowledge_chunks')->exists()
            || DB::table('ai_attachments')->whereNull('ai_message_id')->exists()) {
            throw new LogicException('AI runtime records must be preserved or explicitly removed before rolling back this compatibility migration.');
        }
        Schema::dropIfExists('ai_usage_events');
        Schema::dropIfExists('ai_knowledge_chunks');
        Schema::table('ai_knowledge_documents', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'status', 'updated_at']);
            $table->dropIndex(['team_id', 'content_hash']);
            $table->dropConstrainedForeignId('team_id');
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['reviewed_by', 'status', 'source', 'content_hash', 'version', 'published_at', 'locale', 'domain', 'index_status', 'index_version', 'indexed_at', 'index_error']);
            $table->renameColumn('created_by', 'uploaded_by');
        });
        Schema::table('ai_attachments', function (Blueprint $table): void {
            $table->dropForeign(['ai_message_id']);
            $table->dropForeign(['ai_conversation_id']);
            $table->dropForeign(['user_id']);
            $table->dropIndex(['ai_conversation_id', 'ai_message_id']);
            $table->dropIndex(['sha256', 'status']);
            $table->dropColumn(['ai_conversation_id', 'user_id', 'disk', 'original_name', 'size', 'sha256', 'kind', 'status', 'metadata', 'updated_at']);
            $table->uuid('ai_message_id')->nullable(false)->change();
        });
        Schema::table('ai_attachments', function (Blueprint $table): void {
            $table->renameColumn('ai_message_id', 'message_id');
            $table->renameColumn('mime_type', 'file_type');
            $table->renameColumn('path', 'file_url');
        });
        Schema::table('ai_attachments', function (Blueprint $table): void {
            $table->foreign('message_id')->references('id')->on('ai_messages')->cascadeOnDelete();
            $table->string('file_type', 50)->nullable()->change();
        });
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'provider', 'request_id', 'input_tokens', 'output_tokens', 'latency_ms', 'error_message', 'metadata', 'updated_at']);
            $table->string('model', 100)->nullable()->change();
            $table->string('status', 30)->nullable()->default(null)->change();
        });
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->renameColumn('ai_conversation_id', 'conversation_id');
            $table->renameColumn('role', 'sender');
            $table->renameColumn('model', 'model_name');
            $table->renameColumn('status', 'response_status');
        });
        Schema::table('ai_conversations', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'user_id', 'updated_at']);
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn(['status', 'knowledge_snapshot', 'last_message_at']);
        });
    }
};

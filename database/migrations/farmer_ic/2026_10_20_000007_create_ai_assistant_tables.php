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
            throw new LogicException('Farmer IC requires a separate PostgreSQL database. Run with --database=pgsql --path=database/migrations/farmer_ic.');
        }

        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('title', 200)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->string('sender', 20);
            $table->text('content')->nullable();
            $table->string('model_name', 100)->nullable();
            $table->string('response_status', 30)->nullable();
            $table->timestampTz('created_at');

            $table->foreign('conversation_id')->references('id')->on('ai_conversations')->cascadeOnDelete();
            $table->index(['conversation_id', 'created_at']);
        });
        DB::statement('ALTER TABLE ai_messages ADD CONSTRAINT ai_messages_sender_allowed CHECK (sender IN (\'user\', \'assistant\', \'system\'))');

        Schema::create('ai_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->string('file_type', 50)->nullable();
            $table->text('file_url');
            $table->timestampTz('created_at');

            $table->foreign('message_id')->references('id')->on('ai_messages')->cascadeOnDelete();
            $table->index('message_id');
        });

        Schema::create('ai_knowledge_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->text('content')->nullable();
            $table->text('file_url')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index('uploaded_by');
            $table->index('created_at');
        });

        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('message_id')->nullable();
            $table->string('model_name', 100)->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->decimal('estimated_cost', 18, 8)->nullable();
            $table->timestampTz('created_at');

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('message_id')->references('id')->on('ai_messages')->nullOnDelete();
            $table->index('message_id');
            $table->index(['user_id', 'created_at']);
        });
        DB::statement('ALTER TABLE ai_usage_logs ADD CONSTRAINT ai_usage_logs_input_tokens_nonnegative CHECK (input_tokens >= 0)');
        DB::statement('ALTER TABLE ai_usage_logs ADD CONSTRAINT ai_usage_logs_output_tokens_nonnegative CHECK (output_tokens >= 0)');
        DB::statement('ALTER TABLE ai_usage_logs ADD CONSTRAINT ai_usage_logs_estimated_cost_nonnegative CHECK (estimated_cost >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_knowledge_documents');
        Schema::dropIfExists('ai_attachments');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};

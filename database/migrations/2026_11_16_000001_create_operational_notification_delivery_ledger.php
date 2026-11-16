<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_notification_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->jsonb('payload');
            $table->timestampTz('queued_at')->nullable();
            $table->string('whatsapp_state', 20)->default('pending');
            $table->timestampTz('created_at');
            $table->index(['queued_at', 'id'], 'operational_delivery_pending_index');
        });
        Schema::create('operational_notification_events', function (Blueprint $table): void {
            $table->string('event_key', 64)->primary();
            $table->foreignUuid('delivery_id')->index()->constrained('operational_notification_deliveries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_notification_events');
        Schema::dropIfExists('operational_notification_deliveries');
    }
};

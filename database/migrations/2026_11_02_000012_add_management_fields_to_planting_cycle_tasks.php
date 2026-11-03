<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planting_cycle_tasks', function (Blueprint $table): void {
            $table->text('cancel_reason')->nullable();
            $table->date('rescheduled_date')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->uuid('rescheduled_by')->nullable();
            $table->timestampTz('rescheduled_at')->nullable();

            $table->foreign('rescheduled_by')->references('id')->on('users')->nullOnDelete();
            $table->index('rescheduled_by');
        });
    }

    public function down(): void
    {
        Schema::table('planting_cycle_tasks', function (Blueprint $table): void {
            $table->dropForeign(['rescheduled_by']);
            $table->dropIndex(['rescheduled_by']);
            $table->dropColumn([
                'cancel_reason',
                'rescheduled_date',
                'reschedule_reason',
                'rescheduled_by',
                'rescheduled_at',
            ]);
        });
    }
};

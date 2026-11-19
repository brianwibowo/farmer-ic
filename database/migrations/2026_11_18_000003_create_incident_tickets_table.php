<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('incident_details')
            ->whereRaw("LOWER(BTRIM(COALESCE(severity, ''))) IN ('heavy', 'high', 'berat')")
            ->update(['severity' => 'emergency']);

        Schema::create('incident_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id');
            $table->uuid('activity_id')->unique();
            $table->uuid('planting_cycle_id')->nullable();
            $table->uuid('block_id')->nullable();
            $table->string('incident_category', 150)->nullable();
            $table->string('severity', 30);
            $table->string('status', 30)->default('open');
            $table->text('admin_note')->nullable();
            $table->uuid('responded_by')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->uuid('resolved_activity_id')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->uuid('reopened_by')->nullable();
            $table->timestampTz('reopened_at')->nullable();
            $table->timestampsTz();

            $table->foreign('farm_id')->references('id')->on('farms')->restrictOnDelete();
            $table->foreign('activity_id')->references('id')->on('farm_activities')->cascadeOnDelete();
            $table->foreign('planting_cycle_id')->references('id')->on('planting_cycles')->restrictOnDelete();
            $table->foreign('block_id')->references('id')->on('farm_blocks')->restrictOnDelete();
            $table->foreign('responded_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_activity_id')->references('id')->on('farm_activities')->nullOnDelete();
            $table->foreign('reopened_by')->references('id')->on('users')->nullOnDelete();

            $table->index('farm_id');
            $table->index('planting_cycle_id');
            $table->index('block_id');
            $table->index('responded_by');
            $table->index('resolved_by');
            $table->index('resolved_activity_id');
            $table->index('reopened_by');
            $table->index(['farm_id', 'status', 'severity']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
ALTER TABLE incident_tickets ADD CONSTRAINT incident_tickets_values_check CHECK (
    severity IN ('light', 'medium', 'emergency')
    AND status IN ('open', 'in_progress', 'resolved')
    AND (resolved_activity_id IS NULL OR resolved_activity_id <> activity_id)
)
SQL);
        }

        $this->backfillExistingIncidents();
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_tickets');
    }

    private function backfillExistingIncidents(): void
    {
        $rows = DB::table('incident_details')
            ->join('farm_activities', 'farm_activities.id', '=', 'incident_details.activity_id')
            ->orderBy('farm_activities.activity_date')
            ->orderBy('farm_activities.id')
            ->select([
                'farm_activities.id as activity_id',
                'farm_activities.farm_id',
                'farm_activities.planting_cycle_id',
                'farm_activities.block_id',
                'farm_activities.status as activity_status',
                'farm_activities.task_details',
                'farm_activities.action_taken',
                'farm_activities.activity_date',
                'farm_activities.created_at',
                'farm_activities.updated_at',
                'incident_details.incident_category',
                'incident_details.severity',
                'incident_details.proposed_solution',
            ])
            ->get();

        foreach ($rows as $row) {
            $taskDetails = is_string($row->task_details) ? json_decode($row->task_details, true) : null;
            $rawStatus = strtolower(str_replace(' ', '_', trim((string) ($row->activity_status ?: ($taskDetails['incident_status'] ?? 'open')))));
            $status = match ($rawStatus) {
                'resolved', 'completed', 'closed', 'sudah_ditangani_langsung' => 'resolved',
                'progress', 'in_progress' => 'in_progress',
                default => 'open',
            };
            $rawSeverity = strtolower(trim((string) ($row->severity ?? '')));
            $severity = match ($rawSeverity) {
                'light', 'low', 'ringan' => 'light',
                'heavy', 'high', 'berat', 'emergency', 'critical', 'darurat' => 'emergency',
                default => 'medium',
            };
            $resolutionNote = $status === 'resolved'
                ? ($row->action_taken ?: ($taskDetails['immediate_action'] ?? $row->proposed_solution))
                : null;

            DB::table('incident_tickets')->insert([
                'id' => (string) Str::uuid(),
                'farm_id' => $row->farm_id,
                'activity_id' => $row->activity_id,
                'planting_cycle_id' => $row->planting_cycle_id,
                'block_id' => $row->block_id,
                'incident_category' => $row->incident_category,
                'severity' => $severity,
                'status' => $status,
                'resolution_note' => $resolutionNote,
                'resolved_at' => $status === 'resolved' ? ($row->updated_at ?? $row->activity_date) : null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? $row->created_at ?? now(),
            ]);
        }
    }
};

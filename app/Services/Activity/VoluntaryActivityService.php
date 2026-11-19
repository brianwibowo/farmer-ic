<?php

namespace App\Services\Activity;

use App\Enums\IncidentSeverity;
use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\IncidentDetail;
use App\Models\IncidentTicket;
use App\Models\PlantingCycle;
use App\Models\User;
use App\Services\IncidentTicketService;
use App\Support\VoluntaryActivityFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class VoluntaryActivityService
{
    public function __construct(
        private readonly DailyActivityHeaderService $headers,
        private readonly ActivitySequenceService $sequence,
        private readonly IncidentTicketService $tickets,
    ) {}

    /** @param array<string, mixed> $data */
    public function record(User $actor, Farm $farm, ActivityType $type, ?PlantingCycle $cycle, array $data, ?FarmBlock $block = null): FarmActivity
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $farm, $type, $cycle, $data, $block, &$paths): FarmActivity {
                $team = $farm->team;
                $submittedBlock = $block;
                $farm = Farm::visibleTo($actor, $team)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
                $type = ActivityType::visibleTo($actor, $team)->whereKey($type->id)->where('is_active', true)->where('is_selectable', true)->firstOrFail();
                if (VoluntaryActivityCategoryService::requiresTask($type->code)) {
                    throw ValidationException::withMessages(['type' => 'Panen hanya dapat dicatat melalui tugas SOP.']);
                }
                if ($submittedBlock !== null) {
                    $submittedBlock = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('is_active', true)->whereKey($submittedBlock->id)->lockForUpdate()->firstOrFail();
                    $block = $submittedBlock;
                }
                if ($cycle !== null) {
                    $cycle = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
                    if (! in_array($cycle->status, ['planned', 'active'], true)) {
                        throw ValidationException::withMessages(['cycle_id' => 'Subblok sudah ditutup.']);
                    }
                    $this->sequence->ensureAllowed($actor, $cycle, $type->code);
                    $blockId = $cycle->getAttribute('block_id');
                    $block = $blockId === null ? null : FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereKey($blockId)->firstOrFail();
                    if ($type->code === 'maintenance'
                        && ($cycle->planted_at === null || $data['work_date'] < $cycle->planted_at->toDateString())) {
                        throw ValidationException::withMessages(['work_date' => 'Perawatan dan panen dicatat pada atau setelah tanggal acuan tanam.']);
                    }
                }
                $ticket = empty($data['incident_ticket_id'])
                    ? null
                    : IncidentTicket::visibleTo($actor, $team)->whereKey($data['incident_ticket_id'])->lockForUpdate()->firstOrFail();
                if ($ticket !== null) {
                    $this->tickets->assertFollowUpTargetMatches($actor, $farm, $ticket, $type, $cycle, $submittedBlock);
                }
                $d = VoluntaryActivityFields::normalized($data['details']);
                if (isset($d['area_ha']) && ($d['area_ha'] <= 0 || ($cycle && $d['area_ha'] > (float) $cycle->planted_area_ha) || (! $cycle && $block && $d['area_ha'] > (float) $block->area_ha))) {
                    throw ValidationException::withMessages(['details.area' => 'Luas harus minimal 1 m² dan tidak melebihi luas lokasi yang dipilih.']);
                }
                $log = $this->headers->resolve($farm, $actor, $data['work_date']);
                $isIncident = $type->detail_key === 'incident';
                $incidentSeverity = $isIncident
                    ? (IncidentSeverity::fromStored((string) ($d['severity'] ?? '')) ?? IncidentSeverity::Medium)
                    : null;
                $incidentStatus = $isIncident
                    ? (($d['incident_status'] ?? 'Perlu penanganan') === 'Sudah ditangani langsung' ? 'resolved' : 'open')
                    : null;
                $immediateAction = $isIncident && filled($d['immediate_action'] ?? null)
                    ? trim((string) $d['immediate_action'])
                    : null;

                $date = $this->headers->activityTimestamp($data['work_date']);
                $activity = new FarmActivity([
                    'farm_id' => $farm->id, 'planting_cycle_id' => $cycle?->id, 'activity_type_id' => $type->id,
                    'performed_by' => $actor->id, 'activity_date' => $date, 'daily_activity_log_id' => $log->id,
                    'position' => $this->headers->nextPosition($log, $actor), 'worker_count' => (int) $data['worker_count'],
                    'description' => $isIncident && ! filled($data['notes'] ?? null) ? ($d['description'] ?? null) : ($data['notes'] ?? null),
                    'obstacle_status' => 'none',
                ]);
                $activity->forceFill([
                    'block_id' => $block?->id,
                    'task_details' => json_encode($d, JSON_THROW_ON_ERROR),
                    ...($isIncident ? ['status' => $incidentStatus, 'action_taken' => $immediateAction] : []),
                ])->save();
                if ($isIncident) {
                    /** @var IncidentDetail $detail */
                    $detail = $activity->incidentDetail()->create([
                        'incident_category' => $d['incident_category'],
                        'severity' => $incidentSeverity->value,
                        'affected_area_ha' => $d['area_ha'],
                        'proposed_solution' => $immediateAction,
                    ]);
                    $this->tickets->createFromIncidentReport($activity, $detail, $d, $actor);
                } else {
                    match ($type->detail_key) {
                        'fertilization' => $activity->fertilizationDetail()->create(['fertilization_stage' => 'kandang', 'area_ha' => $d['area_ha']]),
                        'land_cleaning' => $activity->landCleaningDetail()->create(['area_ha' => $d['area_ha']]),
                        'other' => $activity->otherDetail()->create(['description' => $d['description'], 'area_ha' => $d['area_ha'] ?? null]),
                        default => null,
                    };
                }
                foreach ($data['photos'] as $photo) {
                    $path = $photo->store('task-activities/'.$activity->id, 'local');
                    if ($path === false) {
                        throw new \RuntimeException('Foto tidak berhasil disimpan.');
                    }
                    $paths[] = $path;
                    ActivityPhoto::create(['activity_id' => $activity->id, 'image_url' => $path]);
                }

                app(PlantingCycleStageService::class)->record($actor, $team, $activity, $type);
                if ($ticket !== null) {
                    $this->tickets->resolveFromFollowUp($ticket, $activity, $actor);
                }

                return $activity;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }
    }
}

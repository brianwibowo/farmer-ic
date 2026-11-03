<?php

namespace App\Services\Activity;

use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyActivityLogService
{
    public function __construct(private readonly DailyActivityHeaderService $headers) {}

    /** @return \Illuminate\Support\Collection<int, FarmBlock> */
    public function blockOptions(Farm $farm, User $actor, Team $team): \Illuminate\Support\Collection
    {
        return FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
    }

    /** Actor names are a minimal projection of an authorized report, not a user-directory grant.
     * @return array<string, mixed>
     */
    private function relations(User $actor, Team $team): array
    {
        return [
            'recorder' => fn ($query) => $query->select(['id', 'name']),
            'activities' => fn ($query) => $query->visibleTo($actor, $team)->with([
                'activityType' => fn ($types) => $types->visibleTo($actor, $team),
                'blockMappingDetail' => fn ($details) => $details->visibleTo($actor, $team),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters  Validated list filters.
     * @return LengthAwarePaginator<int, DailyActivityLog>
     */
    public function paginate(Farm $farm, User $user, Team $team, array $filters): LengthAwarePaginator
    {
        $query = DailyActivityLog::visibleTo($user, $team)
            ->where('farm_id', $farm->id)
            ->with($this->relations($user, $team));
        foreach (['from_date' => '>=', 'to_date' => '<='] as $field => $operator) {
            if (! empty($filters[$field])) {
                $query->where('work_date', $operator, $filters[$field]);
            }
        }
        if (! empty($filters['recorded_by'])) {
            $query->where('recorded_by', $filters['recorded_by']);
        }

        return $query->orderByDesc('work_date')->orderByDesc('created_at')->orderBy('id')
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 15))))->appends($filters);
    }

    public function find(Farm $farm, string $id, User $actor): DailyActivityLog
    {
        return DailyActivityLog::visibleTo($actor, $farm->team)->where('farm_id', $farm->id)->with($this->relations($actor, $farm->team))->findOrFail($id);
    }

    /** @param array<string, mixed> $data Validated StoreDailyActivityLogRequest payload. */
    public function create(Farm $farm, array $data): DailyActivityLog
    {
        return DB::transaction(function () use ($farm, $data): DailyActivityLog {
            $recorder = User::query()->where('is_active', true)->whereKey($data['recorded_by'])->sharedLock()->first();
            if (! $recorder) {
                throw ValidationException::withMessages(['recorded_by' => 'Pelapor harus merupakan pengguna aktif.']);
            }
            $farm = Farm::visibleTo($recorder, $farm->team)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
            $type = ActivityType::visibleTo($recorder, $farm->team)->where('code', 'block_mapping')->first();
            if (! $type) {
                throw ValidationException::withMessages(['activities' => 'Referensi Pemetaan Blok belum tersedia. Jalankan BlockMappingActivityTypeSeeder.']);
            }

            $log = $this->headers->resolve($farm, $recorder, [...$data, 'daily_notes' => $data['notes'] ?? null]);
            $startedAt = CarbonImmutable::parse($data['work_date'].' '.$log->start_time, 'Asia/Jakarta');
            $position = $this->headers->nextPosition($log, $recorder);
            foreach ($data['activities'] as $index => $item) {
                if (! empty($item['block_mapping']['block_id'])) {
                    $block = FarmBlock::visibleTo($recorder, $farm->team)->where('farm_id', $farm->id)
                        ->whereKey($item['block_mapping']['block_id'])->first();
                    if (! $block) {
                        throw ValidationException::withMessages(["activities.{$index}.block_mapping.block_id" => 'Blok harus berasal dari kebun laporan dan dapat diakses.']);
                    }
                }
                if ($item['type'] !== 'block_mapping') {
                    throw ValidationException::withMessages(["activities.{$index}.type" => 'Tahap ini hanya mendukung Pemetaan Blok.']);
                }
                $activity = $log->activities()->create([
                    'farm_id' => $farm->id,
                    'performed_by' => $recorder->id,
                    'activity_type_id' => $type->id,
                    'activity_date' => $startedAt,
                    'position' => $position + $index,
                    'description' => $item['notes'] ?? null,
                    'obstacle_status' => $item['obstacle_status'],
                    'obstacles' => $item['obstacles'] ?? null,
                    'proposed_solution' => $item['proposed_solution'] ?? null,
                    'action_taken' => $item['action_taken'] ?? null,
                ]);
                $activity->blockMappingDetail()->create($item['block_mapping']);
            }

            return $log->load($this->relations($recorder, $farm->team));
        });
    }
}

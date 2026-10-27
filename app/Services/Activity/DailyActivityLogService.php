<?php

namespace App\Services\Activity;

use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyActivityLogService
{
    private const RELATIONS = ['recorder:id,name', 'activities.activityType:id,code,name', 'activities.blockMappingDetail'];

    /**
     * @param  array<string, mixed>  $filters  Validated list filters.
     * @return LengthAwarePaginator<int, DailyActivityLog>
     */
    public function paginate(Farm $farm, User $user, Team $team, array $filters): LengthAwarePaginator
    {
        $query = DailyActivityLog::visibleTo($user, $team)
            ->where('farm_id', $farm->id)
            ->with(self::RELATIONS);
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

    public function find(Farm $farm, string $id): DailyActivityLog
    {
        return DailyActivityLog::query()->where('farm_id', $farm->id)->with(self::RELATIONS)->findOrFail($id);
    }

    /** @param array<string, mixed> $data Validated StoreDailyActivityLogRequest payload. */
    public function create(Farm $farm, array $data): DailyActivityLog
    {
        return DB::transaction(function () use ($farm, $data): DailyActivityLog {
            Farm::query()->sharedLock()->findOrFail($farm->id);
            $recorder = User::query()->where('is_active', true)->whereKey($data['recorded_by'])->sharedLock()->first();
            if (! $recorder) {
                throw ValidationException::withMessages(['recorded_by' => 'Pelapor harus merupakan pengguna aktif.']);
            }
            $type = ActivityType::query()->where('code', 'block_mapping')->first();
            if (! $type) {
                throw ValidationException::withMessages(['activities' => 'Referensi Pemetaan Blok belum tersedia. Jalankan BlockMappingActivityTypeSeeder.']);
            }

            $log = DailyActivityLog::create([
                'farm_id' => $farm->id,
                'recorded_by' => $recorder->id,
                'work_date' => $data['work_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'worker_count' => $data['worker_count'],
                'notes' => $data['notes'] ?? null,
            ]);
            $startedAt = CarbonImmutable::parse($data['work_date'].' '.$data['start_time'], 'Asia/Jakarta');
            foreach ($data['activities'] as $index => $item) {
                if ($item['type'] !== 'block_mapping') {
                    throw ValidationException::withMessages(["activities.{$index}.type" => 'Tahap ini hanya mendukung Pemetaan Blok.']);
                }
                $activity = $log->activities()->create([
                    'farm_id' => $farm->id,
                    'performed_by' => $recorder->id,
                    'activity_type_id' => $type->id,
                    'activity_date' => $startedAt,
                    'position' => $index + 1,
                    'description' => $item['notes'] ?? null,
                    'obstacle_status' => $item['obstacle_status'],
                    'obstacles' => $item['obstacles'] ?? null,
                    'proposed_solution' => $item['proposed_solution'] ?? null,
                    'action_taken' => $item['action_taken'] ?? null,
                ]);
                $activity->blockMappingDetail()->create($item['block_mapping']);
            }

            return $log->load(self::RELATIONS);
        });
    }
}

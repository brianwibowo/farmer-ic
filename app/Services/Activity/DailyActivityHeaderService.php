<?php

namespace App\Services\Activity;

use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DailyActivityHeaderService
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'daily_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function forDate(Farm $farm, User $actor, string $date): ?DailyActivityLog
    {
        return DailyActivityLog::visibleTo($actor, $farm->team)->where('farm_id', $farm->id)->whereDate('work_date', $date)->first();
    }

    /** Caller authorizes report creation and holds the farm's exclusive lock.
     * @param  array<string, mixed>  $data
     */
    public function resolve(Farm $farm, User $actor, array $data): DailyActivityLog
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Daily header resolution requires a transaction and farm lock.');
        }
        $existing = DailyActivityLog::visibleTo($actor, $farm->team)->where('farm_id', $farm->id)->whereDate('work_date', $data['work_date'])->lockForUpdate()->first();
        if ($existing !== null) {
            return $existing; // Never overwrite shared data from an activity form.
        }
        $header = Validator::make($data, self::rules())->validate();

        return DailyActivityLog::create([
            'farm_id' => $farm->id, 'work_date' => $data['work_date'], 'recorded_by' => $actor->id,
            'start_time' => $header['start_time'], 'end_time' => $header['end_time'] ?? null,
            'notes' => $header['daily_notes'] ?? null,
        ]);
    }

    /** Called under the same farm/header lock as resolve(). */
    public function nextPosition(DailyActivityLog $log, User $actor): int
    {
        return ((int) FarmActivity::visibleTo($actor, $log->farm->team)->where('daily_activity_log_id', $log->id)->max('position')) + 1;
    }

    /** @param array<string, mixed> $data */
    public function update(DailyActivityLog $log, User $actor, array $data): void
    {
        DB::transaction(function () use ($log, $actor, $data): void {
            $farm = Farm::visibleTo($actor, $log->farm->team)->whereKey($log->farm_id)->lockForUpdate()->firstOrFail();
            $locked = DailyActivityLog::visibleTo($actor, $farm->team)->whereKey($log->id)->lockForUpdate()->firstOrFail();
            $header = Validator::make($data, self::rules())->validate();
            $locked->update([
                'start_time' => $header['start_time'], 'end_time' => $header['end_time'] ?? null,
                'notes' => $header['daily_notes'] ?? null,
            ]);
        });
    }
}

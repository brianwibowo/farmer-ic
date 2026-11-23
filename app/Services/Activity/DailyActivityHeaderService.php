<?php

namespace App\Services\Activity;

use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DailyActivityHeaderService
{
    public function forDate(Farm $farm, User $actor, string $date): ?DailyActivityLog
    {
        return DailyActivityLog::visibleTo($actor, $farm->team)->where('farm_id', $farm->id)->whereDate('work_date', $date)->first();
    }

    /** Caller authorizes report creation and holds the farm's exclusive lock. */
    public function resolve(Farm $farm, User $actor, string $workDate): DailyActivityLog
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Daily log resolution requires a transaction and farm lock.');
        }
        $existing = DailyActivityLog::visibleTo($actor, $farm->team)->where('farm_id', $farm->id)->whereDate('work_date', $workDate)->lockForUpdate()->first();
        if ($existing !== null) {
            return $existing; // Never overwrite the initial recorder from a subsequent activity form.
        }

        return DailyActivityLog::create([
            'farm_id' => $farm->id,
            'work_date' => $workDate,
            'recorded_by' => $actor->id,
        ]);
    }

    public function activityTimestamp(string $workDate): CarbonImmutable
    {
        $now = CarbonImmutable::now('Asia/Jakarta');

        return $workDate === $now->toDateString()
            ? $now
            : CarbonImmutable::parse($workDate.' 08:00:00', 'Asia/Jakarta');
    }

    /** Called under the same farm/log lock as resolve(). */
    public function nextPosition(DailyActivityLog $log, User $actor): int
    {
        return ((int) FarmActivity::visibleTo($actor, $log->farm->team)->where('daily_activity_log_id', $log->id)->max('position')) + 1;
    }
}

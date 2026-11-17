<?php

namespace App\Services\Activity;

use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\HarvestDetail;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Support\Facades\DB;

/**
 * Repairs only the identifiable harvest fixture from the original demo seeder.
 * It never creates a daily header or supplies working hours.
 */
class LegacyDemoHarvestRepairService
{
    /** @return array{status: 'not_matching'|'already_linked'|'missing_header'|'linked', log_id: string|null} */
    public function repair(User $actor, FarmActivity $target, ?DailyActivityLog $header): array
    {
        return DB::transaction(function () use ($actor, $target, $header): array {
            $team = $target->farm->team;
            if (! $team instanceof Team || $team->slug !== 'farm-ic-demo' || $team->is_personal) {
                return ['status' => 'not_matching', 'log_id' => null];
            }

            $farm = Farm::visibleTo($actor, $team)->whereKey($target->farm_id)->lockForUpdate()->firstOrFail();
            $activity = FarmActivity::visibleTo($actor, $team)->whereKey($target->id)
                ->where('farm_id', $farm->id)->lockForUpdate()->firstOrFail();
            $type = ActivityType::visibleTo($actor, $team)->whereKey($activity->activity_type_id)->first();
            $detail = HarvestDetail::visibleTo($actor, $team)->where('activity_id', $activity->id)->lockForUpdate()->first();
            if (! in_array($farm->name, DemoFarmSeeder::FARM_NAMES, true)
                || $actor->email !== DemoIdentitySeeder::ACCOUNTS['petugas']['email']
                || $activity->performed_by !== $actor->id
                || $type?->code !== 'harvest'
                || $activity->description !== 'Panen percobaan Blok 2'
                || $activity->status !== 'completed'
                || $activity->activity_date->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s') !== '2026-12-15 09:00:00'
                || ! $detail instanceof HarvestDetail
                || $detail->harvested_area_ha !== '0.5000'
                || $detail->gross_weight_ton !== '2.750'
                || $detail->moisture_percentage !== '18.50'
                || $detail->color !== 'Kuning cerah'
                || $detail->ripeness !== 'Matang panen'
                || $detail->cleanliness !== 'Bersih') {
                return ['status' => 'not_matching', 'log_id' => null];
            }

            if ($activity->daily_activity_log_id !== null) {
                return ['status' => 'already_linked', 'log_id' => $activity->daily_activity_log_id];
            }

            $log = DailyActivityLog::visibleTo($actor, $team)->where('farm_id', $farm->id)
                ->whereKey($header?->id)->whereDate('work_date', '2026-12-15')->lockForUpdate()->first();
            if ($log === null) {
                return ['status' => 'missing_header', 'log_id' => null];
            }
            $activity->forceFill([
                'daily_activity_log_id' => $log->id,
                'position' => app(DailyActivityHeaderService::class)->nextPosition($log, $actor),
            ])->save();

            return ['status' => 'linked', 'log_id' => $log->id];
        });
    }
}

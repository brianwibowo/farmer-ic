<?php

namespace App\Http\Requests\Activity;

use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Services\Activity\DailyActivityHeaderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateDailyActivityLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $farm = $this->route('farm');
        if (! $farm instanceof Farm || ! Gate::allows('view', $farm)) {
            return false;
        }
        $log = DailyActivityLog::visibleTo($this->user(), $farm->team)->where('farm_id', $farm->id)->whereKey($this->route('dailyLog'))->first();

        // Early policy delegation rejects invalid/foreign writes before validation.
        // The controller retains its explicit HTTP authorization boundary.
        return $log !== null && Gate::allows('update', $log);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return DailyActivityHeaderService::rules() + array_fill_keys([
            'farm_id', 'work_date', 'recorded_by', 'activities', 'performed_by', 'id',
        ], ['prohibited']);
    }
}

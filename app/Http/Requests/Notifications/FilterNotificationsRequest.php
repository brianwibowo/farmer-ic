<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'status' => ['sometimes', Rule::in(['all', 'unread', 'read'])], 'category' => ['sometimes', Rule::in(['all', 'weekly_report', 'task_reminder', 'sop_deviation', 'incident_report', 'subblock_cancelled'])]];
    }
}

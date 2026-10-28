<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use App\Rules\TeamName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            ? Gate::allows('update', $team)
            : Gate::allows('create', Team::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new TeamName],
        ];
    }
}

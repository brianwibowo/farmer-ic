<?php

namespace App\Http\Requests\Finance;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class FinanceRequest extends FormRequest
{
    public function team(): Team
    {
        $team = $this->user()?->currentTeam;
        abort_unless($team instanceof Team, 403, 'Pilih tim aktif untuk mengakses keuangan.');

        return $team;
    }

    public function authorize(): bool
    {
        // Controllers authorize the resolved resource or persisted parent.
        return true;
    }

    /** @return list<string> */
    protected function presence(): array
    {
        return $this->isMethod('patch') ? ['sometimes', 'required'] : ['required'];
    }

    /** @return list<string> */
    protected function money(bool $positive = false): array
    {
        // PostgreSQL numeric(18,2): accept plain decimals without float rounding.
        return ['numeric', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D', $positive ? 'gt:0' : 'gte:0'];
    }

    /** @return array<string, list<string>> */
    protected function serverOwned(): array
    {
        return array_fill_keys([
            'id', 'farm_id', 'team_id', 'user_id', 'recorded_by', 'created_by',
            'created_at', 'updated_at', 'calculated_at',
        ], ['prohibited']);
    }
}

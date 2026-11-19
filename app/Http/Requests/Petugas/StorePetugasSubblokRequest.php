<?php

namespace App\Http\Requests\Petugas;

use App\Http\Requests\Blocks\StorePreparationBatchRequest;

class StorePetugasSubblokRequest extends StorePreparationBatchRequest
{
    /** @return list<string> */
    protected function allowedReturnTargets(): array
    {
        return ['kebun', 'penanaman'];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return parent::rules() + [
            'work_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
        ];
    }
}

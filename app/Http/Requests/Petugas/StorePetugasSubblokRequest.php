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
}

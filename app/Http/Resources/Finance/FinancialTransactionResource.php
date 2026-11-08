<?php

namespace App\Http\Resources\Finance;

use App\Models\FinancialTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialTransaction */
class FinancialTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'planting_cycle_id' => $this->planting_cycle_id,
            'category_id' => $this->category_id,
            'recorded_by' => $this->recorded_by,
            'transaction_date' => $this->transaction_date->toDateString(),
            'transaction_type' => $this->transaction_type,
            'amount' => $this->amount,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

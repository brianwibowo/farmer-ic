<?php

namespace App\Models\Farmer;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $transaction_type
 */
#[Fillable(['name', 'transaction_type'])]
class FinancialCategory extends FarmerModel
{
    protected $table = 'financial_categories';

    /** @return HasMany<FinancialTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'category_id');
    }

    /** @return HasMany<FinancialBudget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(FinancialBudget::class, 'category_id');
    }
}

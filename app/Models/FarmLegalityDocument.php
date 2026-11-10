<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['farm_legality_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes'])]
class FarmLegalityDocument extends FarmerModel
{
    public $timestamps = true;

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    /** @return BelongsTo<FarmLegality, $this> */
    public function legality(): BelongsTo
    {
        return $this->belongsTo(FarmLegality::class, 'farm_legality_id');
    }
}

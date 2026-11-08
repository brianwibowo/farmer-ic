<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $form_version
 * @property string $block_name
 * @property string $length_m
 * @property string $width_m
 * @property string|null $row_spacing_cm
 * @property string|null $plant_spacing_cm
 * @property int|null $rows_per_bed
 */
#[Fillable(['block_id', 'block_name', 'length_m', 'width_m', 'row_spacing_cm', 'plant_spacing_cm', 'rows_per_bed'])]
class BlockMappingDetail extends ActivityDetail
{
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<FarmBlock, $this> */
    public function block(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FarmBlock::class, 'block_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'form_version' => 'integer', 'length_m' => 'decimal:3', 'width_m' => 'decimal:3',
            'row_spacing_cm' => 'decimal:2', 'plant_spacing_cm' => 'decimal:2', 'rows_per_bed' => 'integer',
        ];
    }
}

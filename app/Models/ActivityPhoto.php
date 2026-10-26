<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $activity_id
 * @property string|null $label
 * @property string|null $caption
 * @property string $image_url
 * @property CarbonImmutable|null $uploaded_at
 */
#[Fillable(['activity_id', 'label', 'caption', 'image_url', 'uploaded_at'])]
class ActivityPhoto extends FarmerModel
{
    protected $table = 'activity_photos';

    public $timestamps = true;

    public const UPDATED_AT = null;

    public const CREATED_AT = 'uploaded_at';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<FarmActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(FarmActivity::class, 'activity_id');
    }
}

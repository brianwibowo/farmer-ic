<?php

namespace App\Models;

use App\Concerns\HasVisibleScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The primary key is the parent activity UUID, never a newly generated UUID.
 *
 * @property string $activity_id
 */
abstract class ActivityDetail extends Model
{
    use HasVisibleScope;

    protected $primaryKey = 'activity_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** @return BelongsTo<FarmActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(FarmActivity::class, 'activity_id');
    }
}

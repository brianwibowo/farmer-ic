<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $title
 * @property string|null $message
 * @property bool $is_read
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $read_at
 */
#[Fillable(['user_id', 'title', 'message', 'is_read', 'read_at'])]
class Notification extends FarmerModel
{
    protected $table = 'notifications';

    public $timestamps = true;

    public const UPDATED_AT = null;

    protected $attributes = ['is_read' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'created_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

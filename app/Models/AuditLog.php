<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string|null $table_name
 * @property string|null $record_id
 * @property string|null $action
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['user_id', 'table_name', 'record_id', 'action', 'old_values', 'new_values'])]
class AuditLog extends FarmerModel
{
    protected $table = 'audit_logs';

    public $timestamps = true;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<FarmUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(FarmUser::class, 'user_id');
    }
}

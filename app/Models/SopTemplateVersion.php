<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $sop_template_id
 * @property int $version
 * @property string|null $notes
 * @property string $status
 * @property string $created_by
 * @property string|null $published_by
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['sop_template_id', 'version', 'notes', 'status', 'created_by', 'published_by', 'published_at'])]
class SopTemplateVersion extends FarmerModel
{
    protected $table = 'sop_template_versions';

    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<SopTemplate, $this> */
    public function sopTemplate(): BelongsTo
    {
        return $this->belongsTo(SopTemplate::class, 'sop_template_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<SopTemplateTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(SopTemplateTask::class, 'sop_template_version_id')
            ->orderBy('offset_days')
            ->orderByRaw("CASE activity_category WHEN 'planting' THEN 1 WHEN 'maintenance' THEN 2 WHEN 'harvest' THEN 3 ELSE 4 END")
            ->orderBy('created_at')
            ->orderBy('id');
    }
}

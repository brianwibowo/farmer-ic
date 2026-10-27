<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $created_by
 * @property string|null $reviewed_by
 * @property string $title
 * @property string $content
 * @property string $status
 * @property string|null $source
 * @property string $locale
 * @property string|null $domain
 * @property string $index_status
 * @property string|null $index_version
 * @property Carbon|null $indexed_at
 * @property string|null $index_error
 * @property string $content_hash
 * @property int $version
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'team_id',
    'created_by',
    'reviewed_by',
    'title',
    'content',
    'status',
    'source',
    'locale',
    'domain',
    'index_status',
    'index_version',
    'indexed_at',
    'index_error',
    'content_hash',
    'version',
    'published_at',
])]
class AiKnowledgeDocument extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const INDEX_STATUS_PENDING = 'pending';

    public const INDEX_STATUS_INDEXING = 'indexing';

    public const INDEX_STATUS_INDEXED = 'indexed';

    public const INDEX_STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'datetime',
            'indexed_at' => 'datetime',
        ];
    }

    /** @return HasMany<AiKnowledgeChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(AiKnowledgeChunk::class)->orderBy('chunk_index');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

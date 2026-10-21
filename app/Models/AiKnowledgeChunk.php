<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ai_knowledge_document_id
 * @property int $team_id
 * @property int $chunk_index
 * @property string $content
 * @property string $content_hash
 * @property string $chunking_version
 * @property string|null $embedding_model
 * @property string|null $qdrant_point_id
 * @property int|null $token_count
 * @property array<string, mixed>|null $metadata
 * @property string $status
 * @property Carbon|null $indexed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'ai_knowledge_document_id',
    'team_id',
    'chunk_index',
    'content',
    'content_hash',
    'chunking_version',
    'embedding_model',
    'qdrant_point_id',
    'token_count',
    'metadata',
    'status',
    'indexed_at',
])]
class AiKnowledgeChunk extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_INDEXED = 'indexed';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'token_count' => 'integer',
            'metadata' => 'array',
            'indexed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AiKnowledgeDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeDocument::class, 'ai_knowledge_document_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}

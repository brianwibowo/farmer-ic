<?php

namespace App\Models;

use Database\Factories\AiAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $ai_conversation_id
 * @property string|null $ai_message_id
 * @property string $user_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $sha256
 * @property string $kind
 * @property string $status
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'ai_conversation_id',
    'ai_message_id',
    'user_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
    'sha256',
    'kind',
    'status',
    'metadata',
])]
class AiAttachment extends Model
{
    /** @use HasFactory<AiAttachmentFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /** @return BelongsTo<AiMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

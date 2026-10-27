<?php

namespace App\Models;

use App\Concerns\HasVisibleScope;
use Database\Factories\AiMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $ai_conversation_id
 * @property string|null $user_id
 * @property string $role
 * @property string|null $content
 * @property string $status
 * @property string|null $provider
 * @property string|null $model
 * @property string|null $request_id
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property int|null $latency_ms
 * @property string|null $error_message
 * @property array<string, mixed>|null $metadata
 * @property-read AiConversation $conversation
 * @property-read User|null $user
 * @property-read Collection<int, AiAttachment> $attachments
 */
#[Fillable([
    'ai_conversation_id',
    'user_id',
    'role',
    'content',
    'status',
    'provider',
    'model',
    'request_id',
    'input_tokens',
    'output_tokens',
    'latency_ms',
    'error_message',
    'metadata',
])]
class AiMessage extends Model
{
    /** @use HasFactory<AiMessageFactory> */
    use HasFactory, HasUuids;

    use HasVisibleScope;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AiAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(AiAttachment::class, 'ai_message_id');
    }
}

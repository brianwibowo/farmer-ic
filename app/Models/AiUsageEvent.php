<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'user_id',
    'ai_conversation_id',
    'ai_message_id',
    'provider',
    'model',
    'event_type',
    'request_id',
    'input_tokens',
    'output_tokens',
    'latency_ms',
    'metadata',
])]
class AiUsageEvent extends Model
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}

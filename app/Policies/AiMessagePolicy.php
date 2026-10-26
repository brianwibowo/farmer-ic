<?php

namespace App\Policies;

use App\Models\AiConversation;

class AiMessagePolicy extends ResourcePolicy
{
    protected string $resource = 'ai_messages';

    protected ?string $teamPath = 'conversation.team';

    protected bool $ai = true;

    protected array $parents = [AiConversation::class];
}

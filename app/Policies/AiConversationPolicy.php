<?php

namespace App\Policies;

class AiConversationPolicy extends ResourcePolicy
{
    protected string $resource = 'ai_conversations';

    protected ?string $teamPath = 'team';

    protected bool $ai = true;
}

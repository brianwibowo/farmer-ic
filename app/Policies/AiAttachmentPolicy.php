<?php

namespace App\Policies;

use App\Models\AiConversation;

class AiAttachmentPolicy extends ResourcePolicy
{
    protected string $resource = 'ai_attachments';

    protected ?string $teamPath = 'conversation.team';

    protected bool $ai = true;

    protected array $parents = [AiConversation::class];
}

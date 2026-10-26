<?php

namespace App\Policies;

class AiUsageEventPolicy extends ResourcePolicy
{
    protected string $resource = 'ai_usage_events';

    protected ?string $teamPath = 'team';

    protected bool $ai = true;
}

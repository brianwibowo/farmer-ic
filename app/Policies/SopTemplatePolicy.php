<?php

namespace App\Policies;

use App\Models\Team;

class SopTemplatePolicy extends ResourcePolicy
{
    protected string $resource = 'sop_templates';

    protected ?string $teamPath = 'team';

    protected array $parents = [Team::class];
}

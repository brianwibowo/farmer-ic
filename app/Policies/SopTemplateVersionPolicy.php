<?php

namespace App\Policies;

use App\Models\SopTemplate;

class SopTemplateVersionPolicy extends ResourcePolicy
{
    protected string $resource = 'sop_template_versions';

    protected ?string $teamPath = 'sopTemplate.team';

    protected array $parents = [SopTemplate::class];
}

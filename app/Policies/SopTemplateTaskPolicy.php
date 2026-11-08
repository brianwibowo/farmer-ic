<?php

namespace App\Policies;

use App\Models\SopTemplateVersion;

class SopTemplateTaskPolicy extends ResourcePolicy
{
    protected string $resource = 'sop_template_tasks';

    protected ?string $teamPath = 'version.sopTemplate.team';

    protected array $parents = [SopTemplateVersion::class];
}

<?php

namespace App\Policies;

use App\Models\SopTemplateVersion;

class SopTemplateTaskPolicy extends ResourcePolicy
{
    protected string $resource = 'sop_template_tasks';

    protected ?string $farmPath = 'version.sopTemplate.farm';

    protected array $parents = [SopTemplateVersion::class];
}

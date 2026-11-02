<?php

namespace App\Policies;

use App\Models\Farm;

class SopTemplatePolicy extends ResourcePolicy
{
    protected string $resource = 'sop_templates';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}

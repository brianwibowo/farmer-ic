<?php

namespace App\Policies;

use App\Models\Commodity;

class CropVarietyPolicy extends ResourcePolicy
{
    protected string $resource = 'crop_varieties';

    protected bool $global = true;

    protected array $parents = [Commodity::class];
}

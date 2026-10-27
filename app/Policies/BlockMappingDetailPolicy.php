<?php

namespace App\Policies;

use App\Models\FarmActivity;

class BlockMappingDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'block_mapping_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}

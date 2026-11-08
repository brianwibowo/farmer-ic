<?php

namespace App\Policies;

use App\Models\Farm;

class DailyActivityLogPolicy extends ResourcePolicy
{
    protected string $resource = 'daily_activity_logs';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}

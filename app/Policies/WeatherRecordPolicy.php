<?php

namespace App\Policies;

use App\Models\Farm;

class WeatherRecordPolicy extends ResourcePolicy
{
    protected string $resource = 'weather_records';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}

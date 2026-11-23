<?php

use App\Providers\ActivityTimingServiceProvider;
use App\Providers\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    ActivityTimingServiceProvider::class,
    AiServiceProvider::class,
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    FortifyServiceProvider::class,
];

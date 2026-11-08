<?php

use App\Providers\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AiServiceProvider::class,
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    FortifyServiceProvider::class,
];

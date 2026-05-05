<?php

use App\Providers\AccessControlServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\EngagementServiceProvider;

return [
    AppServiceProvider::class,
    AccessControlServiceProvider::class,
    EngagementServiceProvider::class,
];

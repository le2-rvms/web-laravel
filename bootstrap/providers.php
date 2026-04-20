<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\DysmsapiProvider;
use App\Providers\EventServiceProvider;
use App\Providers\OcrServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    OcrServiceProvider::class,
    DysmsapiProvider::class,
];

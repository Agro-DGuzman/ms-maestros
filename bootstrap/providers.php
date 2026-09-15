<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\CoreServiceProvider;
use App\Providers\ModulosServiceProvider;
use BackOffice\BackOfficeServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    ModulosServiceProvider::class,
    BackOfficeServiceProvider::class,
];

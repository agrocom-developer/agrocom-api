<?php

use App\Dominios\Comercial\Infraestructura\ComercialServiceProvider;
use App\Dominios\Distribucion\Infraestructura\DistribucionServiceProvider;
use App\Dominios\Operaciones\Infraestructura\OperacionesServiceProvider;
use App\Dominios\Personal\Infraestructura\PersonalServiceProvider;
use App\Dominios\Seguridad\Infraestructura\SeguridadServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SeguridadServiceProvider::class,
    ComercialServiceProvider::class,
    OperacionesServiceProvider::class,
    PersonalServiceProvider::class,
    DistribucionServiceProvider::class,
];

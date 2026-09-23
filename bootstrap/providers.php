<?php

use App\Dominios\Campania\Infraestructura\CampaniaServiceProvider;
use App\Dominios\Comercial\Infraestructura\ComercialServiceProvider;
use App\Dominios\Compartido\Infraestructura\CompartidoServiceProvider;
use App\Dominios\Distribucion\Infraestructura\DistribucionServiceProvider;
use App\Dominios\Finanzas\Infraestructura\FinanzasServiceProvider;
use App\Dominios\Inventario\Infraestructura\InventarioServiceProvider;
use App\Dominios\Mantenimiento\Infraestructura\MantenimientoServiceProvider;
use App\Dominios\Mezclas\Infraestructura\MezclasServiceProvider;
use App\Dominios\Notificaciones\Infraestructura\NotificacionesServiceProvider;
use App\Dominios\Operaciones\Infraestructura\OperacionesServiceProvider;
use App\Dominios\Personal\Infraestructura\PersonalServiceProvider;
use App\Dominios\Portal\Infraestructura\PortalServiceProvider;
use App\Dominios\Seguridad\Infraestructura\SeguridadServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CompartidoServiceProvider::class,
    SeguridadServiceProvider::class,
    ComercialServiceProvider::class,
    OperacionesServiceProvider::class,
    MezclasServiceProvider::class,
    PersonalServiceProvider::class,
    DistribucionServiceProvider::class,
    FinanzasServiceProvider::class,
    MantenimientoServiceProvider::class,
    InventarioServiceProvider::class,
    PortalServiceProvider::class,
    CampaniaServiceProvider::class,
    NotificacionesServiceProvider::class,
];

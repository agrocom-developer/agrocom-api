<?php

use App\Dominios\Calificaciones\Infraestructura\CalificacionesServiceProvider;
use App\Dominios\Compartido\Infraestructura\CompartidoServiceProvider;
use App\Dominios\Disputas\Infraestructura\DisputasServiceProvider;
use App\Dominios\Flota\Infraestructura\FlotaServiceProvider;
use App\Dominios\Mantenimiento\Infraestructura\MantenimientoServiceProvider;
use App\Dominios\Metricas\Infraestructura\MetricasServiceProvider;
use App\Dominios\Mezclas\Infraestructura\MezclasServiceProvider;
use App\Dominios\Negociacion\Infraestructura\NegociacionServiceProvider;
use App\Dominios\Notificaciones\Infraestructura\NotificacionesServiceProvider;
use App\Dominios\Operaciones\Infraestructura\OperacionesServiceProvider;
use App\Dominios\Personal\Infraestructura\PersonalServiceProvider;
use App\Dominios\Proveedores\Infraestructura\ProveedoresServiceProvider;
use App\Dominios\Seguridad\Infraestructura\SeguridadServiceProvider;
use App\Dominios\Solicitudes\Infraestructura\SolicitudesServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CompartidoServiceProvider::class,
    SeguridadServiceProvider::class,
    OperacionesServiceProvider::class,
    MezclasServiceProvider::class,
    PersonalServiceProvider::class,
    MantenimientoServiceProvider::class,
    NotificacionesServiceProvider::class,
    ProveedoresServiceProvider::class,
    FlotaServiceProvider::class,
    SolicitudesServiceProvider::class,
    NegociacionServiceProvider::class,
    CalificacionesServiceProvider::class,
    DisputasServiceProvider::class,
    MetricasServiceProvider::class,
];

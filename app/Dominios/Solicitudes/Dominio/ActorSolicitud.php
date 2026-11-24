<?php

namespace App\Dominios\Solicitudes\Dominio;

/**
 * Quién produjo un cambio en una solicitud: el cliente, el proveedor, un
 * administrador o el sistema (por ejemplo, el vencimiento de una solicitud o la
 * sincronización del lote). Es el `origen` del historial y, al cancelar, el
 * valor de `cancelada_por`.
 */
enum ActorSolicitud: string
{
    case Cliente = 'cliente';
    case Proveedor = 'proveedor';
    case Administrador = 'administrador';
    case Sistema = 'sistema';
}

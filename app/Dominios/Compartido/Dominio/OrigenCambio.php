<?php

namespace App\Dominios\Compartido\Dominio;

/**
 * Quién produjo un cambio de estado de aprobación: el administrador, el propio
 * solicitante (reenvío tras un rechazo) o el sistema (suspensión automática
 * por documento vencido y su levantamiento al cargar uno vigente).
 */
enum OrigenCambio: string
{
    case Administrador = 'administrador';
    case Solicitante = 'solicitante';
    case Sistema = 'sistema';
}

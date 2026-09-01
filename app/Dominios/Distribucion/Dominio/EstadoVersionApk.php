<?php

namespace App\Dominios\Distribucion\Dominio;

/**
 * Estado de una versión del APK (HU-20). `Rechazada` es un juicio explícito
 * sobre una versión con problemas — nunca el efecto colateral de autorizar
 * otra: cuando se autoriza una versión nueva, la anterior vuelve a
 * `Pendiente` (ver {@see TransicionesVersionApk}), no a `Rechazada`.
 */
enum EstadoVersionApk: string
{
    case Pendiente = 'pendiente';
    case Autorizada = 'autorizada';
    case Rechazada = 'rechazada';
}

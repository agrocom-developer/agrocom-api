<?php

namespace App\Dominios\Asignacion\Dominio;

/** Quién generó la oferta: el motor o un administrador que fuerza una asignación (ADR 0030, punto 6). */
enum OrigenOferta: string
{
    case Motor = 'motor';
    case Administrador = 'administrador';
}

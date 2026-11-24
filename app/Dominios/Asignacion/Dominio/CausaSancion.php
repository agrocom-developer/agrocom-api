<?php

namespace App\Dominios\Asignacion\Dominio;

/** Por qué se aplicó una sanción. */
enum CausaSancion: string
{
    case Cancelaciones = 'cancelaciones';
    case Calificacion = 'calificacion';
    case Aceptacion = 'aceptacion';
}

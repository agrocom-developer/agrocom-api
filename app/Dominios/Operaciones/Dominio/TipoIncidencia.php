<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Catálogo cerrado de la espec (§4.3, fila `incidencias`; HU-08, tarea 22).
 * Sin máquina de estados: es una clasificación fija, no algo que transicione
 * — mismo criterio que {@see TipoEvidencia}.
 */
enum TipoIncidencia: string
{
    case Caldo = 'caldo';
    case Esc = 'esc';
    case Bateria = 'bateria';
    case Mecanica = 'mecanica';
    case Clima = 'clima';
    case Otro = 'otro';
}

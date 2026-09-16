<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Duración del desarrollo de la planta (catálogo `com_cultivos`, ampliación
 * 16/9/2026 a HU-48/tarea 71): CHECK en `com_cultivos.ciclo_vida` — mismo
 * criterio que `TipoCultivo`.
 */
enum CicloVidaCultivo: string
{
    case Anual = 'anual';
    case Bienal = 'bienal';
    case Perenne = 'perenne';
}

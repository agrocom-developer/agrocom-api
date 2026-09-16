<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Clasificación agronómica del cultivo (catálogo `com_cultivos`, ampliación
 * 16/9/2026 a HU-48/tarea 71): CHECK en `com_cultivos.tipo_cultivo`. Grano
 * suficiente para agrupar el catálogo (informe/summary de cultivos) sin
 * pretender una taxonomía botánica completa — mismo criterio que
 * `TipoPersonaCliente`. `Forrajera` es el tipo de "Pasto" (memoria "la
 * mezcla es del cliente": el sistema clasifica y registra volumen, nunca
 * compone el caldo).
 */
enum TipoCultivo: string
{
    case Cereal = 'cereal';
    case Oleaginosa = 'oleaginosa';
    case Leguminosa = 'leguminosa';
    case Forrajera = 'forrajera';
    case Horticola = 'horticola';
    case Frutal = 'frutal';
    case Otro = 'otro';
}

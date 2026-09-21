<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * En qué etapa de su ciclo está el cultivo de un lote (`com_lote_campania`,
 * 21/9/2026, pedido directo): cuando el servicio es sobre un cultivo en pie,
 * Agrocom no lo siembra — solo deja constancia de qué hay en el lote y en qué
 * momento del ciclo está, porque de eso depende la aplicación que se pide
 * (el cliente suele llamar con los primeros brotes). Es un dato que se
 * registra y se corrige a mano, no una máquina de estados: no hay
 * transiciones que guardar. CHECK en `com_lote_campania.etapa_cultivo` —
 * mismo criterio que `CicloVidaCultivo`.
 *
 * Una misma propiedad puede tener el mismo cultivo en todas las etapas a la
 * vez: la caña se escalona para que haya zafra todo el año —un sector en
 * cosecha, otro germinando, otro tierno, otro con el terreno en limpieza
 * para la nueva siembra—. Por eso la etapa es del LOTE en la campaña, no del
 * cultivo, y por eso un contrato junta lotes del mismo cultivo y la misma
 * etapa: cada etapa pide un trabajo distinto.
 *
 * `Preparacion` admite el caso de sólidos (siembra de semilla de pasto): el
 * lote ya está destinado a un cultivo, pero el terreno todavía está limpio.
 *
 * El orden de los casos es el del ciclo: así se ofrecen en el formulario.
 */
enum EtapaCultivo: string
{
    case Preparacion = 'preparacion';
    case Germinacion = 'germinacion';
    case Crecimiento = 'crecimiento';
    case Floracion = 'floracion';
    case Fructificacion = 'fructificacion';
    case Cosecha = 'cosecha';
}

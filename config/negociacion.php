<?php

/*
|--------------------------------------------------------------------------
| Negociación oferta-demanda (`docs/especificacion/negociacion-contrato.md`)
|--------------------------------------------------------------------------
|
| El cliente abre una solicitud con un precio por hectárea; los pilotos
| aceptan ese precio o contraofertan; el cliente elige. Los valores de aquí
| son el respaldo y no hay secretos. Los importes son cadenas decimales
| exactas (invariante 6 de CLAUDE.md).
|
*/

return [
    /*
     * La solicitud no vence sola: sigue en el mercado hasta que se adjudica o el
     * cliente la cancela (ADR 0045, punto 20). No hay vigencia ni plazo de renovación.
     */

    /** Hasta cuántos días en el futuro puede pedirse la fecha deseada (validación de sanidad; no vence nada). */
    'anticipacion_maxima_dias' => (int) env('NEGOCIACION_ANTICIPACION_MAXIMA_DIAS', 365),

    /** Cuántas horas vive una oferta (720 son 30 días). La oferta sí vence; la solicitud no la acota. */
    'vigencia_oferta_horas' => (int) env('NEGOCIACION_VIGENCIA_OFERTA_HORAS', 24),

    /** La oferta con este número es definitiva: solo se acepta o se rechaza. */
    'max_ofertas_por_negociacion' => (int) env('NEGOCIACION_MAX_OFERTAS', 5),

    /** Tope de sanidad del precio por hectárea. */
    'precio_ha_maximo' => env('NEGOCIACION_PRECIO_HA_MAXIMO', '10000.0000'),

    /** Umbral de la tasa de cancelación que alerta al panel (0 a 1). */
    'umbral_cancelacion_alerta' => env('NEGOCIACION_UMBRAL_CANCELACION', '0.30'),

    /** Ventana de la tasa de cancelación, en días. */
    'ventana_cancelacion_dias' => (int) env('NEGOCIACION_VENTANA_CANCELACION_DIAS', 90),

    /** Plazo para disputar desde `finalizada`, en horas. */
    'plazo_disputa_horas' => (int) env('NEGOCIACION_PLAZO_DISPUTA_HORAS', 72),

    /** Oleadas de aviso y comparador (ADR 0045). */
    'oleadas' => [
        /** Cuántas calificaciones necesita un proveedor para que su promedio cuente en el orden recomendado; con menos va al final de su grupo. */
        'calificaciones_minimas_para_contar' => (int) env('NEGOCIACION_CALIFICACIONES_MINIMAS_PARA_CONTAR', 3),
    ],

    /** Plazo para calificar y para el chat tras `finalizada`, en días. */
    'plazo_calificacion_dias' => (int) env('NEGOCIACION_PLAZO_CALIFICACION_DIAS', 7),
];

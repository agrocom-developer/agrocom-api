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
    /** Cuántos días vive una solicitud abierta antes de expirar. */
    'vigencia_solicitud_dias' => (int) env('NEGOCIACION_VIGENCIA_SOLICITUD_DIAS', 7),

    /** Cuántos días después de expirar el cliente puede renovarla. */
    'plazo_renovacion_dias' => (int) env('NEGOCIACION_PLAZO_RENOVACION_DIAS', 30),

    /** Cuántas horas vive una oferta (sin pasar de la vigencia de la solicitud). */
    'vigencia_oferta_horas' => (int) env('NEGOCIACION_VIGENCIA_OFERTA_HORAS', 48),

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

    /** Plazo para calificar y para el chat tras `finalizada`, en días. */
    'plazo_calificacion_dias' => (int) env('NEGOCIACION_PLAZO_CALIFICACION_DIAS', 7),

    /** Precio de referencia por hectárea según el tipo de aplicación: guía, no límite. */
    'precio_referencia_ha' => [
        'herbicida' => env('NEGOCIACION_REF_HERBICIDA', '120.00'),
        'insecticida' => env('NEGOCIACION_REF_INSECTICIDA', '130.00'),
        'fungicida' => env('NEGOCIACION_REF_FUNGICIDA', '135.00'),
        'fertilizante_foliar' => env('NEGOCIACION_REF_FOLIAR', '110.00'),
        'desecante' => env('NEGOCIACION_REF_DESECANTE', '125.00'),
        'otro' => env('NEGOCIACION_REF_OTRO', '120.00'),
    ],
];

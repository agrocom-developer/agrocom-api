<?php

/*
|--------------------------------------------------------------------------
| Asignación de solicitudes a proveedores (ADR 0030 y 0038)
|--------------------------------------------------------------------------
|
| Parámetros del motor que ofrece cada solicitud publicada primero a los
| proveedores propios y después, por radios crecientes, a independientes y
| terceros (especificación del marketplace §6 y §11). Los valores de aquí son
| el respaldo y el piso; no hay secretos. Los pesos y las tasas son cadenas
| decimales exactas: nada de lo que pondera la puntuación pasa por `float`.
|
*/

return [
    /* Minutos que vive una oferta, según la solicitud sea urgente o programada. */
    'ventana_oferta_urgente_min' => (int) env('ASIGNACION_VENTANA_URGENTE_MIN', 10),
    'ventana_oferta_programada_min' => (int) env('ASIGNACION_VENTANA_PROGRAMADA_MIN', 120),

    /* Máximo de proveedores a los que se ofrece en una misma ronda. */
    'max_proveedores_por_ronda' => (int) env('ASIGNACION_MAX_POR_RONDA', 5),

    /*
     * Radios, en kilómetros, de las rondas de terceros: la ronda 1 es de
     * propios y la ronda 2 usa el primer radio, la 3 el segundo, y así.
     */
    'radios_km' => array_values(array_filter(array_map('trim', explode(',', (string) env('ASIGNACION_RADIOS_KM', '50,100,150'))), 'strlen')),

    /* Pesos de la puntuación de terceros. Deben sumar 1. */
    'pesos' => [
        'cercania' => env('ASIGNACION_PESO_CERCANIA', '0.40'),
        'calificacion' => env('ASIGNACION_PESO_CALIFICACION', '0.35'),
        'aceptacion' => env('ASIGNACION_PESO_ACEPTACION', '0.15'),
        'cancelacion' => env('ASIGNACION_PESO_CANCELACION', '0.10'),
    ],

    /* Calificación que se usa en la puntuación de un proveedor que aún no tiene calificaciones. */
    'calificacion_neutra' => env('ASIGNACION_CALIFICACION_NEUTRA', '4.00'),

    /*
     * Una solicitud programada ofrece a quien tenga un equipo apto y libre en la
     * ventana, esté o no en línea; una urgente exige un equipo en línea. Con
     * este parámetro en `true`, la programada también exige estar en línea.
     */
    'exigir_en_linea_programada' => (bool) env('ASIGNACION_EXIGIR_EN_LINEA_PROGRAMADA', false),

    /* Capacidad mínima del tanque del dron, en litros, para recibir una oferta. 0 no exige nada. */
    'capacidad_minima_tanque_l' => env('ASIGNACION_CAPACIDAD_MINIMA_L', '0'),

    /* Política de calificación: umbrales y mínimo de calificaciones para aplicarlos. */
    'calificacion' => [
        'minimo_conteo' => (int) env('ASIGNACION_CALIFICACION_MINIMO_CONTEO', 10),
        'advertencia_bajo' => env('ASIGNACION_CALIFICACION_ADVERTENCIA', '4.0'),
        'restriccion_tras_plazo_bajo' => env('ASIGNACION_CALIFICACION_RESTRICCION_PLAZO', '3.7'),
        'restriccion_inmediata_bajo' => env('ASIGNACION_CALIFICACION_RESTRICCION_INMEDIATA', '3.3'),
        'plazo_mejora_dias' => (int) env('ASIGNACION_PLAZO_MEJORA_DIAS', 30),
    ],

    /* Política de calificación: tasa de aceptación mínima en la ventana, con un mínimo de ofertas respondidas. */
    'aceptacion' => [
        'umbral' => env('ASIGNACION_ACEPTACION_UMBRAL', '0.60'),
        'ventana_dias' => (int) env('ASIGNACION_ACEPTACION_VENTANA_DIAS', 30),
        'minimo_ofertas' => (int) env('ASIGNACION_ACEPTACION_MINIMO_OFERTAS', 5),
    ],

    /* Política de cancelación del proveedor (ADR 0033). */
    'cancelacion' => [
        'ventana_dias' => (int) env('ASIGNACION_CANCELACION_VENTANA_DIAS', 30),
        'advertencia_desde' => (int) env('ASIGNACION_CANCELACION_ADVERTENCIA', 3),
        'restriccion_desde' => (int) env('ASIGNACION_CANCELACION_RESTRICCION', 5),
        'restriccion_dias' => (int) env('ASIGNACION_CANCELACION_RESTRICCION_DIAS', 7),
        'reincidencia_dias' => (int) env('ASIGNACION_CANCELACION_REINCIDENCIA_DIAS', 90),
        'horas_tardia' => (int) env('ASIGNACION_CANCELACION_HORAS_TARDIA', 12),
        'peso_tardia' => (int) env('ASIGNACION_CANCELACION_PESO_TARDIA', 2),
    ],

    /* Comisión que se descuenta al pago estimado del proveedor que no es propio, en porcentaje (política de comisión). */
    'comision_porcentaje_externo' => env('ASIGNACION_COMISION_EXTERNO_PCT', '15'),
];

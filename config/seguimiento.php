<?php

/*
|--------------------------------------------------------------------------
| Seguimiento del servicio en ejecución (`negociacion-contrato.md` §4 y §6)
|--------------------------------------------------------------------------
|
| Avance con fotos, cierre y la última ubicación del piloto. No hay ruta ni
| tiempo estimado: solo el último punto, que se elimina al finalizar.
|
*/

return [
    /** Cuánto pueden pasarse las hectáreas ejecutadas de las solicitadas, en %. */
    'tolerancia_hectareas_pct' => env('SEGUIMIENTO_TOLERANCIA_HECTAREAS_PCT', '5'),

    /** Fotos de avance o finales que exige el cierre. */
    'evidencias_minimas_cierre' => (int) env('SEGUIMIENTO_EVIDENCIAS_MINIMAS', 1),

    /** Tamaño máximo de una evidencia, en KB. */
    'evidencia_max_kb' => (int) env('SEGUIMIENTO_EVIDENCIA_MAX_KB', 300),

    /** Cada cuántos segundos la app del piloto envía su ubicación. */
    'intervalo_ubicacion_s' => (int) env('SEGUIMIENTO_INTERVALO_UBICACION_S', 30),

    /** Pasado este tiempo la ubicación deja de considerarse vigente. */
    'ubicacion_caduca_s' => (int) env('SEGUIMIENTO_UBICACION_CADUCA_S', 300),

    /** Horas sin avance tras las que se avisa a las partes. */
    'inactividad_horas' => (int) env('SEGUIMIENTO_INACTIVIDAD_HORAS', 72),
];

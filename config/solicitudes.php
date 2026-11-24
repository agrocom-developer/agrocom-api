<?php

/*
|--------------------------------------------------------------------------
| Solicitudes de servicio del marketplace (ADR 0027)
|--------------------------------------------------------------------------
|
| Política de calificación del cliente. El precio por hectárea no se fija acá:
| lo propone el cliente y lo negocia el piloto (`config/negociacion.php`).
|
*/

return [
    'calificacion_cliente' => [
        'umbral' => env('SOLICITUDES_CLIENTE_CALIFICACION_UMBRAL', '3.5'),
        'minimo_conteo' => (int) env('SOLICITUDES_CLIENTE_CALIFICACION_MINIMO', 10),
        'plazo_limitacion_dias' => (int) env('SOLICITUDES_CLIENTE_PLAZO_LIMITACION_DIAS', 30),
    ],

    /** Fotos del estado de un terreno que el piloto ve antes de ofertar. */
    'fotos_terreno' => [
        'maximo_por_terreno' => (int) env('SOLICITUDES_FOTOS_TERRENO_MAXIMO', 8),
        'url_minutos' => (int) env('SOLICITUDES_FOTOS_TERRENO_URL_MINUTOS', 15),
    ],
];

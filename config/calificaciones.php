<?php

/*
|--------------------------------------------------------------------------
| Calificaciones del marketplace (ADR 0031 y 0039)
|--------------------------------------------------------------------------
|
| Parámetros de la política de calificación (`docs/politicas/calificacion.md`):
| el plazo para calificar un servicio completado, cuántas calificaciones entran al
| promedio y el peso de cada sujeto en la reputación del proveedor. Los umbrales
| de alerta del proveedor viven en `config/negociacion.php` (alertan al panel; el
| administrador decide) y los del cliente en `config/solicitudes.php`. Los pesos son
| cadenas decimales exactas: nada de lo que pondera el promedio pasa por `float`.
|
*/

return [
    /* Días que tiene el cliente (y el proveedor) para calificar, desde que la solicitud queda `completada`. */
    'plazo_dias' => (int) env('CALIFICACIONES_PLAZO_DIAS', 7),

    /* Cuántas de las últimas calificaciones visibles entran al promedio. */
    'ventana' => (int) env('CALIFICACIONES_VENTANA', 100),

    /*
     * Peso de cada sujeto en la reputación del proveedor: lo que recibe el
     * proveedor y su piloto.
     */
    'pesos' => [
        'proveedor' => env('CALIFICACIONES_PESO_PROVEEDOR', '0.50'),
        'piloto' => env('CALIFICACIONES_PESO_PILOTO', '0.35'),
    ],

    /* Longitud máxima del comentario. */
    'comentario_max' => (int) env('CALIFICACIONES_COMENTARIO_MAX', 1000),
];

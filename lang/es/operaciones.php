<?php

/*
 * Copy del módulo Operaciones. Primer uso: las etiquetas de
 * App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion, consumidas hoy
 * únicamente por el mockup del dashboard de Seguridad (ver
 * DashboardController::generarOrdenesPorEstadoMock() — el conteo ahí es
 * MOCK, pero estas 4 claves son vocabulario real del enum, no texto
 * inventado). Quedan acá, no en `seguridad.php`, para que la pantalla real
 * de gestión de órdenes las reutilice sin duplicar claves cuando se
 * construya.
 */

return [

    'estado' => [
        'emitida' => 'Emitida',
        'vigente' => 'Vigente',
        'consumida' => 'Consumida',
        'vencida' => 'Vencida',
    ],

];

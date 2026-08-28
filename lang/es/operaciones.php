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

    // Estados de sesión del dashboard demo (quinta vuelta — maquetas
    // 4a/5a/5b). "Validada"/"En vuelo"/"Programada" son vocabulario del
    // ciclo de vida real de `sesiones` (especificación §4.3/§5);
    // "Sin evidencia" es la condición de captura_rc faltante que bloquea la
    // validación. Consumidos por los chips de estado vía DatosDemoPanel.
    'sesion' => [
        'estado' => [
            'validada' => 'Validada',
            'sin_evidencia' => 'Sin evidencia',
            'en_vuelo' => 'En vuelo',
            'programada' => 'Programada',
        ],

        // Estado de captura del RC (Fase 6 — columna RC del tab Sesiones):
        // "no_aplica" es una sesión que todavía no voló (en vuelo/programada),
        // no una tercera variante de "sin_evidencia" — evita que una sesión
        // futura se lea como una falla ya ocurrida.
        'rc_estado' => [
            'capturado' => 'Capturada',
            'sin_evidencia' => 'Falta',
            'no_aplica' => '—',
        ],
    ],

];

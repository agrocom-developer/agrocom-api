<?php

/*
 * Vocabulario común del ciclo de aprobación de proveedores, pilotos, auxiliares
 * y drones (ADR 0032). Lo comparten `Proveedores`, `Flota` y las pantallas del
 * panel, por eso vive aparte de los catálogos de cada módulo.
 */
return [
    'estado' => [
        'pendiente' => 'Pendiente',
        'aprobado' => 'Aprobado',
        'suspendido' => 'Suspendido',
        'rechazado' => 'Rechazado',
        'baja' => 'De baja',
    ],
    'accion' => [
        'aprobar' => 'Aprobar',
        'rechazar' => 'Rechazar',
        'suspender' => 'Suspender',
        'rehabilitar' => 'Rehabilitar',
        'dar_de_baja' => 'Dar de baja',
        'reenviar' => 'Reenviar a revisión',
    ],
    'sujeto' => [
        'proveedor' => 'Proveedor',
        'piloto' => 'Piloto',
        'auxiliar' => 'Auxiliar',
        'dron' => 'Dron',
    ],
    'errores' => [
        'transicion_invalida' => 'No se puede :accion un registro que está en estado «:estado».',
        'motivo_requerido' => 'Indica el motivo para :accion.',
        'sin_permiso' => 'No tienes permiso para esta acción.',
    ],
];

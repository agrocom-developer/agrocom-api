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
        'baja' => 'Eliminado',
    ],
    'maquina_aria' => 'Estado de aprobación',
    // Qué significa cada estado, para el párrafo bajo la máquina de estados.
    'ayuda' => [
        'pendiente' => 'Está en revisión: todavía no puede operar ni recibir ofertas. Revisa sus documentos y decide si lo apruebas o lo rechazas.',
        'aprobado' => 'Está aprobado y puede operar y recibir ofertas. Si hay un problema, puedes suspenderlo o eliminarlo indicando el motivo.',
        'suspendido' => 'Está suspendido: no recibe ofertas hasta que lo rehabilites. Si no volverá a operar, elimínalo indicando el motivo.',
        'rechazado' => 'Fue rechazado y el motivo ya se le informó. Puede corregir y reenviar su solicitud a revisión.',
        'baja' => 'Está eliminado y no vuelve a operar.',
    ],
    'accion' => [
        'aprobar' => 'Aprobar',
        'rechazar' => 'Rechazar',
        'suspender' => 'Suspender',
        'rehabilitar' => 'Rehabilitar',
        'dar_de_baja' => 'Eliminar',
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
        'aprobacion_propia' => 'No puedes aprobar un registro que cargaste tú. Pide a otra persona de la oficina que lo revise y lo apruebe.',
    ],
];

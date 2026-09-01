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

    // Pantalla de panel "Operación › Trabajos" (HU-05, tarea 13): listado
    // mínimo, sin filtros ni detalle de evidencias (eso es HU-15). Vocabulario
    // real del ciclo de vida — no confundir con `sesion.estado` de arriba
    // (el mock del dashboard demo).
    'trabajos' => [
        'titulo' => 'Trabajos',
        'subtitulo' => 'Trabajos sincronizados desde la app de campo, con sus sesiones.',
        'vacio' => 'Todavía no llegó ningún trabajo sincronizado.',
        'col_trabajo' => 'Trabajo',
        'col_estado' => 'Estado',
        'col_hectareas' => 'Hectáreas declaradas',
        'col_inicio' => 'Inicio',
        'col_fin' => 'Fin',
        'col_sesiones' => 'Sesiones',
        'col_piloto' => 'Piloto',
        'sesiones_ver' => 'Ver sesiones (:cantidad)',
        'sesiones_vacio' => 'Sin sesiones todavía.',
        'sesion_piloto' => 'Piloto #:id',
        'sin_fin' => '—',
        'estado' => [
            'abierto' => 'Abierto',
            'cerrado' => 'Cerrado',
            // HU-14 (tarea 14): la sesión validada sigue apareciendo en esta
            // sub-tabla de "Operación › Trabajos" (HU-05) — necesita su
            // propia etiqueta para no quedar como clave cruda.
            'validado' => 'Validado',
        ],
        // Catálogo espec §4.3.
        'motivo_cierre' => [
            'completado' => 'Completado',
            'relevo_piloto' => 'Relevo de piloto',
            'cambio_dron' => 'Cambio de dron',
            'falla_equipo' => 'Falla de equipo',
            'clima' => 'Clima',
            'fin_jornada' => 'Fin de jornada',
            'otro' => 'Otro',
        ],
    ],

    // Pantalla de panel "Operación › Sesiones" (HU-14, tarea 14): cola de
    // validación — el jefe aprueba o rechaza cada sesión cerrada.
    'sesiones_validacion' => [
        'titulo' => 'Validación de sesiones',
        'subtitulo' => 'Sesiones cerradas pendientes de aprobación del jefe de campo.',
        'vacio' => 'No hay sesiones cerradas pendientes de validación.',
        'sesion_titulo' => 'Sesión #:id',
        'col_sesion' => 'Sesión',
        'col_trabajo' => 'Trabajo',
        'col_piloto' => 'Piloto',
        'col_hectareas' => 'Hectáreas',
        'col_fin' => 'Fin',
        'col_motivo_cierre' => 'Motivo de cierre',
        'validar' => 'Validar',
        'rechazar' => 'Rechazar',
        'motivo_label' => 'Motivo del rechazo',
        'motivo_placeholder' => 'Por qué se rechaza esta sesión…',
        // Invariante 4: el piloto de la sesión no puede decidir sobre su
        // propio vuelo, ni para aprobar ni para rechazar.
        'propia' => 'Sos el piloto de esta sesión: no podés validarla ni rechazarla.',
        'validada' => 'Sesión validada correctamente.',
        'rechazada' => 'Sesión rechazada: se registró la corrección con el motivo indicado.',
    ],

];

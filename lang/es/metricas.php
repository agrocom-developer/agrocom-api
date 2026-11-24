<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Métricas del mercado (especificación §14)
    |--------------------------------------------------------------------------
    |
    | Solo lectura: lo que el panel muestra del marketplace en los últimos 7, 30 o
    | 90 días. Una tasa sin divisor se muestra «sin datos», nunca como 0. Español
    | neutro, tuteo (CLAUDE.md, «Tuteo estándar»).
    */

    'titulo' => 'Métricas del mercado',
    'subtitulo' => 'Del :desde al :hasta. Las tasas sin base de cálculo se muestran como «sin datos».',
    'periodo_dias' => ':dias días',
    'sin_datos' => 'sin datos',
    'sin_cobro' => 'La aplicación no cobra en la versión 1: la comisión devengada es lo que los proveedores deben liquidar a Agrocom fuera de la app. Agrocom ajusta la comisión de un servicio en la misma proporción que cualquier reembolso resuelto en una disputa.',

    'seccion_mercado' => 'Mercado',
    'solicitudes' => 'Solicitudes publicadas',
    'solicitudes_pie' => ':sin sin proveedor',
    'tasa_asignacion' => 'Tasa de adjudicación',
    'tasa_asignacion_pie' => ':asignadas con proveedor',
    'tiempo_aceptacion' => 'Tiempo hasta la aceptación',
    'tiempo_aceptacion_pie' => 'Mediana :mediana sobre :cantidad aceptaciones',
    'propios' => 'Adjudicado a proveedores propios',
    'propios_pie' => ':propios propios y :terceros terceros',
    'hectareas' => 'Hectáreas servidas',
    'hectareas_pie' => 'De :solicitadas ha solicitadas',

    'seccion_calidad' => 'Calidad',
    'calificacion' => 'Calificación promedio',
    'calificacion_pie' => ':conteo calificaciones; :reportadas por moderar',
    'tasa_disputas' => 'Tasa de disputas',
    'tasa_disputas_pie' => ':abiertas disputas sobre :ejecutados servicios ejecutados',
    'tasa_reembolso' => 'Tasa de reembolso',
    'tasa_reembolso_pie' => ':monto reembolsado sobre el volumen completado',

    'seccion_oferta' => 'Oferta',
    'proveedores' => 'Proveedores aprobados',
    'proveedores_pie' => ':pendientes esperan aprobación',
    'drones' => 'Drones aprobados',
    'tasa_aceptacion' => 'Tasa de aceptación de ofertas',
    'tasa_aceptacion_pie' => ':respondidas ofertas respondidas',
    'tasa_cancelacion' => 'Tasa de cancelación del proveedor',
    'tasa_cancelacion_pie' => ':cancelaciones cancelaciones de proveedores',

    'seccion_ingreso' => 'Ingreso',
    'volumen' => 'Volumen bruto de servicios',
    'volumen_pie' => ':servicios servicios completados',
    'ingreso_hectarea' => 'Ingreso por hectárea',
    'ingreso_hectarea_pie' => 'Comisión devengada entre hectáreas completadas',

    'seccion_proveedores' => 'Proveedores con más servicios',
    'proveedores_vacio_titulo' => 'Todavía no hay servicios completados',
    'proveedores_vacio_detalle' => 'Cuando un proveedor complete servicios en el período, aparece acá con su calificación y su tasa de aceptación.',
    'col_proveedor' => 'Proveedor',
    'col_servicios' => 'Servicios',
    'col_calificacion' => 'Calificación',
    'col_aceptacion' => 'Aceptación',
    'col_respuesta' => 'Respuesta promedio',
    'col_tipo' => 'Tipo',
    'tipo' => [
        'propio' => 'Propio',
        'independiente' => 'Independiente',
        'tercero' => 'Tercero',
    ],
];

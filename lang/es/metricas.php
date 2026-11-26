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
    'sin_cobro' => 'La aplicación no cobra: el volumen suma el precio final de los servicios finalizados y el pago entre cliente y proveedor se hace fuera de ella.',

    'seccion_mercado' => 'Mercado',
    'solicitudes' => 'Solicitudes publicadas',
    'solicitudes_pie' => ':sin expiradas sin adjudicar',
    'tasa_adjudicacion' => 'Tasa de adjudicación',
    'tasa_adjudicacion_pie' => ':adjudicadas adjudicadas',
    'tiempo_aceptacion' => 'Tiempo hasta la adjudicación',
    'tiempo_aceptacion_pie' => 'Mediana: :mediana, sobre :cantidad adjudicaciones',
    'ofertas_por_solicitud' => 'Ofertas por solicitud',
    'rondas_pie' => 'Rondas promedio por negociación: :rondas (sobre :cantidad negociaciones)',
    'hectareas' => 'Hectáreas servidas',
    'hectareas_pie' => 'De :solicitadas ha solicitadas',

    'seccion_calidad' => 'Calidad',
    'calificacion' => 'Calificación promedio',
    'calificacion_pie' => ':conteo calificaciones, :reportadas por moderar',
    'tasa_disputas' => 'Tasa de reclamos',
    'tasa_disputas_pie' => ':abiertas reclamos sobre :ejecutados servicios ejecutados',
    'tasa_reembolso' => 'Tasa de reembolso',
    'tasa_reembolso_pie' => ':monto reembolsado sobre el volumen de servicios completados',

    'seccion_oferta' => 'Proveedores y drones',
    'proveedores' => 'Proveedores aprobados',
    'proveedores_pie' => ':pendientes por aprobar',
    'drones' => 'Drones aprobados',
    'tasa_aceptacion' => 'Tasa de aceptación de ofertas',
    'tasa_aceptacion_pie' => ':respondidas ofertas respondidas',
    'tasa_cancelacion' => 'Tasa de cancelación del proveedor',
    'tasa_cancelacion_pie' => ':cancelaciones cancelaciones de proveedores',

    'seccion_ingreso' => 'Ingreso',
    'volumen' => 'Volumen bruto de servicios',
    'volumen_pie' => ':servicios servicios completados',
    'ingreso_hectarea' => 'Ingreso por hectárea',
    'ingreso_hectarea_pie' => 'Comisión generada por hectárea completada',

    'seccion_proveedores' => 'Proveedores con más servicios',
    'proveedores_vacio_titulo' => 'Todavía no hay servicios completados',
    'proveedores_vacio_detalle' => 'Cuando un proveedor complete servicios en el período, aparece aquí con su calificación y su tasa de aceptación.',
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

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
    'seccion_negociacion' => 'Negociación',
    'tabs_aria' => 'Secciones de las métricas',
    'tab_calidad_ingreso' => 'Calidad e ingreso',
    'graficas_vacio' => 'No hay solicitudes publicadas en este período. Prueba con un período más largo.',
    'embudo_titulo' => 'Embudo de la negociación',
    'embudo_ayuda' => 'Cuántas solicitudes publicadas en el período llegaron al menos hasta cada paso.',
    'col_paso' => 'Paso',
    'embudo' => [
        'publicadas' => 'Publicadas',
        'con_ofertas' => 'Con ofertas',
        'adjudicadas' => 'Adjudicadas',
        'finalizadas' => 'Finalizadas',
    ],
    'departamentos_titulo' => 'Solicitudes por departamento',
    'departamentos_ayuda' => 'Las publicadas en el período, según su estado actual. Un borrador usa el departamento de su terreno.',
    'col_departamento' => 'Departamento',
    'departamento_sin_dato' => 'Sin departamento',
    'grupo' => [
        'abiertas' => 'Abiertas',
        'en_curso' => 'En curso',
        'cerradas' => 'Finalizadas',
        'canceladas' => 'Canceladas o vencidas',
    ],
    'tiempo_titulo' => 'Tiempo hasta la adjudicación',
    'tiempo_ayuda' => 'Cuántas de las :cantidad adjudicaciones del período tardaron cada tramo desde la publicación.',
    'col_tiempo' => 'Tiempo',
    'adjudicaciones' => 'Adjudicaciones',
    'tiempo' => [
        'hasta_1h' => 'Menos de 1 hora',
        'de_1_a_6h' => 'De 1 a 6 horas',
        'de_6_a_24h' => 'De 6 a 24 horas',
        'de_1_a_3d' => 'De 1 a 3 días',
        'mas_de_3d' => 'Más de 3 días',
    ],
    'rondas_titulo' => 'Rondas por negociación',
    'rondas_ayuda' => 'En cuántas rondas se resolvió cada una de las :cantidad negociaciones de las solicitudes del período (una por solicitud y proveedor).',
    'col_rondas' => 'Rondas',
    'negociaciones' => 'Negociaciones',
    'rondas' => [
        'una' => '1 ronda',
        'dos' => '2 rondas',
        'tres' => '3 rondas',
        'cuatro_o_mas' => '4 o más rondas',
    ],
    'solicitudes' => 'Solicitudes publicadas',
    'solicitudes_pie' => ':abiertas siguen abiertas sin adjudicar',
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

    'seccion_proveedores' => 'Proveedores con más servicios',
    'tab_proveedores' => 'Proveedores',
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

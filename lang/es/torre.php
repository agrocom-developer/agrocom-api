<?php

/*
 * Tablero de la torre de control del marketplace (`panel.dashboard`). Español
 * neutro, tuteo (CLAUDE.md, «Tuteo estándar»). Las tasas sin base de cálculo
 * se muestran «sin datos», nunca como 0.
 */

return [
    'titulo' => 'Torre de control',
    'bajada' => ':fecha · el estado del mercado y lo que espera tu decisión.',
    'viendo_como' => 'Viendo como :rol',
    'periodo_dias' => ':dias días',
    'periodo_aria' => 'Período del tablero',
    'sin_datos' => 'sin datos',

    'pendientes_titulo' => 'Por resolver',
    'pendientes_vacio' => 'No hay nada pendiente. Las colas de aprobación, moderación y disputas están al día.',
    'pendiente' => [
        'proveedores' => 'Proveedores por aprobar',
        'pilotos' => 'Pilotos por aprobar',
        'auxiliares' => 'Auxiliares por aprobar',
        'drones' => 'Drones por aprobar',
        'fotos_dron' => 'Fotos de dron por revisar',
        'documentos' => 'Documentos por revisar',
        'calificaciones' => 'Calificaciones reportadas',
        'disputas' => 'Disputas sin resolver',
    ],
    'abrir_cola' => 'Abrir',

    'mercado_titulo' => 'Mercado',
    'kpi_publicadas' => 'Solicitudes publicadas',
    'kpi_publicadas_pie' => ':ofertas ofertas en el período',
    'kpi_adjudicacion' => 'Tasa de adjudicación',
    'kpi_adjudicacion_pie' => ':adjudicadas de :publicadas publicadas',
    'kpi_ofertas' => 'Ofertas vigentes',
    'kpi_ofertas_pie' => ':promedio ofertas por solicitud en el período',
    'kpi_tiempo' => 'Tiempo hasta adjudicar',
    'kpi_tiempo_pie' => 'Mediana entre publicar y adjudicar',
    'minutos' => ':minutos min',

    'volumen_titulo' => 'Lo acordado en el período',
    'kpi_hectareas' => 'Hectáreas acordadas',
    'kpi_monto' => 'Monto acordado',
    'kpi_monto_pie' => 'Hectáreas por precio acordado; se paga fuera de la app',
    'kpi_precio' => 'Precio acordado por hectárea',
    'kpi_precio_pie' => 'Promedio; el propuesto fue :propuesto',
    'kpi_calificacion' => 'Calificación promedio',
    'kpi_calificacion_pie' => ':conteo calificaciones, :reportadas reportadas',

    'estados_titulo' => 'Solicitudes por estado',
    'estados_vacio' => 'Todavía no hay solicitudes.',
    'col_estado' => 'Estado',
    'col_cantidad' => 'Cantidad',

    'regiones_titulo' => 'Demanda abierta por región',
    'regiones_vacio' => 'No hay solicitudes abiertas. Cuando un cliente publique una, aparece aquí por región.',
    'col_region' => 'Región',
    'col_solicitudes' => 'Solicitudes',
    'col_hectareas' => 'Hectáreas',
    'macrorregion' => [
        'cercado' => 'Cercado',
        'norte_integrado' => 'Norte integrado',
        'chiquitania' => 'Chiquitania',
        'valles' => 'Valles',
        'chaco' => 'Chaco',
        'sin_zona' => 'Sin zona',
    ],

    'recientes_titulo' => 'Últimas solicitudes publicadas',
    'recientes_vacio' => 'Todavía no se publicó ninguna solicitud.',
    'col_solicitud' => 'Solicitud',
    'col_cliente' => 'Cliente',
    'col_precio' => 'Precio por ha',
    'ver_todas' => 'Ver todas',
    'ver_metricas' => 'Ver métricas completas',
];

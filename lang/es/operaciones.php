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
        // HU-15 (tarea 15): sin resultados por los filtros aplicados —
        // distinto de "vacio" (no hay NADA todavía), para no confundir al
        // jefe con un tablero que en realidad tiene datos.
        'filtro_vacio' => 'Ningún trabajo coincide con estos filtros.',
        'col_trabajo' => 'Trabajo',
        'col_estado' => 'Estado',
        'col_hectareas' => 'Hectáreas declaradas',
        'col_inicio' => 'Inicio',
        'col_fin' => 'Fin',
        'col_sesiones' => 'Sesiones',
        'col_piloto' => 'Piloto',
        'col_detalle' => 'Detalle',
        'ver_detalle' => 'Ver detalle',
        'sesiones_ver' => 'Ver sesiones (:cantidad)',
        'sesiones_vacio' => 'Sin sesiones todavía.',
        'sesion_piloto' => 'Piloto #:id',
        'sin_fin' => '—',
        // HU-15 (tarea 15): filtros del tablero — estado de TABLERO
        // (Trabajo::estadoTablero(), no la columna cruda), lote y orden de
        // aplicación. Las opciones de lote/orden solo listan lo que
        // realmente aparece entre los trabajos existentes (sin catálogo
        // completo de Comercial, ADR 0003 regla 3).
        'filtro_estado' => 'Estado',
        'filtro_lote' => 'Lote',
        'filtro_orden' => 'Orden de aplicación',
        'filtro_todos' => 'Todos',
        'filtro_lote_opcion' => 'Lote #:id',
        'filtro_orden_opcion' => 'Orden #:id (aplicación :aplicacion)',
        'filtrar' => 'Filtrar',
        'limpiar_filtros' => 'Limpiar filtros',
        'paginacion_aria' => 'Paginación de trabajos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
        // Detalle de un trabajo (HU-15, tarea 15): panel.trabajos.show.
        'volver' => 'Volver al tablero',
        'detalle_titulo' => 'Trabajo #:id',
        'detalle_sesiones_titulo' => 'Sesiones',
        'detalle_evidencias_titulo' => 'Evidencias',
        // TE-07/HU-08/HU-09 (compresión, fotos, captura del RC) son sprint 3
        // y no están implementadas: la sección convive con eso vacío sin
        // simular datos que no existen.
        'detalle_evidencias_vacio' => 'Todavía no hay evidencias registradas para este trabajo.',
        // Acta de conformidad (HU-17, tarea 24): solo lectura desde el
        // panel — generar/firmar es de `agrocom-field` (piloto/jefe).
        'detalle_acta_titulo' => 'Acta de conformidad',
        'detalle_acta_vacio' => 'Todavía no se generó el acta de este trabajo.',
        'acta_estado' => [
            'pendiente' => 'Pendiente de firma',
            'firmada' => 'Firmada',
        ],
        'acta_firmante' => 'Firmado por',
        'acta_descargar_pdf' => 'Descargar PDF',
        // Reporte técnico (HU-18, tarea 25): solo lectura desde el panel —
        // se genera solo al firmar el acta (mismo criterio que la sección
        // de acta, arriba).
        'detalle_reporte_titulo' => 'Reporte técnico',
        'detalle_reporte_vacio' => 'Todavía no se generó el reporte técnico de este trabajo.',
        'reporte_descargar_pdf' => 'Descargar PDF',
        'sesion_rechazada' => 'Rechazada',
        'sesion_motivo_rechazo' => 'Motivo del rechazo: :motivo',
        'estado' => [
            'abierto' => 'Abierto',
            'cerrado' => 'Cerrado',
            // HU-14 (tarea 14): la sesión validada sigue apareciendo en esta
            // sub-tabla de "Operación › Trabajos" (HU-05) — necesita su
            // propia etiqueta para no quedar como clave cruda. Desde HU-15
            // (tarea 15), estas mismas tres claves también rotulan el
            // estado de TABLERO del trabajo (Trabajo::estadoTablero()).
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

    // Pantalla de panel "Reportes › Alertas" (HU-19, tarea 26): bandeja de
    // alertas por excepción del encargado de operaciones.
    'alertas' => [
        'titulo' => 'Alertas por excepción',
        'subtitulo' => 'Solo lo anómalo: batería caliente, dron sospechoso, condiciones forzadas y suma excedida.',
        'vacio' => 'No hay alertas registradas todavía.',
        'filtro_vacio' => 'Ninguna alerta coincide con estos filtros.',
        'col_tipo' => 'Tipo',
        'col_mensaje' => 'Detalle',
        'col_trabajo' => 'Trabajo',
        'col_estado' => 'Estado',
        'col_fecha' => 'Generada',
        'col_atendida' => 'Atendida',
        'col_accion' => 'Acción',
        'sin_trabajo' => '—',
        'atender' => 'Marcar como atendida',
        'atendida' => 'Alerta marcada como atendida.',
        'atendida_por' => 'Atendida por persona #:id el :fecha',
        'filtro_estado' => 'Estado',
        'filtro_tipo' => 'Tipo',
        'filtro_todos' => 'Todos',
        'filtrar' => 'Filtrar',
        'limpiar_filtros' => 'Limpiar filtros',
        'paginacion_aria' => 'Paginación de alertas',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
        'tipo' => [
            'bateria_caliente' => 'Batería caliente',
            'dron_sospechoso' => 'Dron sospechoso',
            'condiciones_forzadas' => 'Condiciones forzadas',
            'suma_excedida' => 'Suma excedida',
        ],
        'estado' => [
            'pendiente' => 'Pendiente',
            'atendida' => 'Atendida',
        ],
    ],

    // Pantalla de panel "Operación › Drones" (HU-27, tarea 36): ABM de la
    // flota de drones, con su modelo (texto libre) y capacidad de carga en
    // litros (30/50/60, CHECK de base de datos). Mismo molde que
    // `comercial.campos`/`comercial.clientes`, sin sub-entidad.
    'drones' => [
        'titulo' => 'Drones',
        'subtitulo' => 'Flota de drones registrada, con su modelo y volumen de carga.',
        'nuevo' => 'Nuevo dron',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Identificador o modelo…',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio' => 'Todavía no hay drones registrados.',
        'filtro_vacio' => 'Ningún dron coincide con esta búsqueda.',
        'col_identificador' => 'Identificador',
        'col_modelo' => 'Modelo',
        'col_capacidad' => 'Capacidad de carga',
        'sin_modelo' => '—',
        'sin_capacidad' => '—',
        'capacidad_valor' => ':cantidad L',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmás la baja de este dron?',
        'paginacion_aria' => 'Paginación de drones',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
        'titulo_crear' => 'Nuevo dron',
        'titulo_editar' => 'Editar dron',
        'subtitulo_form' => 'Identificador, modelo y capacidad de carga del dron.',
        'seccion_datos' => 'Datos del dron',
        'campos_contador' => ':cantidad campos',
        'campo_identificador' => 'Identificador',
        'campo_modelo' => 'Modelo',
        'campo_modelo_ayuda' => 'Texto libre, ej.: DJI Agras T30.',
        'campo_capacidad' => 'Capacidad de carga (L)',
        'campo_capacidad_ayuda' => 'Valores permitidos: 30, 50 o 60 litros.',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'creado' => 'Dron creado correctamente.',
        'actualizado' => 'Dron actualizado correctamente.',
        'eliminado' => 'Dron dado de baja correctamente.',
    ],

    // Pantalla de panel "Operación › Órdenes" (HU-25, tarea 38): alta y
    // seguimiento de órdenes de aplicación, con su propia máquina de estados
    // (emitida → vigente). Los estados en sí reutilizan `operaciones.estado.*`
    // (arriba) — no se duplican acá.
    'ordenes' => [
        'titulo' => 'Órdenes de aplicación',
        'subtitulo' => 'Órdenes emitidas por contrato y lote, con sus límites y parámetros de vuelo.',
        'nueva' => 'Nueva orden',
        'filtro_estado' => 'Estado',
        'filtro_todos' => 'Todos',
        'filtrar' => 'Filtrar',
        'limpiar_filtros' => 'Limpiar filtros',
        'vacio' => 'Todavía no hay órdenes de aplicación registradas.',
        'filtro_vacio' => 'Ninguna orden coincide con estos filtros.',
        'col_contrato' => 'Contrato',
        'col_lote' => 'Lote',
        'col_aplicacion' => 'Aplicación',
        'col_litros_ha' => 'Litros/ha',
        'col_fecha_emision' => 'Emisión',
        'col_estado' => 'Estado',
        'editar' => 'Editar',
        'activar_accion' => 'Activar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_activar' => '¿Confirmás activar esta orden? Pasará a estar vigente para el lote.',
        'confirmar_baja' => '¿Confirmás la baja de esta orden?',
        'paginacion_aria' => 'Paginación de órdenes',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
        'titulo_crear' => 'Nueva orden de aplicación',
        'titulo_editar' => 'Editar orden de aplicación',
        'subtitulo_form' => 'Contrato, lote, límites climáticos y parámetros de vuelo de la orden.',
        'seccion_datos' => 'Datos de la orden',
        'seccion_limites' => 'Límites climáticos',
        'seccion_limites_ayuda' => 'Un límite en blanco hereda el valor del contrato o el parámetro por defecto del sistema.',
        'seccion_vuelo' => 'Parámetros de vuelo',
        'campos_contador' => ':cantidad campos',
        'campo_contrato' => 'Contrato',
        'campo_contrato_placeholder' => 'Seleccioná un contrato…',
        'campo_contrato_opcion' => ':cliente — Contrato #:id',
        'campo_lote' => 'Lote',
        'campo_lote_placeholder' => 'Seleccioná un lote…',
        'campo_lote_opcion' => ':campo — Lote :codigo',
        'campo_nro_aplicacion' => 'Número de aplicación',
        'campo_litros_ha' => 'Litros por hectárea',
        'campo_fecha_emision' => 'Fecha de emisión',
        'campo_contacto' => 'Emitida por (contacto)',
        'campo_contacto_placeholder' => 'Sin especificar',
        'campo_contacto_opcion' => ':nombre — :cliente',
        'campo_observaciones' => 'Observaciones',
        'campo_humedad_min_pct' => 'Humedad mínima (%)',
        'campo_humedad_max_pct' => 'Humedad máxima (%)',
        'campo_viento_max_kmh' => 'Viento máximo (km/h)',
        'campo_temperatura_max_c' => 'Temperatura máxima (°C)',
        'campo_velocidad_max_kmh' => 'Velocidad máxima (km/h)',
        'campo_altura_vuelo_m' => 'Altura de vuelo (m)',
        'campo_velocidad_vuelo_kmh' => 'Velocidad de vuelo (km/h)',
        'campo_ancho_pasada_m' => 'Ancho de pasada (m)',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'error_humedad_rango' => 'La humedad mínima no puede ser mayor que la máxima.',
        'error_contrato_requerido' => 'Seleccioná un contrato.',
        'error_contrato_invalido' => 'El contrato seleccionado no es válido.',
        'error_lote_requerido' => 'Seleccioná un lote.',
        'error_lote_invalido' => 'El lote seleccionado no es válido.',
        'error_contacto_invalido' => 'El contacto seleccionado no es válido.',
        'creada' => 'Orden de aplicación creada correctamente.',
        'actualizada' => 'Orden de aplicación actualizada correctamente.',
        'activada' => 'Orden de aplicación activada correctamente.',
        'eliminada' => 'Orden de aplicación dada de baja correctamente.',
    ],

];

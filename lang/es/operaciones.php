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

    // Momento del ciclo del cultivo en que se aplica (HU-47, tarea 70; enum
    // `Dominio\TipoAplicacion`). Mismo criterio que `estado` de arriba:
    // vocabulario compartido por el formulario, el listado y su filtro.
    'tipo_aplicacion' => [
        'siembra' => 'Siembra',
        'desarrollo' => 'Desarrollo',
        'cosecha' => 'Cosecha',
    ],

    // Estados de sesión del dashboard demo (quinta vuelta — maquetas
    // 4a/5a/5b). "Validada"/"En vuelo"/"Programada" son vocabulario del
    // ciclo de vida real de `sesiones` (especificación §4.3/§5);
    // "Sin evidencia" es la condición de captura_rc faltante que bloquea la
    // validación. Consumidos por los chips de estado vía DatosDemoPanel.
    'sesion' => [
        // Los TRES estados reales de `ope_sesiones.estado` (enum
        // `Dominio\EstadoSesion`). Hasta la tarea 67 esta lista decía
        // "Validada / Sin evidencia / En vuelo / Programada": era el
        // vocabulario del mock del dashboard, que no existía en la base y no
        // coincidía con ningún estado que la máquina de estados pudiera
        // producir. Se reemplaza por el catálogo real — la clave que se pide
        // acá es siempre `EstadoSesion::value`.
        'estado' => [
            'abierto' => 'Abierta',
            'cerrado' => 'Cerrada',
            'validado' => 'Validada',
        ],
    ],

    // Tipos de `ope_evidencias.tipo` (enum `Dominio\TipoEvidencia`), para la
    // galería multimedia del dashboard y donde haga falta nombrar el tipo.
    'evidencias' => [
        'tipo' => [
            'captura_rc' => 'Captura del control remoto',
            'imagen_campo' => 'Imagen del campo',
            'foto_incidencia' => 'Foto de incidencia',
            'comprobante' => 'Comprobante',
            'firma_acta' => 'Firma del acta',
        ],
    ],

    // Pantalla de panel "Operación › Trabajos" (HU-05, tarea 13): listado
    // mínimo, sin filtros ni detalle de evidencias (eso es HU-15). Vocabulario
    // real del ciclo de vida del TRABAJO — `sesion.estado` de arriba es el
    // de la sesión, que tiene tres valores y no dos.
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
        // Galería de evidencias (HU-42, tarea 56): panel.trabajos.evidencias.
        // Agrupa las tres evidencias que existen hoy para un trabajo — no hay
        // `captura_rc` a nivel de sesión (recorte de otra tarea, fuera de
        // alcance de esta pantalla).
        'volver_al_detalle' => 'Volver al detalle',
        'evidencias_titulo' => 'Evidencias del trabajo #:id',
        'evidencias_ver_galeria' => 'Ver galería de evidencias',
        'evidencias_descargar' => 'Descargar',
        'evidencias_imagen_campo_titulo' => 'Imagen de campo',
        'evidencias_imagen_campo_vacio' => 'Todavía no se registró la imagen de campo de este trabajo.',
        'evidencias_capturas_rc_titulo' => 'Capturas del control remoto',
        'evidencias_capturas_rc_vacio' => 'Ninguna sesión de este trabajo se cerró con su captura del control remoto.',
        'evidencias_capturas_rc_alt' => 'Captura del control remoto al cierre de la sesión #:secuencia',
        'evidencias_capturas_rc_hectareas' => ':hectareas ha',
        'evidencias_firma_acta_titulo' => 'Firma del acta',
        'evidencias_firma_acta_vacio' => 'Todavía no se firmó el acta de este trabajo.',
        'evidencias_incidencias_titulo' => 'Incidencias con foto',
        'evidencias_incidencias_vacio' => 'Ninguna sesión de este trabajo registró una incidencia con foto.',
        'evidencias_sesion_titulo' => 'Sesión #:secuencia',
        // Catálogo de TipoIncidencia (espec §4.3, tabla `incidencias`; HU-08,
        // tarea 22) — sin traducir todavía en ningún otro lado del panel.
        'incidencia_tipo' => [
            'caldo' => 'Caldo',
            'esc' => 'ESC',
            'bateria' => 'Batería',
            'mecanica' => 'Mecánica',
            'clima' => 'Clima',
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
        // El campo vive en la tarjeta junto a los dos botones: la ayuda
        // aclara que solo lo pide "Rechazar", no "Validar".
        'motivo_ayuda' => 'Obligatorio solo para rechazar.',
        // Invariante 4: el piloto de la sesión no puede decidir sobre su
        // propio vuelo, ni para aprobar ni para rechazar.
        'propia' => 'Sos el piloto de esta sesión: no podés validarla ni rechazarla.',
        'validada' => 'Sesión validada correctamente.',
        'rechazada' => 'Sesión rechazada: se registró la corrección con el motivo indicado.',
    ],

    // Pantalla de panel "Operación › Pausas" (HU-44, tarea 58): pausas de
    // sesión con causa atribuible (DS-01) y su agregado por causa.
    'pausas' => [
        'titulo' => 'Pausas',
        'subtitulo' => 'Tiempo perdido en pausas de sesión, con su causa atribuible.',
        'nuevo' => 'Nueva pausa',
        'creada' => 'Pausa registrada correctamente.',
        'filtro_periodo' => 'Período',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio' => 'No hay pausas registradas.',
        'filtro_vacio' => 'No hay pausas que coincidan con el filtro.',
        'tablero_titulo' => 'Total por causa',
        'tablero_total' => 'Total del período',
        'col_sesion' => 'Sesión',
        'col_causa' => 'Causa',
        'col_inicio' => 'Inicio',
        'col_fin' => 'Fin',
        'col_duracion' => 'Duración',
        'duracion_valor' => ':horas h :minutos min',
        'sesion_etiqueta' => 'Sesión #:id (trabajo #:trabajo, secuencia :secuencia)',
        'paginacion_aria' => 'Paginación de pausas',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        'titulo_crear' => 'Nueva pausa',
        'subtitulo_form' => 'Registrá una pausa de sesión con su causa atribuible.',
        'seccion_datos' => 'Datos de la pausa',
        'campos_contador' => ':cantidad campos',
        'campo_sesion' => 'Sesión',
        'campo_sesion_placeholder' => 'Elegí una sesión…',
        'campo_causa' => 'Causa',
        'campo_causa_placeholder' => 'Elegí una causa…',
        'campo_inicio' => 'Inicio',
        'campo_fin' => 'Fin',
        'estado_form' => 'Sin guardar',

        'error_sesion_requerida' => 'Elegí la sesión en la que ocurrió la pausa.',
        'error_sesion_invalida' => 'La sesión elegida no existe.',
        'error_causa_requerida' => 'Elegí la causa de la pausa.',
        'error_causa_invalida' => 'Esa causa no es válida.',
        'volver' => 'Volver a pausas',

        'causa' => [
            'clima' => 'Clima fuera de rango',
            'imprevisto_del_cliente' => 'Imprevisto del cliente (insumos que no llegan)',
            'cambio_lote_cliente' => 'Cambio de lote ordenado por el cliente',
            'falla_equipo' => 'Falla de equipo',
            'logistica' => 'Logística y traslados',
        ],
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
        'volver' => 'Volver a drones',
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
        'filtro_tipo_aplicacion' => 'Tipo de aplicación',
        'filtro_todos' => 'Todos',
        'filtrar' => 'Filtrar',
        'limpiar_filtros' => 'Limpiar filtros',
        'vacio' => 'Todavía no hay órdenes de aplicación registradas.',
        'filtro_vacio' => 'Ninguna orden coincide con estos filtros.',
        'col_contrato' => 'Contrato',
        'col_lote' => 'Lote',
        'col_aplicacion' => 'Aplicación',
        'col_tipo_aplicacion' => 'Tipo',
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
        'campo_tipo_aplicacion' => 'Tipo de aplicación',
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
        'volver' => 'Volver a órdenes',
    ],

    // Pantalla de panel "Operación › Asignación de equipos" (HU-70, tarea
    // 85): reparto de las hectáreas de una orden vigente entre equipos de
    // trabajo. Ficha propia, no sub-pantalla de `ordenes` — ver docblock de
    // `AsignacionEquiposController`.
    'asignacion_equipos' => [
        'titulo' => 'Asignación de equipos',
        'subtitulo' => 'Repartí las hectáreas de cada orden vigente entre los equipos de trabajo.',
        'vacio' => 'No hay órdenes vigentes para repartir.',
        'col_orden' => 'Orden',
        'col_contrato' => 'Contrato',
        'col_lote' => 'Lote',
        'col_hectareas_lote' => 'Hectáreas del lote',
        'col_asignadas' => 'Asignadas',
        'col_restantes' => 'Restantes',
        'asignar_accion' => 'Asignar equipos',
        'ficha_titulo' => 'Orden #:nro — :lote',
        'ficha_subtitulo' => 'Aplicación #:nro del contrato :contrato.',
        'ficha_volver' => 'Volver a asignación de equipos',
        'resumen_hectareas_lote' => 'Hectáreas del lote',
        'resumen_asignadas' => 'Asignadas',
        'resumen_restantes' => 'Restantes',
        'orden_no_vigente' => 'Esta orden ya no está vigente: no admite nuevas asignaciones.',
        'seccion_equipos' => 'Equipos asignados',
        'equipos_vacio' => 'Todavía no se asignó ningún equipo a esta orden.',
        'campo_equipo' => 'Equipo de trabajo',
        'campo_equipo_placeholder' => 'Seleccioná un equipo…',
        'campo_hectareas' => 'Hectáreas a asignar',
        'asignar_boton' => 'Asignar',
        'asignado' => 'Equipo asignado correctamente.',
        'equipos_sin_vigentes' => 'No hay equipos de trabajo vigentes hoy.',
    ],

    // Pantalla de panel "Reportes › Técnicos" (HU-43, tarea 57):
    // panel.reportes.tecnicos.index. Solo lectura — filtra y enlaza a la
    // descarga individual ya existente (panel.trabajos.reporte-pdf).
    'reportes_tecnicos' => [
        'titulo' => 'Reportes técnicos',
        'subtitulo' => 'Reportes técnicos generados por lote, para reenviar al agrónomo.',
        'filtro_cliente' => 'Cliente',
        'filtro_cliente_placeholder' => 'Todos los clientes',
        'filtro_desde' => 'Desde',
        'filtro_hasta' => 'Hasta',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtros',
        'vacio' => 'Todavía no se generó ningún reporte técnico.',
        'filtro_vacio' => 'Ningún reporte coincide con estos filtros.',
        'col_trabajo' => 'Trabajo',
        'col_cliente' => 'Cliente',
        'col_generado' => 'Generado',
        'col_descarga' => 'Descarga',
        'trabajo_lote' => 'Lote :lote — aplicación :aplicacion',
        'descargar_pdf' => 'Descargar PDF',
    ],

    // Pantalla de panel "Operación › Estadías en hacienda" (HU-51, tarea 74):
    // consulta de estadías del equipo en cada hacienda — entrada/salida del
    // equipo cargada desde la app de campo vía POST /api/sync, con filtro por
    // rango de fechas, equipo de trabajo y campo. Totales de días efectivos
    // por equipo y por propiedad. Solo lectura.
    'estadias' => [
        'titulo' => 'Estadías en hacienda',
        'subtitulo' => 'Entrada y salida del equipo de trabajo en cada propiedad, con sus tiempos efectivos.',
        'vacio' => 'Todavía no hay estadías registradas.',
        'filtro_vacio' => 'Ninguna estadía coincide con estos filtros.',
        'filtro_desde' => 'Desde',
        'filtro_placeholder_desde' => 'Fecha de inicio…',
        'filtro_hasta' => 'Hasta',
        'filtro_placeholder_hasta' => 'Fecha de fin…',
        'filtro_equipo' => 'Equipo de trabajo',
        'filtro_campo' => 'Propiedad',
        'filtro_todos' => 'Todos',
        'filtrar' => 'Filtrar',
        'limpiar_filtros' => 'Limpiar filtros',
        'col_equipo' => 'Equipo',
        'col_campo' => 'Propiedad',
        'col_vehiculo' => 'Vehículo',
        'col_entrada' => 'Entrada',
        'col_salida' => 'Salida',
        'en_curso' => 'En curso',
        'sin_vehiculo' => '—',
        'totales_equipo' => 'Días efectivos por equipo',
        'totales_campo' => 'Días efectivos por propiedad',
        'dias' => 'días',
        'paginacion_aria' => 'Paginación de estadías',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
    ],

];

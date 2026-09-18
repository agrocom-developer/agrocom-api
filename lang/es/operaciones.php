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

    // HU-79 (tarea 110): etiquetas del selector de PRESENTACIÓN "Tipo" que
    // filtra "Categoría de insumo" en el formulario de orden — no es un
    // campo que el server valide, ver docblock de `_formulario.blade.php`.
    'tipo_insumo' => [
        'solido' => 'Sólido',
        'liquido' => 'Líquido',
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
        'titulo' => 'Orden de Trabajo',
        'subtitulo' => 'Trabajos sincronizados desde la app de campo, con sus sesiones.',
        'vacio_titulo' => 'Todavía no llegó ningún trabajo sincronizado',
        'vacio_detalle' => 'Los trabajos se sincronizan automáticamente desde la app de campo cuando un piloto cierra una sesión. Vuelve a esta pantalla cuando llegue el primero.',
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
        // Edición/eliminación de un trabajo puntual (HU-93, tarea 108):
        // panel.trabajos.detalle-editar. Turno obligatorio junto con su
        // horario cuando se carga, pero el campo en sí es opcional (un
        // trabajo nacido por sync puro nunca lo tuvo).
        'editar_titulo' => 'Editar trabajo #:id',
        'editar_subtitulo' => 'Corrige el lote, el equipo, las hectáreas o el turno de este trabajo antes de que se valide.',
        'campos_contador' => ':cantidad campos',
        'campo_lote' => 'Lote',
        'campo_equipo' => 'Equipo de trabajo',
        'campo_equipo_sin_asignar' => 'Sin equipo asignado',
        'campo_hectareas' => 'Hectáreas declaradas',
        'campo_turno' => 'Turno',
        'turno_manana' => 'Mañana',
        'turno_noche' => 'Noche',
        'turno_todo_el_dia' => 'Todo el día',
        'campo_turno_hora_inicio' => 'Hora de inicio',
        'campo_turno_hora_fin' => 'Hora de fin',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmas la baja de este trabajo?',
        'actualizado' => 'Trabajo actualizado.',
        'eliminado' => 'Trabajo eliminado.',
        'error_lote_requerido' => 'Elige el lote.',
        'error_lote_no_pertenece' => 'El lote elegido no pertenece a la orden de este trabajo.',
        'error_equipo_no_existe' => 'El equipo elegido no existe.',
        'error_hectareas_requerido' => 'Ingresa las hectáreas declaradas.',
        'error_hectareas_positivo' => 'Las hectáreas declaradas deben ser un número positivo.',
        'error_turno_hora_requerida' => 'Ingresa la hora de inicio y de fin del turno.',
        'error_turno_hora_rango' => 'La hora de fin del turno debe ser posterior a la de inicio.',
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
        'vacio_titulo' => 'No hay sesiones pendientes de validación',
        'vacio_detalle' => 'Esta cola se llena cuando un piloto cierra una sesión. Vuelve a esta pantalla cuando haya una lista para aprobar o rechazar.',
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
        'propia' => 'Eres el piloto de esta sesión: no puedes validarla ni rechazarla.',
        'validada' => 'Sesión validada correctamente.',
        'rechazada' => 'Sesión rechazada: se registró la corrección con el motivo indicado.',
        'error_motivo_requerido' => 'Ingresa el motivo del rechazo.',
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
        'vacio_titulo' => 'Sin pausas registradas',
        'vacio_detalle' => 'Las pausas se registran cuando un piloto o supervisor pausan una sesión en la app de campo, con su causa atribuible. Vuelve a esta pantalla cuando se registre la primera.',
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
        'subtitulo_form' => 'Registra una pausa de sesión con su causa atribuible.',
        'seccion_datos' => 'Datos de la pausa',
        'campos_contador' => ':cantidad campos',
        'campo_sesion' => 'Sesión',
        'campo_sesion_placeholder' => 'Elige una sesión…',
        'campo_causa' => 'Causa',
        'campo_causa_placeholder' => 'Elige una causa…',
        'campo_inicio' => 'Inicio',
        'campo_fin' => 'Fin',
        'estado_form' => 'Sin guardar',

        'error_sesion_requerida' => 'Elige la sesión en la que ocurrió la pausa.',
        'error_sesion_invalida' => 'La sesión elegida no existe.',
        'error_causa_requerida' => 'Elige la causa de la pausa.',
        'error_causa_invalida' => 'Esa causa no es válida.',
        'error_inicio_requerido' => 'Elige el inicio de la pausa.',
        'error_fin_requerido' => 'Elige el fin de la pausa.',
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
        'vacio_titulo' => 'Sin alertas registradas',
        'vacio_detalle' => 'Las alertas se generan automáticamente cuando se detecta una condición anómala durante una sesión. Vuelve a esta pantalla cuando se registre la primera.',
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
    // Capacidad de carga en kilos (HU-81, tarea 96): sin catálogo cerrado,
    // a diferencia de los litros.
    'drones' => [
        'titulo' => 'Drones',
        'subtitulo' => 'Flota de drones registrada, con su modelo y volumen de carga.',
        'nuevo' => 'Nuevo dron',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Identificador o modelo…',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio_titulo' => 'Todavía no hay drones registrados',
        'vacio_detalle' => 'Registra cada dron de la flota con su identificador, modelo y capacidad de carga. Necesitarás al menos uno para ejecutar trabajos.',
        'filtro_vacio' => 'Ningún dron coincide con esta búsqueda.',
        'col_identificador' => 'Identificador',
        'col_modelo' => 'Modelo',
        'col_capacidad' => 'Capacidad de carga',
        'sin_modelo' => '—',
        'sin_capacidad' => '—',
        'capacidad_valor' => ':cantidad L',
        'capacidad_kg_valor' => ':cantidad kg',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmas la baja de este dron?',
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
        'campo_capacidad_kg' => 'Capacidad de carga (kg)',
        'campo_capacidad_kg_ayuda' => 'Kilos que puede llevar el dron por vuelo, para aplicación sólida.',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'error_identificador_requerido' => 'Ingresa el identificador del dron.',
        'creado' => 'Dron creado correctamente.',
        'actualizado' => 'Dron actualizado correctamente.',
        'eliminado' => 'Dron dado de baja correctamente.',
        'volver' => 'Volver a drones',
    ],

    // Pantalla de panel "Operación › Orden de Trabajo" (reforma 18/9/2026):
    // maestro de tandas (`OrdenTrabajo`) — una orden de aplicación puede
    // ejecutarse en varias tandas, cada una con sus propios equipos y
    // parámetros compartidos (clima/vuelo, Ph, calda). El detalle de cada
    // trabajo puntual (equipo×lote) sigue en `operaciones.trabajos.*`.
    'ordenes_trabajo' => [
        'titulo' => 'Orden de Trabajo',
        'subtitulo' => 'Tandas de trabajo sobre las órdenes de aplicación vigentes, con sus equipos y lotes.',
        'vacio_titulo' => 'Todavía no se creó ninguna Orden de Trabajo',
        'vacio_detalle' => 'Una Orden de Trabajo agrupa los equipos que trabajan juntos una tanda, con sus lotes, turnos y parámetros de vuelo. Crea la primera desde el botón de arriba.',
        'filtro_vacio' => 'Ninguna Orden de Trabajo coincide con estos filtros.',
        'nueva_accion' => 'Nueva Orden de Trabajo',
        'ver_accion' => 'Ver detalle',
        'col_tanda' => 'Nro. Orden de Trabajo',
        'col_orden' => 'Orden de aplicación',
        'col_equipos' => 'Equipos',
        'col_hectareas' => 'Hectáreas',
        'col_estado' => 'Estado',
        'filtro_orden' => 'Orden de aplicación',
        'filtro_todos' => 'Todas',
        'filtrar' => 'Filtrar',
        'limpiar_filtros' => 'Limpiar filtros',
        'paginacion_aria' => 'Paginación de Órdenes de Trabajo',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Alta (`panel.trabajos.crear`).
        'crear_titulo' => 'Nueva Orden de Trabajo',
        'crear_subtitulo' => 'Elige la orden de aplicación, los equipos que participan de esta tanda y sus lotes.',
        'campo_orden' => 'Orden de aplicación',
        'campo_orden_placeholder' => 'Selecciona una orden vigente…',
        'campo_orden_opcion' => 'Orden #:id (aplicación :aplicacion)',
        'error_orden_requerida' => 'Elige la orden de aplicación.',
        'error_orden_no_vigente' => 'Esa orden no está vigente: no admite una Orden de Trabajo nueva.',
        'seccion_parametros' => 'Parámetros compartidos de la tanda',
        'seccion_parametros_ayuda' => 'Un límite en blanco hereda el valor del contrato o el parámetro por defecto del sistema.',
        'campos_contador' => ':cantidad campos',
        'seccion_equipos' => 'Equipos de esta tanda',
        'equipo_sin_opciones' => 'No hay equipos de trabajo vigentes hoy.',
        'equipo_crear_link' => '¿No está el equipo que necesitas? Créalo acá',
        'equipo_agregar' => 'Agregar equipo',
        'equipo_quitar' => 'Quitar equipo',
        'lote_agregar' => 'Agregar lote',
        'lote_quitar' => 'Quitar',
        'guardar' => 'Confirmar Orden de Trabajo',
        'volver' => 'Volver a Orden de Trabajo',
        'creada' => 'Orden de Trabajo creada correctamente.',

        // Detalle maestro-detalle (`panel.trabajos.show`).
        'detalle_titulo' => 'Orden de Trabajo #:id',
        'kpi_hectareas' => 'Hectáreas totales',
        'kpi_equipos' => 'Equipos',
        'seccion_condiciones' => 'Límites climáticos y parámetros de vuelo',
        'seccion_ph_calda' => 'Ph y calda',
        'seccion_trabajos' => 'Trabajos de esta tanda',
        'trabajos_vacio' => 'Esta tanda todavía no tiene trabajos.',
        'sin_dato' => '—',
    ],

    // Pantalla de panel "Operación › Órdenes" (HU-25, tarea 38): alta y
    // seguimiento de órdenes de aplicación, con su propia máquina de estados
    // (emitida → vigente). Los estados en sí reutilizan `operaciones.estado.*`
    // (arriba) — no se duplican acá.
    'ordenes' => [
        'titulo' => 'Órdenes de aplicación',
        'subtitulo' => 'Órdenes emitidas por contrato y lotes, con dosis del insumo.',
        'nueva' => 'Nueva orden',
        'filtro_estado' => 'Estado',
        'filtro_tipo_aplicacion' => 'Tipo de aplicación',
        'filtro_todos' => 'Todos',
        'filtro_busqueda_placeholder' => 'Buscar por cliente…',
        'filtrar' => 'Filtrar',
        'limpiar_filtros' => 'Limpiar filtros',
        'vacio_titulo' => 'Sin órdenes de aplicación',
        'vacio_detalle' => 'Emite órdenes de aplicación para los lotes donde se necesita un trabajo. Cada orden define la dosis del insumo.',
        'filtro_vacio_titulo' => 'Sin resultados',
        'filtro_vacio_detalle' => 'Ninguna orden coincide con la búsqueda o los filtros elegidos.',
        'col_contrato' => 'Contrato',
        'col_lote' => 'Lote',
        'col_aplicacion' => 'Aplicación',
        'col_tipo_aplicacion' => 'Tipo',
        'col_dosis' => 'Dosis',
        'dosis_litros_ha' => ':cantidad l/ha',
        'dosis_kilos_por_vuelo' => ':cantidad kg/vuelo',
        'col_fecha_emision' => 'Emisión',
        'col_estado' => 'Estado',
        'editar' => 'Editar',
        'activar_accion' => 'Activar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_activar_titulo' => 'Activar orden',
        'confirmar_activar' => '¿Confirmas activar esta orden? Pasará a estar vigente para el lote.',
        'confirmar_eliminar_titulo' => 'Dar de baja la orden',
        'confirmar_baja' => '¿Confirmas la baja de esta orden?',
        'paginacion_aria' => 'Paginación de órdenes',
        'titulo_crear' => 'Nueva orden de aplicación',
        'titulo_editar' => 'Editar orden de aplicación',
        'subtitulo_form' => 'Contrato, lotes, categoría de insumo y dosis de la orden.',
        'seccion_datos_contrato' => 'Datos del contrato',
        'seccion_datos' => 'Datos de la orden',
        'seccion_lotes' => 'Lotes',
        'campos_contador' => ':cantidad campos',
        'campo_contrato' => 'Contrato',
        'campo_contrato_placeholder' => 'Selecciona un contrato…',
        'campo_contrato_opcion' => ':cliente — Contrato #:id',
        'campo_contrato_ayuda' => 'Busca por cliente, propiedad o número de contrato: si escribes el nombre de un cliente, aparecen todos sus contratos.',
        'campo_contrato_cliente' => 'Cliente',
        'campo_contrato_propiedades' => 'Propiedad(es)',
        'campo_contrato_aplicaciones' => 'Aplicaciones pactadas',
        'campo_contrato_hectareas' => 'Hectáreas contratadas',
        'campo_contrato_fecha_inicio' => 'Fecha de inicio',
        'campo_contrato_fecha_fin' => 'Fecha de fin',
        'campo_lote' => 'Lote',
        'campo_lote_placeholder' => 'Selecciona un lote…',
        'campo_lote_opcion' => ':campo — Lote :codigo',
        'campo_lote_hectareas' => 'Hectáreas solicitadas',
        'lote_agregar' => 'Agregar lote',
        'lote_quitar' => 'Quitar',
        'lotes_buscar_placeholder' => 'Buscar por código o propiedad…',
        'lotes_buscar_aria' => 'Buscar lotes',
        'lotes_seleccionar_todos' => 'Seleccionar todos los lotes',
        'lotes_vacio_titulo' => 'Elige un contrato',
        'lotes_vacio_detalle' => 'Elige un contrato arriba para ver y seleccionar sus lotes.',
        'lotes_tabla_vacio' => 'Ningún lote coincide con la búsqueda.',
        'lotes_pagina_anterior' => 'Anterior',
        'lotes_pagina_siguiente' => 'Siguiente',
        'lotes_columna_codigo' => 'Código',
        'lotes_columna_propiedad' => 'Propiedad',
        'lotes_columna_hectareas_lote' => 'Hectáreas del lote',
        'lotes_columna_hectareas_solicitadas_ayuda' => 'Cuántas hectáreas de este lote pide esta orden — puede ser menos que el total si se reparte con otras órdenes.',
        'lotes_columna_desnivel' => 'Desnivel',
        'lotes_columna_limpieza' => 'Limpieza',
        'campo_cantidad_equipos' => 'Cantidad de equipos necesarios',
        'campo_cantidad_equipos_asignar' => 'Asignar equipos',
        'campo_nro_aplicacion' => 'Número de aplicación',
        'campo_nro_aplicacion_placeholder' => 'Elige el número…',
        'nro_aplicacion_opcion_1' => 'Primera aplicación',
        'nro_aplicacion_opcion_2' => 'Segunda aplicación',
        'nro_aplicacion_opcion_3' => 'Tercera aplicación',
        'nro_aplicacion_opcion_4' => 'Cuarta aplicación',
        'nro_aplicacion_opcion_5' => 'Quinta aplicación',
        'nro_aplicacion_opcion_6' => 'Sexta aplicación',
        'nro_aplicacion_opcion_7' => 'Séptima aplicación',
        'nro_aplicacion_opcion_8' => 'Octava aplicación',
        'nro_aplicacion_opcion_9' => 'Novena aplicación',
        'nro_aplicacion_opcion_10' => 'Décima aplicación',
        'nro_aplicacion_opcion_extra' => 'Aplicación #:n',
        'campo_tipo_aplicacion' => 'Tipo de aplicación',
        'campo_tipo_insumo' => 'Tipo',
        'campo_tipo_insumo_placeholder' => 'Sólido o líquido…',
        'campo_categoria_insumo' => 'Categoría de insumo',
        'campo_categoria_insumo_placeholder' => 'Selecciona una categoría…',
        'campo_litros_ha' => 'Litros por hectárea',
        'campo_kilos_por_vuelo' => 'Kilos por vuelo',
        'campo_fecha_emision' => 'Fecha de emisión',
        'campo_fecha_emision_ayuda_prefijo' => 'El contrato va del',
        'campo_fecha_emision_ayuda_conector' => 'al',
        'campo_contacto' => 'Emitida por (contacto)',
        'campo_contacto_placeholder' => 'Sin especificar',
        'campo_contacto_opcion' => ':nombre — :tipo',
        'campo_observaciones' => 'Observaciones',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'error_contrato_requerido' => 'Selecciona un contrato.',
        'error_contrato_invalido' => 'El contrato seleccionado no es válido.',
        'error_lote_requerido' => 'Selecciona un lote.',
        'error_lote_invalido' => 'El lote seleccionado no es válido.',
        'error_lotes_requerido' => 'Agrega al menos un lote.',
        'error_lote_repetido' => 'Un lote no puede repetirse dentro de la misma orden.',
        'error_hectareas_solicitadas_superan_lote' => 'Las hectáreas solicitadas superan las hectáreas del lote.',
        'error_lote_de_otro_cliente' => 'El lote pertenece a un cliente distinto del contrato.',
        'error_categoria_insumo_requerida' => 'Selecciona una categoría de insumo.',
        'error_categoria_insumo_invalida' => 'La categoría de insumo seleccionada no es válida.',
        'error_kilos_por_vuelo_requerido' => 'Los kilos por vuelo son obligatorios para un insumo sólido.',
        'error_litros_ha_requerido' => 'Los litros por hectárea son obligatorios para un insumo líquido.',
        'error_contacto_invalido' => 'El contacto seleccionado no es válido.',
        'error_hectareas_solicitadas_requeridas' => 'Ingresa las hectáreas solicitadas del lote.',
        'error_cantidad_equipos_requerida' => 'Ingresa la cantidad de equipos necesarios.',
        'error_nro_aplicacion_requerido' => 'Elige el número de aplicación.',
        'error_tipo_aplicacion_requerido' => 'Elige el tipo de aplicación.',
        'error_fecha_emision_requerida' => 'Elige la fecha de emisión.',
        'creada' => 'Orden de aplicación creada correctamente.',
        'actualizada' => 'Orden de aplicación actualizada correctamente.',
        'activada' => 'Orden de aplicación activada correctamente.',
        'eliminada' => 'Orden de aplicación dada de baja correctamente.',
        'volver' => 'Volver a órdenes',

        // Aside de resumen relacionado del edit (homogeneización 17/9/2026,
        // §6.3.1): estado de la asignación de equipos de ESTA orden.
        'aside_titulo' => 'Asignación de equipos',
        'aside_volver_texto' => 'Orden #:nro',
        'aside_no_vigente_titulo' => 'Todavía no está vigente',
        'aside_no_vigente_detalle' => 'Activa la orden para poder repartir sus hectáreas entre equipos de trabajo.',
        'aside_vacio_titulo' => 'Sin equipos asignados',
        'aside_vacio_detalle' => 'Reparte los lotes de esta orden entre los equipos de trabajo disponibles.',
        'aside_repartir_accion' => 'Repartir equipos',
        'aside_hectareas_solicitadas' => 'Hectáreas solicitadas',
        'aside_asignadas' => 'Asignadas',
        'aside_restantes' => 'Restantes',
        'aside_equipos_asignados' => 'Equipos asignados',
        'aside_ver_asignacion' => 'Ver asignación',

        // Vista de detalle de solo lectura (`show()`, homogeneización
        // 17/9/2026) — primera pantalla del arquetipo Detalle del panel.
        'detalle_titulo' => 'Orden de aplicación #:id',
        'detalle_subtitulo' => ':cliente · emitida el :fecha',
        'ver_accion' => 'Ver',
        'kpi_aplicaciones' => 'Aplicaciones',
        'kpi_aplicaciones_sufijo' => 'de :total',
        'valor_sin_definir' => 'Sin definir',
        'lotes_contador' => ':cantidad lotes · :hectareas ha',
        'col_hectareas_lote' => 'Hectáreas',
        'lote_estado_asignado' => 'Asignado',
        'lote_estado_pendiente' => 'Pendiente',
        'seccion_actividad' => 'Actividad',
        'actividad_emitida' => 'Orden emitida',
        'actividad_activada' => 'Orden activada — pasó a vigente',
        'actividad_equipo_asignado' => 'Equipo :equipo asignado — :hectareas ha',
        'actividad_meta' => ':fecha · :autor',
        'avance_titulo' => 'Avance de asignación',
        'avance_resumen' => ':asignadas de :solicitadas ha asignadas',

        'kpi_equipos_necesarios' => 'Equipos necesarios',
        'kpi_equipos_asignados_pie' => ':asignados de :necesarios asignados',

        'seccion_vinculos' => 'Relacionado',
        'vinculo_trabajos' => 'Órdenes de trabajo',
        'vinculo_trabajos_meta' => ':cantidad trabajos',
        'vinculo_asignacion' => 'Asignación de equipos',
        'vinculo_asignacion_meta' => ':cantidad equipos',
    ],

    // Pantalla de panel "Operación › Asignación de equipos" (HU-70, tarea
    // 85; reforma 18/9/2026): reparto de las hectáreas de una orden vigente
    // entre equipos de trabajo. Ficha propia, no sub-pantalla de `ordenes`.
    // Incluye subsección "Condiciones de vuelo" (8 campos movidos de órdenes).
    'asignacion_equipos' => [
        'titulo' => 'Asignación de equipos',
        'subtitulo' => 'Reparte las hectáreas de cada orden vigente entre los equipos de trabajo.',
        'vacio_titulo' => 'Sin órdenes vigentes',
        'vacio_detalle' => 'Activa una orden de aplicación para que aparezca acá. Una orden vigente es la que está lista para que los equipos comiencen a trabajar.',
        'col_orden' => 'Orden',
        'col_contrato' => 'Contrato',
        'col_lote' => 'Lote',
        'col_hectareas_lote' => 'Hectáreas del lote',
        'col_asignadas' => 'Asignadas',
        'col_restantes' => 'Restantes',
        'asignar_accion' => 'Asignar equipos',
        'ficha_titulo' => 'Orden #:nro',
        'ficha_subtitulo' => 'Aplicación #:nro del contrato :contrato.',
        'ficha_volver' => 'Volver a asignación de equipos',
        'resumen_hectareas_lote' => 'Hectáreas del lote',
        'resumen_asignadas' => 'Asignadas',
        'resumen_restantes' => 'Restantes',
        'orden_no_vigente' => 'Esta orden ya no está vigente: no admite nuevas asignaciones.',
        'seccion_lotes' => 'Lotes de la orden',
        'seccion_equipos' => 'Equipos asignados',
        'seccion_condiciones_vuelo' => 'Condiciones de vuelo',
        'seccion_condiciones_vuelo_ayuda' => 'Los límites en blanco heredan el valor del contrato o del parámetro por defecto del sistema.',
        'equipos_vacio' => 'Todavía no se asignó ningún equipo a esta orden.',
        'campo_equipo' => 'Equipo de trabajo',
        'campo_equipo_placeholder' => 'Selecciona un equipo…',
        'campo_lote' => 'Lote',
        'campo_lote_placeholder' => 'Selecciona un lote…',
        'campo_hectareas' => 'Hectáreas a asignar',
        'campo_humedad_min_pct' => 'Humedad mínima (%)',
        'campo_humedad_max_pct' => 'Humedad máxima (%)',
        'campo_viento_max_kmh' => 'Viento máximo (km/h)',
        'campo_temperatura_max_c' => 'Temperatura máxima (°C)',
        'campo_velocidad_max_kmh' => 'Velocidad máxima (km/h)',
        'campo_altura_vuelo_m' => 'Altura de vuelo (m)',
        'campo_velocidad_vuelo_kmh' => 'Velocidad de vuelo (km/h)',
        'campo_ancho_pasada_m' => 'Ancho de pasada (m)',
        // Reforma 18/9/2026 ("Orden de Trabajo"/tandas): Ph y calda son
        // compartidos por toda la tanda, solo aplican si la orden es de
        // insumo líquido — la vista los oculta para una orden sólida.
        'campo_ph_agua' => 'Ph del agua',
        'campo_ph_calda' => 'Ph de la calda',
        'seccion_calda' => 'Calda',
        'campo_calda_producto' => 'Producto',
        'campo_calda_cantidad' => 'Cantidad',
        'campo_calda_unidad' => 'Unidad',
        'unidad_l' => 'Litros (l)',
        'unidad_ml' => 'Mililitros (ml)',
        'unidad_kg' => 'Kilos (kg)',
        'unidad_g' => 'Gramos (g)',
        'calda_agregar' => 'Agregar producto',
        'calda_quitar' => 'Quitar',
        // Turno (obligatorio junto con su horario, pedido explícito del
        // dueño) — propio de cada lote de cada equipo, no de la tanda.
        'campo_turno' => 'Turno',
        'turno_manana' => 'Mañana',
        'turno_noche' => 'Noche',
        'turno_todo_el_dia' => 'Todo el día',
        'campo_turno_hora_inicio' => 'Hora de inicio',
        'campo_turno_hora_fin' => 'Hora de fin',
        'equipo_agregar' => 'Agregar equipo',
        'equipo_quitar' => 'Quitar equipo',
        'lote_agregar' => 'Agregar lote',
        'lote_quitar' => 'Quitar',
        'asignar_boton' => 'Confirmar reparto',
        'asignado' => 'Equipos asignados correctamente.',
        'equipos_sin_vigentes' => 'No hay equipos de trabajo vigentes hoy.',
        'error_equipos_requerido' => 'Agrega al menos un equipo.',
        'error_equipo_requerido' => 'Elige el equipo de trabajo.',
        'error_lotes_requerido' => 'Agrega al menos un lote.',
        'error_lote_requerido' => 'Elige el lote.',
        'error_hectareas_requerido' => 'Ingresa las hectáreas a asignar.',
        'error_humedad_rango' => 'La humedad mínima no puede ser mayor que la máxima.',
        'error_turno_requerido' => 'Elige el turno.',
        'error_turno_hora_requerida' => 'Ingresa la hora de inicio y de fin del turno.',
        'error_turno_hora_rango' => 'La hora de fin del turno debe ser posterior a la de inicio.',
        'error_ph_solo_liquido' => 'El Ph solo aplica a una orden de insumo líquido.',
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
        'vacio_titulo' => 'Todavía no se generó ningún reporte técnico',
        'vacio_detalle' => 'Los reportes técnicos se generan por lote a medida que se cierran y conforman trabajos. En cuanto se genere el primero, vas a verlo acá.',
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
        'vacio_titulo' => 'Sin estadías registradas',
        'vacio_detalle' => 'Las estadías se registran automáticamente desde la app de campo cuando el equipo entra o sale de una propiedad. Vuelve a esta pantalla cuando se complete el primer registro.',
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

    // Mensajes de error.
    'errores' => [
        'dron_duplicado' => "Ya existe un dron activo con el identificador ':identificador'.",
        'equipo_trabajo_no_vigente' => 'El equipo de trabajo #:id no está vigente hoy.',
        'firma_evidencia_invalida' => 'La evidencia de firma no existe o no es del tipo firma_acta.',
        'firma_evidencia_reutilizada' => 'La evidencia #:id ya respalda la firma de otra acta.',
        'acta_ya_firmada_con_otra_evidencia' => 'El acta #:id ya está firmada con otra evidencia.',
        'firma_evidencia_conflicto_concurrente' => 'La evidencia de firma ya fue tomada por otra acta en un pedido concurrente.',
        'hectareas_asignadas_superan_lote' => 'Las hectáreas asignadas al lote #:lote de la orden #:orden (:asignado) superan las hectáreas solicitadas para ese lote (:solicitadas).',
        'lote_no_pertenece_a_orden' => 'El lote #:lote no pertenece a la orden #:orden.',
        'orden_no_editable' => "No se puede editar una orden en estado ':estado': solo una orden 'emitida' admite edición.",
        'orden_no_vigente_para_asignacion' => 'La orden #:id no está vigente: no admite asignación de equipos.',
        'orden_vigente_duplicada_en_lote' => 'El lote #:lote ya tiene otra orden de aplicación vigente.',
        'orden_vigente_no_eliminable' => 'La orden #:id está vigente: no se puede eliminar sin pasar antes por un estado terminal.',
        'pausa_fin_anterior_a_inicio' => 'El fin de la pausa debe ser posterior a su inicio.',
        'piloto_no_puede_decidir_su_propia_sesion' => 'El piloto de la sesión #:id no puede validar ni rechazar su propia sesión.',
        'sesion_no_disponible_por_estado' => "La sesión #:id está ':estado': solo una sesión 'cerrado' admite validación o rechazo.",
        'sesion_no_disponible_por_anulada' => 'La sesión #:id ya fue rechazada — no admite una nueva decisión.',
        'trabajo_no_cerrado_para_acta' => 'El trabajo #:id debe estar cerrado para generar su acta.',
        'trabajo_con_sesiones_sin_validar' => 'El trabajo #:id tiene sesiones vigentes sin validar: el acta certifica datos ya validados.',
        'acta_uuid_cliente_en_uso' => 'El uuid_cliente pedido para el acta del trabajo #:id ya está en uso por otra acta.',
        'transicion_acta_no_permitida' => "No se puede pasar un acta de ':desde' a ':hasta'.",
        'transicion_orden_no_permitida' => "No se puede pasar una orden de aplicación de ':desde' a ':hasta'.",
        'transicion_sesion_no_permitida' => "No se puede pasar una sesión de ':desde' a ':hasta'.",
        'transicion_trabajo_no_permitida' => "No se puede pasar un trabajo de ':desde' a ':hasta'.",
        'trabajo_validado_no_editable' => 'El trabajo #:id ya está validado: no se puede editar.',
        'trabajo_validado_no_eliminable' => 'El trabajo #:id ya está validado: no se puede eliminar.',
        'calda_no_registrada' => 'No se pudo registrar la calda de la tanda de la orden #:orden.',

        // Avisos que aparecen en la bandeja de alertas.
        'alerta_bateria_caliente' => 'Batería a :temperatura °C en la recarga #:secuencia de la sesión #:sesion (trabajo #:trabajo).',
        'alerta_dron_sospechoso' => 'El dron #:dron acumula :recargas recargas con alerta de temperatura — revisar motores/ESC.',
        'alerta_condiciones_forzadas' => 'El trabajo #:trabajo arrancó fuera de rango, autorizado con observación del agrónomo.',
        'alerta_suma_excedida' => 'El trabajo #:trabajo superó las hectáreas del lote más la tolerancia de solape configurada.',

        // Motivos de rechazo al subir una evidencia desde la app de campo.
        'evidencia_archivo_ausente' => 'archivo ausente o vacío',
        'evidencia_fecha_invalida' => 'fecha inválida',
        'evidencia_hash_no_coincide' => 'el hash declarado no coincide con el contenido recibido',
        'evidencia_guardado_disco_fallido' => 'no se pudo guardar la evidencia en el disco r2: :ruta',
        'evidencia_archivo_no_guardado' => 'no se pudo guardar el archivo',
        'evidencia_datos_invalidos' => 'evidencia con datos incompletos o inválidos',
    ],

    // Motivos de rechazo que recibe la app de campo: cambiar el texto cambia lo que lee el piloto.
    'sync' => [
        'orden_lote_no_vigente' => 'la orden y el lote declarados no forman un par vigente',
        'trabajo_no_existe_aun' => 'el trabajo referenciado no existe todavía',
        'sesion_no_existe_aun' => 'la sesión referenciada no existe todavía',
        'condiciones_fuera_de_rango' => 'condiciones fuera de rango sin observación firmada del agrónomo',
        'falta_foto_incidencia' => 'falta la foto de la incidencia: evidencia inexistente o de tipo distinto',
        'foto_incidencia_reutilizada' => 'la foto ya fue usada para respaldar otra incidencia',
        'trabajo_no_existe' => 'el trabajo referenciado no existe',
        'trabajo_ya_cerrado' => 'el trabajo ya está cerrado',
        'operario_no_participo' => 'el operario no participó en este trabajo',
        'falta_imagen_campo' => 'falta la imagen del campo: evidencia inexistente o de tipo distinto',
        'imagen_campo_reutilizada' => 'la imagen del campo ya fue usada para cerrar otro trabajo',
        'trabajo_cierre_invalido' => 'no se pudo cerrar el trabajo: referencia o dato inválido',
        'sesion_no_existe' => 'la sesión referenciada no existe',
        'sesion_ya_cerrada' => 'la sesión ya está cerrada',
        'piloto_no_corresponde' => 'el piloto de la sesión no corresponde al operario autenticado',
        'sesion_cierre_invalido' => 'no se pudo cerrar la sesión: referencia o dato inválido',
        'registro_invalido' => 'no se pudo aplicar el registro: referencia o dato inválido',
        'estadia_no_existe' => 'la estadía referenciada no existe',
        'estadia_ya_cerrada' => 'la estadía ya está cerrada',
        'salida_anterior_a_entrada' => 'la salida no puede ser anterior o igual a la entrada',
        'estadia_cierre_invalido' => 'no se pudo cerrar la estadía: referencia o dato inválido',
        'equipo_con_estadia_abierta' => 'el equipo ya tiene una estadía abierta',
        'falta_foto_control' => 'falta la foto de control: evidencia inexistente o de tipo distinto',
        'falta_foto_ciclo_bateria' => 'falta la foto del ciclo de batería y balanceo: evidencia inexistente o de tipo distinto',
        'falta_foto_dron_limpio' => 'falta la foto del dron limpio: evidencia inexistente o de tipo distinto',
        'foto_control_reutilizada' => 'la foto de control ya fue usada para respaldar otro reporte de equipo',
        'foto_ciclo_bateria_reutilizada' => 'la foto del ciclo de batería y balanceo ya fue usada para respaldar otro reporte de equipo',
        'foto_dron_limpio_reutilizada' => 'la foto del dron limpio ya fue usada para respaldar otro reporte de equipo',
    ],

    // Textos de los documentos PDF.
    'pdf' => [
        'comun' => [
            'subtitulo_lote_orden' => 'Lote #:lote — Orden #:orden (aplicación :aplicacion)',
            'sin_dato' => '—',
        ],
        'acta' => [
            'titulo_documento' => 'Acta de conformidad — Trabajo #:id',
            'titulo' => 'Acta de conformidad',
            'hectareas_conformadas' => 'Hectáreas conformadas',
            'fecha_generacion' => 'Fecha de generación',
        ],
        'reporte_tecnico' => [
            'titulo_documento' => 'Reporte técnico — Trabajo #:id',
            'titulo' => 'Reporte técnico',
            'emitido_el' => 'Emitido el :fecha',
            'imagen_campo_titulo' => 'Imagen del campo',
            'imagen_campo_vacio' => 'Sin imagen del campo registrada.',
            'horas_aplicacion_titulo' => 'Horas de la aplicación',
            'acta_titulo' => 'Acta de conformidad',
            'acta_columna' => 'Acta',
            'firmante' => 'Firmante',
            'fecha_firma' => 'Fecha de firma',
            'acta_vacio' => 'Sin acta registrada.',
            'resumen_titulo' => 'Resumen',
            'hectareas_declaradas' => 'Hectáreas declaradas',
            'litros_por_hectarea' => 'Litros de caldo por hectárea',
            'cobertura' => 'Cobertura',
            'cobertura_en_curso' => 'en curso',
            'condiciones_titulo' => 'Condiciones de vuelo',
            'viento' => 'Viento (km/h)',
            'temperatura' => 'Temperatura (°C)',
            'humedad' => 'Humedad (%)',
            'resultado' => 'Resultado',
            'resultado_valor' => [
                'autorizado' => 'Autorizado',
                'autorizado_con_observacion' => 'Autorizado con observación del agrónomo',
            ],
            'superficie_no_aplicada_titulo' => 'Superficie no aplicada',
            'hectareas_sin_aplicar' => 'Hectáreas sin aplicar',
            'motivo' => 'Motivo',
            'capturas_titulo' => 'Capturas del control remoto',
            'captura_alt' => 'Captura del control remoto de la sesión :secuencia',
            'captura_leyenda' => 'Sesión :secuencia — :hectareas ha',
            'capturas_vacio' => 'Ninguna sesión de este trabajo registró su captura del control remoto.',
            'incidencias_titulo' => 'Incidencias',
            'evidencia' => 'Evidencia',
            'incidencias_vacio' => 'Sin incidencias registradas.',
            'sesiones_detalle_titulo' => 'Detalle de sesiones (relevo de piloto o cambio de dron)',
            'dron' => 'Dron',
            'productos_titulo' => 'Productos cargados en el caldo',
            'producto' => 'Producto',
            'cantidad' => 'Cantidad',
            'unidad' => 'Unidad',
            'productos_vacio' => 'Sin productos registrados para este trabajo.',
            'equipos_titulo' => 'Reporte de Equipos',
            'bateria' => 'Batería',
            'ciclos_acumulados' => 'Ciclos acumulados',
            'ciclos_sin_dato' => 'sin dato',
            'horas_vuelo_dron' => 'Horas de vuelo del dron',
            'equipo_foto_control' => 'Control',
            'equipo_foto_ciclo_bateria' => 'Ciclo de batería y balanceo',
            'equipo_foto_dron_limpio' => 'Dron limpio',
            'foto_de' => 'Foto de :etiqueta',
            'equipos_vacio' => 'Sin "Reporte de Equipos" registrado para este trabajo.',
        ],
    ],

];

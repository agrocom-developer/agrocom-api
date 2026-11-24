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

    // Pantalla de panel "Operación › Trabajos" (HU-05, tarea 13): listado
    // mínimo, sin filtros ni detalle de evidencias (eso es HU-15). Vocabulario
    // real del ciclo de vida del TRABAJO — `sesion.estado` de arriba es el
    // de la sesión, que tiene tres valores y no dos.
    'trabajos' => [
        'titulo' => 'Trabajos',
        'subtitulo' => 'Lo que los equipos ejecutan sobre cada solicitud, sincronizado desde la app de campo.',
        'col_solicitud' => 'Solicitud',
        'buscar_placeholder' => 'Buscar por número de solicitud',
        'filtro_vacio_detalle' => 'Cambia el estado o el número de solicitud para ver más trabajos.',
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
        'seccion_datos' => 'Datos del trabajo',
        'campos_contador' => ':cantidad campos',
        'campo_solicitud' => 'Solicitud',
        'sin_dato' => '—',
        'seccion_vinculos' => 'Relacionado',
        'seccion_actividad' => 'Actividad',
        'vinculo_solicitud' => 'Solicitud de servicio',
        'vinculo_solicitud_meta' => 'Solicitud #:id',
        'campo_cliente' => 'Cliente',
        'baja_titulo' => 'Dar de baja el trabajo #:id',
        'campo_lote' => 'Lote',
        'campo_lote_ayuda' => 'Solo los lotes de la orden a la que pertenece este trabajo.',
        'campo_equipo' => 'Cuadrilla',
        'campo_equipo_sin_asignar' => 'Sin equipo asignado',
        'campo_equipo_ayuda' => 'La cuadrilla que vuela este lote. Sin asignar, el trabajo queda esperando el reparto.',
        'campo_hectareas' => 'Hectáreas declaradas',
        'campo_hectareas_ayuda' => 'Las que se le reparten a esta cuadrilla en el lote; no pueden superar las del lote.',
        'campo_turno' => 'Turno',
        'campo_turno_ayuda' => 'Si eliges un turno, indica también su hora de inicio y de fin.',
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
        // Detalle de un trabajo (HU-15, tarea 15; arquetipo Detalle desde la
        // tarea 124): panel.trabajos.detalle.
        'volver_a_ot' => 'Volver a la orden de trabajo #:id',
        'volver_al_listado' => 'Volver a trabajos',
        'detalle_titulo' => 'Trabajo #:id',
        'detalle_subtitulo' => ':solicitud · :cuadrilla',
        'kpi_hectareas_aplicadas' => 'Hectáreas aplicadas',
        'kpi_sesiones' => 'Sesiones',
        'kpi_sesiones_pie' => ':validadas de :total validadas',
        'kpi_turno' => 'Turno',
        'turno_sin_definir' => 'Sin turno definido',
        'campo_orden_aplicacion' => 'Orden de aplicación',
        'campo_orden_trabajo' => 'Orden de trabajo',
        'campo_horario' => 'Horario',
        'col_dron' => 'Dron',
        'col_motivo_cierre' => 'Motivo de cierre',
        'col_motivo_rechazo' => 'Motivo del rechazo',
        'avance_titulo' => 'Cobertura del trabajo',
        'avance_resumen' => ':aplicadas de :declaradas ha aplicadas',
        'vinculo_ot' => 'Orden de trabajo',
        'vinculo_ot_meta' => 'Orden #:id',
        'vinculo_orden' => 'Orden de aplicación',
        'vinculo_orden_meta' => 'Aplicación :aplicacion',
        'vinculo_cuadrilla' => 'Cuadrilla asignada',
        'vinculo_acta_pdf' => 'Acta en PDF',
        'vinculo_reporte_pdf' => 'Reporte técnico en PDF',
        'actividad_sincronizado' => 'Trabajo sincronizado desde la app de campo',
        'actividad_cerrado' => 'Trabajo cerrado',
        'actividad_sesion_validada' => 'Sesión #:secuencia validada',
        'actividad_sesion_rechazada' => 'Sesión #:secuencia rechazada: :motivo',
        'actividad_acta_firmada' => 'Acta firmada por :firmante',
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
        // Pasos de estado de la ficha de edición (tarea 114). El panel no
        // cierra un trabajo: eso lo hace el piloto desde la app de campo, así
        // que los pasos solo dicen dónde está.
        'estado_pasos_aria' => 'Estado del trabajo',
        'estado_pista_cierre_en_campo' => 'El cierre lo registra el piloto desde la app de campo, no el panel.',
        'estado_ayuda' => [
            'abierto' => 'El trabajo está en curso: el equipo todavía puede sumar sesiones desde la app de campo. Mientras siga abierto puedes corregir su lote, su cuadrilla, sus hectáreas y su turno.',
            'cerrado' => 'El piloto ya cerró el trabajo en campo. Sus sesiones pasan a la cola de validación y, cuando estén todas validadas, el trabajo deja de admitir cambios.',
        ],
        // Resumen relacionado del aside de la ficha de edición: sus dos
        // órdenes, lo registrado en campo y la cuadrilla asignada.
        'aside_abierto_titulo' => 'Todavía no hay nada que resumir',
        'aside_abierto_detalle' => 'El trabajo sigue abierto. Cuando el piloto lo cierre desde la app de campo vas a ver acá sus sesiones, sus órdenes y la cuadrilla que lo voló.',
        'aside_orden_titulo' => 'Orden de aplicación',
        'aside_orden_nro' => 'Aplicación',
        'aside_orden_tipo' => 'Tipo',
        'aside_orden_estado' => 'Estado',
        'aside_orden_accion' => 'Ver la orden',
        'aside_tanda_titulo' => 'Orden de trabajo',
        'aside_tanda_id' => 'Orden',
        'aside_tanda_trabajos' => 'Trabajos',
        'aside_tanda_cuadrillas' => 'Cuadrillas',
        'aside_tanda_accion' => 'Ver la orden de trabajo',
        'aside_campo_titulo' => 'Registro en campo',
        'aside_campo_sesiones' => 'Sesiones',
        'aside_campo_validadas' => 'Validadas',
        'aside_campo_hectareas' => 'Hectáreas voladas',
        'aside_campo_accion_detalle' => 'Ver el detalle',
        'aside_campo_accion_evidencias' => 'Ver evidencias',
        'aside_cuadrilla_titulo' => 'Cuadrilla asignada',
        'aside_cuadrilla_codigo' => 'Código',
        'aside_cuadrilla_nombre' => 'Nombre',
        'aside_cuadrilla_sin_nombre' => 'Sin nombre',
        'aside_cuadrilla_integrantes' => 'Integrantes',
        'aside_cuadrilla_accion' => 'Ver la cuadrilla',
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
        'estado_cambio_de_a' => 'Cambio de estado: de :desde a :hacia',
        'confirmar_validar_titulo' => 'Validar sesión',
        'confirmar_validar' => '¿Confirmas la sesión #:id? Al validarla ya no admite cambios.',
        'confirmar_rechazar_titulo' => 'Rechazar sesión',
        'confirmar_rechazar' => 'La sesión #:id vuelve al piloto con el motivo que indiques. Es obligatorio.',
        'motivo_label' => 'Motivo del rechazo',
        'motivo_placeholder' => 'Por qué se rechaza esta sesión…',
        // El campo vive dentro de la confirmación del rechazo: la ayuda
        // aclara a quién le llega lo que se escriba.
        'motivo_ayuda' => 'El piloto lo ve al recibir la sesión de vuelta.',
        // Invariante 4: el piloto de la sesión no puede decidir sobre su
        // propio vuelo, ni para aprobar ni para rechazar.
        'propia' => 'Eres el piloto de esta sesión: no puedes validarla ni rechazarla.',
        'propia_corto' => 'Tu sesión',
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
        'vacio_titulo' => 'Sin pausas registradas',
        'vacio_detalle' => 'Las pausas se registran cuando un piloto o supervisor pausan una sesión en la app de campo, con su causa atribuible. Vuelve a esta pantalla cuando se registre la primera.',
        'filtro_vacio_titulo' => 'Sin resultados para este período',
        'filtro_vacio_detalle' => 'No hay pausas registradas en el período elegido. Prueba con otro mes o limpia el filtro.',
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

        'titulo_crear' => 'Nueva pausa',
        'subtitulo_form' => 'Registra una pausa de sesión con su causa atribuible.',
        'seccion_datos' => 'Datos de la pausa',
        'campos_contador' => ':cantidad campos',
        'campo_sesion' => 'Sesión',
        'campo_sesion_placeholder' => 'Elige una sesión…',
        'campo_causa' => 'Causa',
        'campo_causa_placeholder' => 'Elige una causa…',
        'campo_fecha' => 'Fecha',
        'campo_fecha_placeholder' => 'Elige la fecha…',
        'campo_horario' => 'Horario de la pausa',
        'campo_horario_ayuda' => 'Hora de inicio y de fin, dentro del mismo día.',
        'estado_form' => 'Sin guardar',

        'error_sesion_requerida' => 'Elige la sesión en la que ocurrió la pausa.',
        'error_sesion_invalida' => 'La sesión elegida no existe.',
        'error_causa_requerida' => 'Elige la causa de la pausa.',
        'error_causa_invalida' => 'Esa causa no es válida.',
        'error_fecha_requerida' => 'Elige la fecha de la pausa.',
        'error_fecha_invalida' => 'La fecha de la pausa no es válida.',
        'error_inicio_requerido' => 'Elige la hora de inicio de la pausa.',
        'error_fin_requerido' => 'Elige la hora de fin de la pausa.',
        'error_hora_invalida' => 'La hora elegida no es válida.',
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
        'filtro_vacio_titulo' => 'Sin resultados para estos filtros',
        'filtro_vacio_detalle' => 'Ninguna alerta coincide con el estado o el tipo elegidos. Prueba con otros valores o limpia los filtros.',
        'kpi_pendientes' => 'Pendientes',
        'kpi_atendidas' => 'Atendidas',
        'col_tipo' => 'Tipo',
        'col_mensaje' => 'Detalle',
        'col_trabajo' => 'Trabajo',
        'col_estado' => 'Estado',
        'col_fecha' => 'Generada',
        'col_atendida' => 'Atendida',
        'sin_trabajo' => '—',
        'atender' => 'Atender',
        'confirmar_atender_titulo' => 'Atender alerta',
        'confirmar_atender' => '¿Confirmas que esta alerta ya fue atendida? Quedará registrada tu persona y la fecha.',
        'estado_cambio_de_a' => 'Cambio de estado: de :desde a :hacia',
        'atendida' => 'Alerta marcada como atendida.',
        'atendida_por' => 'Atendida por persona #:id el :fecha',
        'filtro_estado' => 'Estado',
        'filtro_tipo' => 'Tipo',
        'filtro_todos' => 'Todos',
        'paginacion_aria' => 'Paginación de alertas',
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

    // Mensajes de error.
    'errores' => [
        'dron_duplicado' => "Ya existe un dron activo con el identificador ':identificador'.",
        'equipo_trabajo_no_vigente' => 'La cuadrilla #:id no está vigente hoy.',
        'estadia_abierta_existente' => 'La cuadrilla #:id ya tiene una estadía en curso. Finalízala antes de registrar una nueva.',
        'estadia_salida_anterior_a_entrada' => 'La salida de la estadía debe ser posterior a su entrada.',
        'estadia_ya_finalizada' => 'La estadía #:id ya está finalizada: no se edita ni se vuelve a finalizar. Si hay un error, dala de baja y regístrala de nuevo.',
        'firma_evidencia_invalida' => 'La evidencia de firma no existe o no es del tipo firma_acta.',
        'firma_evidencia_reutilizada' => 'La evidencia #:id ya respalda la firma de otra acta.',
        'acta_ya_firmada_con_otra_evidencia' => 'El acta #:id ya está firmada con otra evidencia.',
        'firma_evidencia_conflicto_concurrente' => 'La evidencia de firma ya fue tomada por otra acta en un pedido concurrente.',
        'hectareas_asignadas_superan_lote' => 'Las hectáreas asignadas al lote #:lote de la orden #:orden (:asignado) superan las hectáreas solicitadas para ese lote (:solicitadas).',
        'lote_no_pertenece_a_orden' => 'El lote #:lote no pertenece a la orden #:orden.',
        'orden_no_editable' => "No se puede editar una orden en estado ':estado': solo se edita una orden emitida, vigente o pausada.",
        'orden_no_vigente_para_asignacion' => 'La orden #:id no está vigente: no admite asignación de equipos.',
        'orden_no_eliminable' => "Solo se puede eliminar una orden emitida. Esta orden está ':estado'; si no se llevará adelante, cancélala.",
        'contrato_no_admite_ordenes' => 'Solo un contrato En Ejecución admite órdenes de aplicación. El contrato #:id no lo está.',
        'contrato_inexistente' => 'El contrato #:id no existe.',
        'contrato_con_orden_abierta' => 'El contrato #:id ya tiene la aplicación :nro abierta. Ciérrala o cancélala antes de emitir otra.',
        'contrato_con_orden_abierta_simultanea' => 'Se emitió otra orden para el contrato #:id al mismo tiempo. Actualiza la pantalla y revisa la aplicación abierta.',
        'aplicaciones_completas' => 'El contrato #:id ya tiene sus :previstas aplicaciones. No se pueden emitir más órdenes.',
        'motivo_pausa_requerido' => 'Escribe el motivo de la pausa.',
        'motivo_cancelacion_requerido' => 'Escribe el motivo de la cancelación.',
        'pausa_fin_anterior_a_inicio' => 'El fin de la pausa debe ser posterior a su inicio.',
        'piloto_no_puede_decidir_su_propia_sesion' => 'El piloto de la sesión #:id no puede validar ni rechazar su propia sesión.',
        'sesion_no_disponible_por_estado' => "La sesión #:id está ':estado': solo una sesión 'cerrado' admite validación o rechazo.",
        'sesion_no_disponible_por_anulada' => 'La sesión #:id ya fue rechazada — no admite una nueva decisión.',
        'trabajo_no_cerrado_para_acta' => 'El trabajo #:id debe estar cerrado para generar su acta.',
        'trabajo_con_sesiones_sin_validar' => 'El trabajo #:id tiene sesiones vigentes sin validar: el acta certifica datos ya validados.',
        'acta_uuid_cliente_en_uso' => 'El uuid_cliente pedido para el acta del trabajo #:id ya está en uso por otra acta.',
        'transicion_acta_no_permitida' => "No se puede pasar un acta de ':desde' a ':hasta'.",
        'cierre_orden_hectareas_sin_asignar' => 'No se puede cerrar la orden: faltan :hectareas ha por asignar a algún equipo.',
        'motivo_correccion_requerido' => 'Escribe el motivo de la corrección.',
        'correccion_insumo_con_trabajos' => 'No se puede cambiar el insumo ni la dosis: la orden ya tiene órdenes de trabajo. Si hay un error, cancela la orden y emite otra.',
        'orden_trabajo_no_editable' => 'La Orden de Trabajo #:id ya tiene todos sus trabajos validados: no se puede editar.',
        'cierre_orden_sin_trabajos' => 'No se puede cerrar la orden: todavía no tiene ninguna orden de trabajo.',
        'cierre_orden_trabajos_abiertos' => 'No se puede cerrar la orden: todavía hay trabajos sin terminar (:cantidad). Todos los equipos deben terminar los suyos.',
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
        'solicitud_no_vigente' => 'La solicitud no existe, todavía no tiene un equipo asignado o ya terminó.',
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
            'subtitulo_solicitud' => 'Solicitud #:solicitud',
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

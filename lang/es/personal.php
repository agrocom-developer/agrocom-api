<?php

/*
 * Copy de vocabulario del dominio Personal (bases, personas operativas).
 * Mismo criterio que lang/es/comercial.php: las claves de estado nunca se
 * hardcodean en la vista, se resuelven acá contra el valor crudo que viaja
 * como dato (ADR 0013).
 */

return [

    // Clasificación operativa de una persona (RolOperativoPersona, espec
    // §4.2) — compartido por el listado y el formulario de personas.
    'roles' => [
        'piloto' => 'Piloto',
        'auxiliar' => 'Ayudante',
        'jefe_campo' => 'Jefe de campo',
        'encargado_operaciones' => 'Encargado de operaciones',
        'dueno' => 'Dueño',
    ],

    // Tipo de recurso asignable a un equipo de trabajo (RecursoTipoEquipo,
    // tarea 72).
    'recurso_tipo' => [
        'dron' => 'Dron',
        'bateria' => 'Batería',
    ],

    // Tarea 72 (HU-49, ADR 0015 punto 3), pasada a «cuadrilla» y ampliada con
    // alta de una sola vez, máquina de estados y accesorios por la tarea
    // "cuadrillas-estadias" (pedido del dueño, 19/9/2026): la cuadrilla es el
    // piloto, su ayudante (con posible segundo) y su equipamiento (dron,
    // baterías, accesorios). Sin campaña (la cuadrilla
    // es de Agrocom, trabaja para varias a la vez) — la vigencia es la de la
    // cuadrilla y la de cada integrante/recurso, no un período de campaña.
    //
    // El grupo de idioma conserva el nombre de clave `equipos_trabajo`
    // (identificador de código, no se traduce) — todos los VALORES dicen
    // «cuadrilla».
    'equipos_trabajo' => [
        'creado' => 'La cuadrilla se dio de alta correctamente.',
        'actualizado' => 'Los datos de la cuadrilla se actualizaron correctamente.',
        'eliminado' => 'La cuadrilla se eliminó correctamente.',
        'estado_cambiado' => 'El estado de la cuadrilla se actualizó correctamente.',
        'integrante_asignado' => 'El integrante se asignó correctamente.',
        'integrante_finalizado' => 'Se finalizó la vigencia del integrante.',
        'recurso_asignado' => 'El recurso se asignó correctamente.',
        'recurso_finalizado' => 'Se finalizó la vigencia del recurso.',
        'accesorio_agregado' => 'El accesorio se agregó a la cuadrilla.',
        'accesorio_quitado' => 'El accesorio se quitó de la cuadrilla.',
        'aviso_solapamiento' => 'Ya está vigente en otra cuadrilla en fechas que se superponen: :equipos. Se guardó igual — la operación real presta gente y equipamiento entre cuadrillas.',
        'error_codigo_requerido' => 'Ingresa el código de la cuadrilla.',
        'error_base_requerida' => 'Elige la base de la cuadrilla.',
        'error_estado_requerido' => 'Elige el estado de la cuadrilla.',
        'error_desde_requerida' => 'Elige la fecha de inicio de la vigencia.',
        'error_integrante_persona_requerida' => 'Elige la persona que integra la cuadrilla.',
        'error_integrante_rol_requerido' => 'Elige el rol de la persona en la cuadrilla.',
        'error_integrante_desde_requerida' => 'Elige la fecha desde la que integra la cuadrilla.',
        'error_recurso_tipo_requerido' => 'Elige el tipo de recurso.',
        'error_recurso_id_requerido' => 'Elige el recurso.',
        'error_recurso_desde_requerida' => 'Elige la fecha desde la que se asigna el recurso.',
        'error_vigencia_hasta_requerida' => 'Elige la fecha en que termina la vigencia.',
        // Alta de una sola vez (ArmarCuadrilla, "cuadrillas-estadias").
        'error_piloto_requerido' => 'Elige el piloto de la cuadrilla.',
        'error_ayudante_requerido' => 'Elige el ayudante de la cuadrilla.',
        'error_persona_repetida' => 'La misma persona no puede ocupar dos puestos de la cuadrilla.',
        'error_dron_requerido' => 'Elige el dron de la cuadrilla.',
        'error_baterias_repetidas' => 'No repitas la misma batería.',
        // Accesorios (AgregarAccesorioEquipo, "cuadrillas-estadias").
        'error_accesorio_requerido' => 'Elige un accesorio del catálogo o escribe el nombre de uno nuevo.',
        'error_accesorio_ambos' => 'Elige un accesorio del catálogo o escribe uno nuevo, no los dos.',
        'error_accesorio_cantidad_requerida' => 'Indica la cantidad del accesorio (al menos 1).',

        // Listado
        'titulo' => 'Cuadrillas',
        'subtitulo' => 'Cuadrillas de Agrocom, con su base, vigencia y estado.',
        'nuevo' => 'Nueva cuadrilla',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Código o nombre…',
        'filtro_base' => 'Base',
        'filtro_estado' => 'Estado',
        'filtro_todos' => 'Todas',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'filtro_vacio' => 'Ninguna cuadrilla coincide con esta búsqueda.',
        'filtro_vacio_titulo' => 'Sin resultados',
        'filtro_vacio_detalle' => 'Ninguna cuadrilla coincide con esta búsqueda. Prueba con otro término o quita los filtros.',
        'vacio_titulo' => 'Todavía no hay cuadrillas',
        'vacio_detalle' => 'Una cuadrilla se arma con su piloto, su ayudante y su equipamiento (dron, baterías) desde un único formulario de alta.',
        'col_codigo' => 'Código',
        'col_nombre' => 'Nombre',
        'col_integrantes' => 'Integrantes',
        'col_dron' => 'Dron',
        'sin_integrantes' => 'Sin integrantes',
        'sin_piloto' => 'Sin piloto',
        'sin_dron' => 'Sin dron',
        'confirmar_eliminar_titulo' => 'Eliminar cuadrilla',
        'col_base' => 'Base',
        'col_vigencia' => 'Vigencia',
        'col_estado' => 'Estado',
        'sin_nombre' => '—',
        'vigente' => 'vigente',
        'ver' => 'Ver ficha',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmas eliminar esta cuadrilla?',
        'paginacion_aria' => 'Paginación de cuadrillas',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario
        'titulo_crear' => 'Nueva cuadrilla',
        'titulo_editar' => 'Editar cuadrilla',
        'subtitulo_form' => 'Quiénes la integran, con qué equipamiento sale al campo y desde cuándo.',
        'seccion_datos' => 'Datos de la cuadrilla',
        'seccion_identificacion' => 'Identificación',
        'seccion_vigencia' => 'Vigencia',
        'seccion_integrantes' => 'Integrantes',
        'seccion_equipamiento' => 'Equipamiento',
        'seccion_accesorios' => 'Accesorios',
        'campos_contador' => ':cantidad campos',
        'campo_codigo' => 'Código',
        'campo_nombre' => 'Nombre',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Selecciona una base',
        'campo_estado' => 'Estado',
        'campo_desde' => 'Vigente desde',
        'campo_hasta' => 'Vigente hasta',
        'campo_hasta_ayuda' => 'Déjalo vacío si la cuadrilla sigue vigente.',
        'campo_baterias_ayuda' => 'La cantidad de baterías es cuántas marques.',
        // Formulario de alta y ficha de edición.
        'estado_pasos_aria' => 'Estado de la cuadrilla',
        'estado_cambio_de_a' => 'Cambio de estado: de :desde a :hacia',
        'confirmar_estado_titulo' => [
            'activo' => 'Activar cuadrilla',
            'inactivo' => 'Inactivar cuadrilla',
        ],
        'confirmar_estado' => [
            'activo' => 'La cuadrilla vuelve a estar disponible para órdenes de trabajo y estadías.',
            'inactivo' => 'La cuadrilla deja de ofrecerse en órdenes de trabajo y estadías. Sus registros anteriores no cambian y puedes reactivarla cuando quieras.',
        ],
        'accion_estado' => [
            'activo' => 'Activar',
            'inactivo' => 'Inactivar',
        ],
        'campo_codigo_ayuda' => 'Corto y único, como C1 o NORTE-2. Es lo que se ve en órdenes y estadías.',
        'campo_nombre_ayuda' => 'Opcional. Un nombre para reconocerla más fácil.',
        'campo_base_ayuda' => 'La base de la que sale la cuadrilla.',
        'campo_desde_ayuda' => 'Desde cuándo trabaja esta formación. Sus integrantes y su equipamiento nacen con esta misma fecha.',
        'campo_piloto' => 'Piloto',
        'campo_piloto_placeholder' => 'Elige al piloto…',
        'campo_piloto_ayuda' => 'Quien vuela el dron. Solo figura el personal con puesto de piloto.',
        'campo_ayudante' => 'Ayudante',
        'campo_ayudante_placeholder' => 'Elige al ayudante…',
        'campo_ayudante_ayuda' => 'Quien prepara el tanque y rota las baterías.',
        'campo_ayudante2' => 'Segundo ayudante',
        'campo_ayudante2_placeholder' => 'Elige al segundo ayudante…',
        'campo_ayudante2_ayuda' => 'Opcional. Para cuadrillas que salen con dos ayudantes.',
        'campo_dron' => 'Dron',
        'campo_dron_placeholder' => 'Elige el dron…',
        'campo_dron_ayuda' => 'El dron con el que trabaja la cuadrilla.',
        'campo_baterias' => 'Baterías',
        'baterias_vacio_titulo' => 'Sin baterías disponibles',
        'baterias_vacio_detalle' => 'No hay baterías activas en el catálogo. Puedes asignarlas después, desde la ficha de la cuadrilla.',
        'accion_nuevo_corto' => 'Nuevo',
        'accion_nueva_corto' => 'Nueva',
        'accion_nuevo_personal' => 'Dar de alta a una persona nueva',
        'accion_nueva_base' => 'Dar de alta una base nueva',
        'accion_nuevo_dron' => 'Dar de alta un dron nuevo',
        'volver_a_formulario_origen' => 'Volver al formulario de origen',

        // Tablas de detalle de la ficha de edición y sus diálogos.
        'contador_vigentes' => '{0} sin vigentes|{1} :cantidad vigente|[2,*] :cantidad vigentes',
        'contador_baterias' => '{0} sin baterías|{1} :cantidad batería|[2,*] :cantidad baterías',
        'contador_accesorios' => '{0} sin accesorios|{1} :cantidad accesorio|[2,*] :cantidad accesorios',
        'detalle_agregar' => 'Agregar',
        'detalle_finalizar' => 'Finalizar',
        'detalle_integrantes_vacio_titulo' => 'Sin integrantes',
        'detalle_equipamiento_vacio_titulo' => 'Sin equipamiento',
        'detalle_accesorios_vacio_titulo' => 'Sin accesorios',
        'detalle_agregar_integrante' => 'Agregar integrante',
        'detalle_agregar_integrante_mensaje' => 'Elige a la persona y el puesto que ocupa en la cuadrilla.',
        'detalle_integrantes_vacio_detalle' => 'Agrega al piloto y a su ayudante para que la cuadrilla pueda recibir trabajos.',
        'detalle_integrantes_paginacion' => 'Paginación de integrantes',
        'ficha_campo_rol_placeholder' => 'Elige el puesto…',
        'detalle_finalizar_integrante_titulo' => 'Finalizar integrante',
        'detalle_finalizar_integrante_mensaje' => ':persona deja de integrar la cuadrilla desde la fecha que indiques. Su paso queda en el historial.',
        'detalle_campo_hasta_cierre' => 'Último día en la cuadrilla',
        'detalle_hasta_ayuda' => 'Déjalo vacío si sigue vigente.',
        'detalle_agregar_equipamiento' => 'Agregar equipamiento',
        'detalle_agregar_equipamiento_mensaje' => 'Elige el tipo y después el recurso, entre los que ya existen en el sistema.',
        'detalle_equipamiento_vacio_detalle' => 'Agrega el dron y las baterías con los que sale la cuadrilla.',
        'detalle_equipamiento_paginacion' => 'Paginación de equipamiento',
        'detalle_col_recurso' => 'Recurso',
        'detalle_recurso_ayuda' => 'Cada batería se agrega por separado: la cantidad es cuántas tenga vigentes.',
        'ficha_campo_tipo_placeholder' => 'Elige el tipo…',
        'detalle_finalizar_recurso_titulo' => 'Finalizar equipamiento',
        'detalle_finalizar_recurso_mensaje' => ':recurso deja de estar asignado a la cuadrilla desde la fecha que indiques.',
        'detalle_agregar_accesorio_mensaje' => 'Elige un accesorio del catálogo o escribe uno nuevo, y cuántos lleva la cuadrilla.',
        'detalle_accesorios_vacio_detalle' => 'Agrega lo que la cuadrilla lleva al campo: machete, palas, linternas…',
        'detalle_accesorios_paginacion' => 'Paginación de accesorios',
        'detalle_accesorio_catalogo' => 'Accesorio del catálogo',
        'detalle_accesorio_catalogo_placeholder' => 'Elige un accesorio…',
        'detalle_accesorio_nuevo' => 'O escribe uno nuevo',
        'detalle_accesorio_nuevo_placeholder' => 'Por ejemplo: Machete',
        'detalle_accesorio_nuevo_ayuda' => 'Queda en el catálogo para las demás cuadrillas. Si la cuadrilla ya lo tiene, se actualiza la cantidad.',
        'confirmar_quitar_accesorio_titulo' => 'Quitar accesorio',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a cuadrillas',
        'confirmar_cambio_titulo' => 'Cambiar estado de la cuadrilla',
        'confirmar_cambio' => 'Cambiar estado',

        // Pasos de estado (`step-arrow`, §6.3.4, "cuadrillas-estadias"):
        // grupo anidado propio de esta pantalla, distinto del grupo plano
        // `personal.estado.*` que ya usan el listado y la ficha para el
        // badge — mismo contenido, ruta de clave que exige
        // `PasosDeEstado::armar()`/`::ayuda()` (mismo criterio que
        // `campania.campania.estado`/`estado_ayuda`).
        'estado' => [
            'activo' => 'Activa',
            'inactivo' => 'Inactiva',
        ],
        'estado_ayuda' => [
            'activo' => ':actual: la cuadrilla está operativa y se puede asignar a órdenes de trabajo.',
            'inactivo' => ':actual: la cuadrilla no se asigna a trabajos nuevos, pero conserva su historial.',
        ],

        // Ficha
        'ficha_titulo' => 'Cuadrilla :codigo',
        'ficha_subtitulo' => 'Integrantes, equipamiento y accesorios vigentes a la fecha elegida.',
        'ficha_volver' => 'Volver al listado',
        'ficha_campo_base' => 'Base',
        'ficha_campo_vigencia' => 'Vigencia de la cuadrilla',
        'ficha_selector_fecha' => 'Ver vigencia al',
        'ficha_consultar' => 'Consultar',
        'ficha_seccion_integrantes' => 'Integrantes',
        'ficha_seccion_accesorios' => 'Accesorios',
        'ficha_integrantes_vacio' => 'Nadie integraba esta cuadrilla en la fecha elegida.',
        'ficha_accesorios_vacio' => 'Esta cuadrilla no tenía accesorios asignados.',
        'ficha_finalizar' => 'Finalizar vigencia',
        'ficha_quitar' => 'Quitar',
        'ficha_asignar_integrante' => 'Agregar integrante',
        'ficha_campo_persona' => 'Persona',
        'ficha_campo_persona_placeholder' => 'Selecciona una persona',
        'ficha_campo_rol' => 'Rol en la cuadrilla',
        'ficha_campo_tipo' => 'Tipo de recurso',
        'ficha_campo_cantidad' => 'Cantidad',
        'ficha_campo_accesorio' => 'Accesorio',
        'ficha_campo_observacion' => 'Observación',
        'ficha_campo_observacion_placeholder' => 'Notas sobre el accesorio (opcional)',
        'ficha_seccion_recursos' => 'Recursos asignados',
        'ficha_recursos_vacio' => 'Esta cuadrilla no tenía recursos asignados en la fecha elegida.',
        'ficha_asignar_recurso' => 'Agregar equipamiento',
        'ficha_campo_recurso_placeholder' => 'Selecciona un recurso',
        'ficha_agregar_accesorio' => 'Agregar accesorio',
        'confirmar_quitar_accesorio' => '¿Confirmas que quieres quitar este accesorio?',

        // Resumen relacionado del aside de edit() (§6.3.1, "cuadrillas-estadias").
        'aside_estadias_titulo' => 'Estadías en hacienda',
        'aside_estadias_total' => 'Total',
        'aside_estadias_en_curso' => 'En curso',
        'aside_estadias_vacio_titulo' => 'Sin estadías registradas',
        'aside_estadias_vacio_detalle' => 'Todavía no se registró ninguna estadía de esta cuadrilla en una hacienda.',
        'aside_estadias_accion_ver' => 'Ver estadías',
        'aside_estadias_accion_crear' => 'Registrar estadía',
        'aside_trabajos_titulo' => 'Órdenes de trabajo',
        'aside_trabajos_total' => 'Total',
        'aside_trabajos_abiertos' => 'Abiertos',
        'aside_trabajos_vacio_titulo' => 'Sin trabajos asignados',
        'aside_trabajos_vacio_detalle' => 'Todavía no se le asignó ningún trabajo a esta cuadrilla.',
        'aside_trabajos_accion' => 'Ver trabajos',
        'aside_base_titulo' => 'Base',
        'aside_base_nombre' => 'Nombre',
        'aside_base_ubicacion' => 'Ubicación',
        'aside_base_accion' => 'Ver base',

        // Ficha (show, arquetipo Detalle, tarea 125): KPI, resumen de datos,
        // vínculos y actividad — la ficha pasó a ser de solo lectura, los
        // formularios de alta/finalizar viven en edit() (HU-101 punto 4).
        'kpi_integrantes' => 'Integrantes vigentes',
        'kpi_recursos' => 'Recursos vigentes',
        'kpi_vigencia' => 'Vigencia de la cuadrilla',
        'kpi_vigencia_sufijo' => 'días',
        'kpi_estadias' => 'Estadías en curso',
        'ficha_historico_titulo' => 'Integrantes históricos',
        'ficha_historico_vacio_titulo' => 'Sin historial',
        'ficha_historico_vacio_detalle' => 'Esta cuadrilla todavía no tuvo integrantes.',
        'ficha_integrantes_vacio_titulo' => 'Sin integrantes en esta fecha',
        'ficha_recursos_vacio_titulo' => 'Sin recursos en esta fecha',
        'aside_datos_titulo' => 'Datos de la cuadrilla',
        'aside_datos_base' => 'Base',
        'aside_datos_vigencia' => 'Vigencia',
        'aside_datos_creada_por' => 'Creada por',
        'aside_relacionado_titulo' => 'Relacionado',
        'aside_actividad_titulo' => 'Actividad',
        'vinculo_base' => 'Base',
        'vinculo_trabajos' => 'Órdenes de trabajo',
        'vinculo_trabajos_meta' => ':total en total, :abiertos abiertos',
        'vinculo_estadias' => 'Estadías en hacienda',
        'vinculo_estadias_meta' => ':total en total, :enCurso en curso',
        'actividad_creada' => 'Cuadrilla creada',
        'actividad_integrante_alta' => ':persona se suma como :rol',
        'actividad_integrante_baja' => ':persona deja la cuadrilla',
        'actividad_recurso_alta' => ':recurso se asigna a la cuadrilla',
        'actividad_recurso_baja' => ':recurso deja de estar asignado',
        'actividad_meta' => ':fecha · :autor',
    ],

    // Ficha de desempeño de una persona (HU-58, tarea 81): "¿qué hizo esta
    // persona esta campaña?" — hechos verificables por SESIÓN (nunca por
    // equipo de trabajo, ADR 0015 punto 3), sin puntaje ni ranking. Arquetipo
    // Detalle, mismo molde de filtros por GET que `equipos_trabajo.ficha_*`.
    'desempenio' => [
        'titulo' => 'Desempeño de :nombre',
        'subtitulo' => 'Sesiones, rechazos e incidencias de la persona en el rango elegido — hechos, no un puntaje.',
        'volver' => 'Volver a la persona',

        // Aside "Relacionado" (arquetipo Detalle, tarea 125).
        'aside_relacionado_titulo' => 'Relacionado',
        'vinculo_persona' => 'Ficha de la persona',

        'filtro_desde' => 'Desde',
        'filtro_hasta' => 'Hasta',
        'filtro_cliente' => 'Cliente',
        'filtro_cliente_placeholder' => 'Todos los clientes',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtros',

        'total_hectareas' => 'Hectáreas aplicadas',
        'total_sesiones_validadas' => 'Sesiones validadas',
        'total_sesiones_rechazadas' => 'Sesiones rechazadas',
        'total_incidencias' => 'Incidencias',

        // Estado vacío ilustrado (etapa 3, tarea 81): la persona no tiene
        // NINGUNA sesión ni rechazo en todo el rango — ver comentario de
        // cabecera de desempeno.blade.php. Distinto de 'sesiones_vacio' de
        // abajo, que es "hay datos pero este filtro puntual no trae nada".
        'vacio_titulo' => 'Sin actividad registrada',
        'vacio_detalle' => 'Esta persona no tiene sesiones en el rango elegido. Prueba ampliar las fechas, o vuelve cuando haya volado su primera aplicación.',

        'seccion_sesiones' => 'Sesiones',
        'sesiones_vacio_titulo' => 'Sin sesiones con este filtro',
        'sesiones_vacio' => 'No hay sesiones de esta persona con estos filtros.',
        'col_fecha' => 'Fecha',
        'col_rol' => 'Rol',
        'col_cliente' => 'Cliente',
        'col_solicitud' => 'Solicitud',
        'col_hectareas' => 'Hectáreas',
        'col_estado' => 'Estado',

        'seccion_rechazos' => 'Sesiones rechazadas',
        'rechazos_vacio_titulo' => 'Sin rechazos con este filtro',
        'rechazos_vacio' => 'Ninguna sesión rechazada con estos filtros.',
        'col_motivo' => 'Motivo',
        'col_rechazado_por' => 'Rechazado por',

        'seccion_incidencias' => 'Incidencias',
        'incidencias_vacio_titulo' => 'Sin incidencias con este filtro',
        'incidencias_vacio' => 'Sin incidencias registradas con estos filtros.',
        'col_tipo' => 'Tipo',
        'col_descripcion' => 'Descripción',
        'sin_descripcion' => '—',

        // Catálogo de EstadoSesion (Operaciones\Dominio\EstadoSesion) — copia
        // deliberada de `operaciones.estado`: esta pantalla es de `Personal`
        // y su lang file es su propio vocabulario (ADR 0013), no un import
        // cruzado del lang de otro módulo.
        'estado_sesion' => [
            'abierto' => 'Abierto',
            'cerrado' => 'Cerrado',
            'validado' => 'Validado',
        ],

        // Catálogo de TipoIncidencia (Operaciones\Dominio\TipoIncidencia) —
        // misma razón de copia que `estado_sesion` arriba.
        'incidencia_tipo' => [
            'caldo' => 'Caldo',
            'esc' => 'ESC',
            'bateria' => 'Batería',
            'mecanica' => 'Mecánica',
            'clima' => 'Clima',
            'otro' => 'Otro',
        ],
    ],

    // Mensajes de error.
    'errores' => [
        'equipo_trabajo_duplicado' => "Ya existe una cuadrilla activa con el código ':codigo'.",
        'recurso_equipo_invalido' => 'El :tipo #:recurso_id no existe o no está disponible.',
        'vigencia_integrante_solapada' => ':persona ya integra esta cuadrilla en una vigencia que se superpone con :rango.',
        'vigencia_recurso_solapada' => ':recurso ya está asignado a esta cuadrilla en una vigencia que se superpone con :rango.',
        'vigencia_indefinida' => 'vigente',
        // Máquina de estados de la cuadrilla ("cuadrillas-estadias", 19/9/2026).
        'transicion_equipo_trabajo_no_permitida' => 'No se puede pasar la cuadrilla de :desde a :hasta.',
    ],

    // Mensajes de los formularios.
    'validacion' => [
        'base_invalida' => 'La base seleccionada no es válida.',
        'persona_invalida' => 'La persona seleccionada no es válida.',
        'accesorio_invalido' => 'El accesorio seleccionado no es válido.',
    ],

];

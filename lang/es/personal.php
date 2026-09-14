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
        'auxiliar' => 'Auxiliar',
        'jefe_campo' => 'Jefe de campo',
        'encargado_operaciones' => 'Encargado de operaciones',
        'dueno' => 'Dueño',
    ],

    // Estado descriptivo de un equipo de trabajo (EstadoEquipoTrabajo, tarea
    // 72) — campo libre, sin máquina de estados (ver docblock del enum).
    'estado' => [
        'activo' => 'Activo',
        'inactivo' => 'Inactivo',
    ],

    // Rol de una persona DENTRO de un equipo de trabajo (RolEquipo, tarea 72).
    'rol_equipo' => [
        'piloto' => 'Piloto',
        'auxiliar' => 'Auxiliar',
    ],

    // Tipo de recurso asignable a un equipo de trabajo (RecursoTipoEquipo,
    // tarea 72).
    'recurso_tipo' => [
        'dron' => 'Dron',
        'vehiculo' => 'Vehículo',
        'generador' => 'Generador',
    ],

    // HU-26 (tarea 37): alta y mantenimiento de bases operativas. Catálogo
    // simple, sin sub-entidad — mismo molde que operaciones.drones.
    'bases' => [
        'creado' => 'La base se dio de alta correctamente.',
        'actualizado' => 'Los datos de la base se actualizaron correctamente.',
        'eliminado' => 'La base se dio de baja correctamente.',

        // Listado
        'titulo' => 'Bases',
        'subtitulo' => 'Bases operativas registradas, con su ubicación.',
        'nueva' => 'Nueva base',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Nombre o ubicación…',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio' => 'Todavía no hay bases registradas.',
        'filtro_vacio' => 'Ninguna base coincide con esta búsqueda.',
        'col_nombre' => 'Nombre',
        'col_ubicacion' => 'Ubicación',
        'sin_ubicacion' => '—',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmás la baja de esta base?',
        'paginacion_aria' => 'Paginación de bases',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario
        'titulo_crear' => 'Nueva base',
        'titulo_editar' => 'Editar base',
        'subtitulo_form' => 'Nombre y ubicación de la base.',
        'seccion_datos' => 'Datos de la base',
        'campos_contador' => ':cantidad campos',
        'campo_nombre' => 'Nombre',
        'campo_ubicacion' => 'Ubicación',
        'campo_latitud' => 'Latitud',
        'campo_longitud' => 'Longitud',
        'error_coordenada_incompleta' => 'Completá latitud y longitud juntas, o dejá las dos vacías.',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a bases',
    ],

    // HU-26 (tarea 37): alta y mantenimiento de personas operativas, con su
    // rol y tarifa por hectárea. `base_id` es opcional — mismo criterio de
    // select nativo que `cliente_id` en comercial.campos.
    'personas' => [
        'creado' => 'La persona se dio de alta correctamente.',
        'actualizado' => 'Los datos de la persona se actualizaron correctamente.',
        'eliminado' => 'La persona se dio de baja correctamente.',

        // Listado
        'titulo' => 'Personas',
        'subtitulo' => 'Personas operativas registradas, con su rol y tarifa por hectárea.',
        'nueva' => 'Nueva persona',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Nombre…',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio' => 'Todavía no hay personas registradas.',
        'filtro_vacio' => 'Ninguna persona coincide con esta búsqueda.',
        'col_nombre' => 'Nombre',
        'col_rol' => 'Rol',
        'col_base' => 'Base',
        'col_tarifa' => 'Tarifa/ha',
        'col_estado' => 'Estado',
        'sin_base' => 'Sin base asignada',
        'sin_tarifa' => '—',
        'tarifa_valor' => 'Bs :monto',
        'estado_activo' => 'Activa',
        'estado_inactivo' => 'Inactiva',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmás la baja de esta persona?',
        'paginacion_aria' => 'Paginación de personas',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // HU-58 (tarea 81): enlace desde la fila del listado a la ficha de
        // desempeño — gateado por `personal.persona.desempenio`, permiso
        // aparte de `.ver` (información sensible sobre la persona).
        'desempenio' => [
            'ver' => 'Ver desempeño',
        ],

        // Formulario
        'titulo_crear' => 'Nueva persona',
        'titulo_editar' => 'Editar persona',
        'subtitulo_form' => 'Nombre, rol, base y tarifa por hectárea de la persona.',
        'seccion_datos' => 'Datos de la persona',
        'campos_contador' => ':cantidad campos',
        'campo_nombre' => 'Nombre',
        'campo_rol' => 'Rol',
        'campo_rol_placeholder' => 'Seleccioná un rol',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Sin base asignada',
        'campo_tarifa' => 'Tarifa por hectárea',
        'campo_tarifa_ayuda' => 'Se usa para calcular el devengo de cada sesión validada. Cambiarla no altera los devengos ya generados.',
        'campo_activo' => 'Persona activa',
        'campo_activo_ayuda' => 'Una persona inactiva no puede asignarse a sesiones nuevas.',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a personas',
    ],

    // Tarea 72 (HU-49, ADR 0015 punto 3): equipos de trabajo — el piloto y
    // su auxiliar, con el equipamiento asignado. Sin campaña (el equipo es
    // de Agrocom, trabaja para varias a la vez) — la vigencia es la del
    // equipo y la de cada integrante/recurso, no un período de campaña.
    'equipos_trabajo' => [
        'creado' => 'El equipo de trabajo se dio de alta correctamente.',
        'actualizado' => 'Los datos del equipo de trabajo se actualizaron correctamente.',
        'eliminado' => 'El equipo de trabajo se dio de baja correctamente.',
        'integrante_asignado' => 'El integrante se asignó correctamente.',
        'integrante_finalizado' => 'Se finalizó la vigencia del integrante.',
        'recurso_asignado' => 'El recurso se asignó correctamente.',
        'recurso_finalizado' => 'Se finalizó la vigencia del recurso.',
        'aviso_solapamiento' => 'Ya está vigente en otro equipo en fechas que se superponen: :equipos. Se guardó igual — la operación real presta gente y equipamiento entre cuadrillas.',

        // Listado
        'titulo' => 'Equipos de trabajo',
        'subtitulo' => 'Cuadrillas de Agrocom, con su base, vigencia y estado.',
        'nuevo' => 'Nuevo equipo',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Código o nombre…',
        'filtro_base' => 'Base',
        'filtro_estado' => 'Estado',
        'filtro_todos' => 'Todos',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio' => 'Todavía no hay equipos de trabajo registrados.',
        'filtro_vacio' => 'Ningún equipo coincide con esta búsqueda.',
        'col_codigo' => 'Código',
        'col_nombre' => 'Nombre',
        'col_base' => 'Base',
        'col_vigencia' => 'Vigencia',
        'col_estado' => 'Estado',
        'sin_nombre' => '—',
        'vigente' => 'vigente',
        'ver' => 'Ver ficha',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmás la baja de este equipo de trabajo?',
        'paginacion_aria' => 'Paginación de equipos de trabajo',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario
        'titulo_crear' => 'Nuevo equipo de trabajo',
        'titulo_editar' => 'Editar equipo de trabajo',
        'subtitulo_form' => 'Código, base y vigencia del equipo.',
        'seccion_datos' => 'Datos del equipo',
        'campos_contador' => ':cantidad campos',
        'campo_codigo' => 'Código',
        'campo_nombre' => 'Nombre',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Seleccioná una base',
        'campo_estado' => 'Estado',
        'campo_desde' => 'Vigente desde',
        'campo_hasta' => 'Vigente hasta',
        'campo_hasta_ayuda' => 'Dejalo vacío si el equipo sigue vigente.',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a equipos de trabajo',

        // Ficha
        'ficha_titulo' => 'Equipo :codigo',
        'ficha_subtitulo' => 'Integrantes y recursos vigentes a la fecha elegida.',
        'ficha_volver' => 'Volver al listado',
        'ficha_campo_base' => 'Base',
        'ficha_campo_vigencia' => 'Vigencia del equipo',
        'ficha_selector_fecha' => 'Ver vigencia al',
        'ficha_consultar' => 'Consultar',
        'ficha_seccion_integrantes' => 'Integrantes',
        'ficha_integrantes_vacio' => 'Nadie integraba este equipo en la fecha elegida.',
        'ficha_finalizar' => 'Finalizar vigencia',
        'ficha_asignar_integrante' => 'Asignar integrante',
        'ficha_campo_persona' => 'Persona',
        'ficha_campo_persona_placeholder' => 'Seleccioná una persona',
        'ficha_campo_rol' => 'Rol en el equipo',
        'ficha_seccion_recursos' => 'Recursos asignados',
        'ficha_recursos_vacio' => 'Este equipo no tenía recursos asignados en la fecha elegida.',
        'ficha_asignar_recurso' => 'Asignar recurso',
        'ficha_campo_recurso_placeholder' => 'Seleccioná un recurso',
    ],

    // Ficha de desempeño de una persona (HU-58, tarea 81): "¿qué hizo esta
    // persona esta campaña?" — hechos verificables por SESIÓN (nunca por
    // equipo de trabajo, ADR 0015 punto 3), sin puntaje ni ranking. Arquetipo
    // Detalle, mismo molde de filtros por GET que `equipos_trabajo.ficha_*`.
    'desempenio' => [
        'titulo' => 'Desempeño de :nombre',
        'subtitulo' => 'Sesiones, rechazos e incidencias de la persona en el rango elegido — hechos, no un puntaje.',
        'volver' => 'Volver a personas',

        'filtro_desde' => 'Desde',
        'filtro_hasta' => 'Hasta',
        'filtro_cliente' => 'Cliente',
        'filtro_cliente_placeholder' => 'Todos los clientes',
        'filtro_campania' => 'Campaña',
        'filtro_campania_placeholder' => 'Todas las campañas',
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
        'vacio_detalle' => 'Esta persona no tiene sesiones en el rango elegido. Probá ampliar las fechas, o volvé cuando haya volado su primera aplicación.',

        'seccion_sesiones' => 'Sesiones',
        'sesiones_vacio' => 'No hay sesiones de esta persona con estos filtros.',
        'col_fecha' => 'Fecha',
        'col_rol' => 'Rol',
        'col_cliente' => 'Cliente',
        'col_campania' => 'Campaña',
        'col_lote' => 'Lote',
        'col_hectareas' => 'Hectáreas',
        'col_estado' => 'Estado',
        'sin_campania' => '—',

        'seccion_rechazos' => 'Sesiones rechazadas',
        'rechazos_vacio' => 'Ninguna sesión rechazada con estos filtros.',
        'col_motivo' => 'Motivo',
        'col_rechazado_por' => 'Rechazado por',

        'seccion_incidencias' => 'Incidencias',
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

];

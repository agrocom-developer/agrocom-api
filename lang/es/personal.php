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
        'estado_form' => 'Los cambios se guardan al confirmar.',
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
    ],

];

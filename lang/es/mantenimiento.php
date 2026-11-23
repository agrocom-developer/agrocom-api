<?php

/*
 * Copy de vocabulario del dominio Mantenimiento (HU-39, tarea 51: catálogo
 * de baterías; HU-82, tarea 97: ficha técnica del dron). Mismo criterio que
 * lang/es/operaciones.php: las claves de estado nunca se hardcodean en la
 * vista, se resuelven acá contra el valor crudo que viaja como dato (ADR
 * 0013).
 */

return [

    // Estado descriptivo de una batería (EstadoBateria): compartido por el
    // filtro, el badge del listado y el select del formulario.
    'estado_bateria' => [
        'activa' => 'Activa',
        'retirada' => 'Retirada',
        'mantenimiento' => 'En mantenimiento',
    ],

    // Estado de un dron. No tiene uno propio: figura siempre operativo.
    'estado_dron' => [
        'operativo' => 'Operativo',
    ],

    // Tarjeta del resumen relacionado de una batería: las cuadrillas que la
    // tienen asignada.
    'aside' => [
        'cuadrillas_titulo' => 'Cuadrillas',
        'cuadrillas_vigentes' => 'Vigentes',
        'cuadrillas_historial' => 'En el historial',
        'cuadrillas_actual' => 'Actual',
        'cuadrillas_vacio_titulo' => 'Sin cuadrillas',
        'cuadrillas_accion_ver' => 'Ver cuadrillas',
    ],

    // Pantalla de panel "Recursos › Baterías" (HU-39, tarea 51): ABM del
    // catálogo de baterías, con sus ciclos acumulados, estado, base
    // asignada y la alerta de retiro calculada por fila (ver
    // ListarBaterias).
    'baterias' => [
        'titulo' => 'Baterías',
        'subtitulo' => 'Catálogo de baterías registrado, con sus ciclos acumulados, estado y base asignada.',
        'nuevo' => 'Nueva batería',
        'filtro_busqueda_placeholder' => 'Identificador…',
        'filtro_base' => 'Base',
        'filtro_estado' => 'Estado',
        'filtro_todos' => 'Todos',
        'vacio_titulo' => 'Todavía no hay baterías registradas',
        'vacio_detalle' => 'Este catálogo se completa a medida que se dan de alta baterías nuevas. En cuanto se registre la primera batería, vas a verla acá.',
        'filtro_vacio_titulo' => 'Sin resultados para estos filtros',
        'filtro_vacio_detalle' => 'Ninguna batería coincide con la búsqueda o los filtros elegidos. Prueba con otro identificador o quita un filtro.',
        'col_identificador' => 'Identificador',
        'col_base' => 'Base',
        'col_ciclos' => 'Ciclos',
        'col_estado' => 'Estado',
        'col_alerta' => 'Alerta',
        'sin_base' => '—',
        'sin_alerta' => '—',
        'alerta_activa' => 'Alerta',
        'alerta_motivo' => [
            'ciclos' => 'Superó el umbral de ciclos recomendado.',
            'temperatura' => 'Tuvo una recarga con temperatura por encima del umbral.',
            'ciclos_temperatura' => 'Superó el umbral de ciclos y tuvo una recarga con temperatura por encima del umbral.',
        ],
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_eliminar_titulo' => 'Eliminar batería',
        'confirmar_baja' => '¿Confirmas la baja de esta batería?',
        'paginacion_aria' => 'Paginación de baterías',
        'titulo_crear' => 'Nueva batería',
        'titulo_editar' => 'Editar batería',
        'subtitulo_form' => 'Identificador, ciclos acumulados, base asignada y estado de la batería.',
        'seccion_datos' => 'Datos de la batería',
        'seccion_ciclos' => 'Ciclos de carga',
        'unidad_ciclos' => 'ciclos',
        'campos_contador' => ':cantidad campos',
        'campo_identificador' => 'Identificador',
        'campo_ciclos_inicial' => 'Ciclos inicial',
        'campo_ciclos_inicial_ayuda' => 'Con cuántos ciclos entró la batería al catálogo. No se puede editar después del alta.',
        'campo_ciclos' => 'Ciclos acumulados',
        'campo_ciclos_correccion_ayuda' => 'El contador sube solo con cada recarga registrada. Para bajarlo, completa el motivo de corrección de abajo.',
        'campo_motivo_correccion' => 'Motivo de corrección',
        'campo_motivo_correccion_ayuda' => 'Obligatorio solo si bajas los ciclos acumulados respecto al valor actual.',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Sin asignar',
        'campo_estado' => 'Estado',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'creado' => 'Batería creada correctamente.',
        'actualizado' => 'Batería actualizada correctamente.',
        'eliminado' => 'Batería dada de baja correctamente.',
        'volver' => 'Volver a baterías',
        'aside_cuadrillas_vacio_detalle' => 'Ninguna cuadrilla tiene asignada esta batería. Se asigna desde la ficha de la cuadrilla.',
        'aside_recargas_titulo' => 'Recargas',
        'aside_recargas_total' => 'Registradas',
        'aside_recargas_alertas' => 'Con alerta de temperatura',
        'aside_recargas_vacio_titulo' => 'Sin recargas',
        'aside_recargas_vacio_detalle' => 'Las recargas llegan desde la app de campo cuando el piloto cambia esta batería.',
    ],

    // Pantalla de panel "Recursos › Fichas de dron" (HU-82, tarea 97): alta
    // y mantenimiento de la ficha de inventario del dron (serie, chasis,
    // versión de software, región, serie del control, accesorios). Mismo
    // molde que 'baterias' arriba, sin estado ni alerta: es un ABM plano.
    'fichas_dron' => [
        'titulo' => 'Fichas de dron',
        'subtitulo' => 'Inventario de drones registrado, con serie, chasis, versión de software y accesorios.',
        'nuevo' => 'Nueva ficha',
        'filtro_busqueda_placeholder' => 'Identificador del dron…',
        'vacio_titulo' => 'Todavía no hay fichas de dron',
        'vacio_detalle' => 'El inventario de drones se registra a través de fichas de identificación. En cuanto se cargue la primera ficha, vas a verla acá.',
        'filtro_vacio_titulo' => 'Sin resultados para esta búsqueda',
        'filtro_vacio_detalle' => 'Ninguna ficha coincide con el término buscado. Prueba con otro identificador de dron.',
        'col_identificador' => 'Dron',
        'col_numero_serie' => 'N.° de serie',
        'col_chasis' => 'Chasis',
        'col_version_software' => 'Versión de software',
        'col_region' => 'Región',
        'col_accesorios' => 'Accesorios',
        'sin_dato' => '—',
        'accesorio_cargador_control' => 'Cargador de control',
        'accesorio_modem' => 'Módem',
        'accesorio_maletin' => 'Maletín',
        'sin_accesorios' => 'Sin accesorios',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_eliminar_titulo' => 'Eliminar ficha de dron',
        'confirmar_baja' => '¿Confirmas la baja de esta ficha?',
        'paginacion_aria' => 'Paginación de fichas de dron',
        'titulo_crear' => 'Nueva ficha de dron',
        'titulo_editar' => 'Editar ficha de dron',
        'subtitulo_form' => 'Identificador del dron, datos de serie y accesorios de la ficha.',
        'seccion_datos' => 'Datos de la ficha',
        'campos_contador' => ':cantidad campos',
        'campo_identificador' => 'Identificador del dron',
        'campo_identificador_ayuda' => 'Tiene que corresponder a un dron activo del catálogo de Operaciones.',
        'campo_numero_serie' => 'N.° de serie',
        'campo_chasis' => 'Chasis',
        'campo_version_software' => 'Versión de software',
        'campo_region' => 'Región',
        'campo_serie_control' => 'Serie del control',
        'seccion_accesorios' => 'Accesorios',
        'campo_tiene_cargador_control' => 'Tiene cargador de control',
        'campo_tiene_modem' => 'Tiene módem',
        'campo_tiene_maletin' => 'Tiene maletín',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'creado' => 'Ficha de dron creada correctamente.',
        'actualizado' => 'Ficha de dron actualizada correctamente.',
        'eliminado' => 'Ficha de dron dada de baja correctamente.',
        'volver' => 'Volver a fichas de dron',
        'aside_dron_titulo' => 'Dron',
        'aside_dron_identificador' => 'Identificador',
        'aside_dron_modelo' => 'Modelo',
        'aside_dron_sin_dato' => '—',
        'aside_dron_vacio_titulo' => 'Sin dron en el catálogo',
        'aside_dron_vacio_detalle' => 'No hay un dron activo con el identificador :identificador. Regístralo en el catálogo de drones con ese mismo identificador.',
        'aside_dron_accion_ver' => 'Ver dron',
        'aside_dron_accion_crear' => 'Crear dron',
    ],

    // Mensajes de error.
    'errores' => [
        'bateria_duplicada' => "Ya existe una batería activa con el identificador ':identificador'.",
        'correccion_ciclos_no_autorizada' => "La batería ':identificador' tiene :actual ciclos acumulados; bajar a :propuesto requiere un motivo de corrección.",
        'ficha_dron_duplicada' => "Ya existe una ficha activa para el dron con identificador ':identificador_dron'.",
    ],

    // Mensajes de los formularios.
    'validacion' => [
        'base_invalida' => 'La base seleccionada no es válida.',
        'dron_invalido' => 'El dron seleccionado no es válido.',
        'bateria_identificador_requerido' => 'Ingresa el identificador de la batería.',
        'identificador_dron_requerido' => 'Ingresa el identificador del dron.',
        'ciclos_inicial_requerido' => 'Ingresa los ciclos iniciales de la batería.',
        'ciclos_acumulados_requerido' => 'Ingresa los ciclos acumulados de la batería.',
        'bateria_estado_requerido' => 'Elige el estado de la batería.',
    ],

];

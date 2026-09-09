<?php

/*
 * Copy de vocabulario del dominio Mantenimiento (HU-40, tarea 50: flota de
 * vehículos; HU-39, tarea 51: catálogo de baterías). Mismo criterio que
 * lang/es/operaciones.php: las claves de estado nunca se hardcodean en la
 * vista, se resuelven acá contra el valor crudo que viaja como dato (ADR
 * 0013).
 */

return [

    // Estado descriptivo de un vehículo (EstadoVehiculo) — compartido por el
    // filtro, el badge del listado y el select del formulario.
    'estado' => [
        'activo' => 'Activo',
        'taller' => 'En taller',
        'de_baja' => 'De baja',
    ],

    // Estado descriptivo de una batería (EstadoBateria) — mismo patrón que
    // 'estado' arriba, namespace propio para no confundir "activo"
    // (vehículo) con "activa" (batería) aunque hoy no colisionen.
    'estado_bateria' => [
        'activa' => 'Activa',
        'retirada' => 'Retirada',
    ],

    // Pantalla de panel "Recursos › Vehículos" (HU-40, tarea 50): alta y
    // mantenimiento de la flota de vehículos, con su asignación a base y
    // estado.
    'vehiculos' => [
        'titulo' => 'Vehículos',
        'subtitulo' => 'Flota de vehículos registrada, con su base asignada y estado.',
        'nuevo' => 'Nuevo vehículo',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Identificador…',
        'filtro_base' => 'Base',
        'filtro_estado' => 'Estado',
        'filtro_todos' => 'Todos',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio' => 'Todavía no hay vehículos registrados.',
        'filtro_vacio' => 'Ningún vehículo coincide con estos filtros.',
        'col_identificador' => 'Identificador',
        'col_base' => 'Base',
        'col_estado' => 'Estado',
        'sin_base' => '—',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmás la baja de este vehículo?',
        'paginacion_aria' => 'Paginación de vehículos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
        'titulo_crear' => 'Nuevo vehículo',
        'titulo_editar' => 'Editar vehículo',
        'subtitulo_form' => 'Identificador, base asignada y estado del vehículo.',
        'seccion_datos' => 'Datos del vehículo',
        'campos_contador' => ':cantidad campos',
        'campo_identificador' => 'Identificador',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Sin asignar',
        'campo_estado' => 'Estado',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'creado' => 'Vehículo creado correctamente.',
        'actualizado' => 'Vehículo actualizado correctamente.',
        'eliminado' => 'Vehículo dado de baja correctamente.',
    ],

    // Pantalla de panel "Recursos › Generadores" (tarea 72, HU-49, ADR 0015
    // punto 3): ABM mínimo del catálogo de generadores. Mismo molde de
    // claves que 'vehiculos' arriba, con dos campos extra opcionales
    // (modelo, horas de uso).
    'generadores' => [
        'titulo' => 'Generadores',
        'subtitulo' => 'Catálogo de generadores registrado, con su modelo, base asignada, estado y horas de uso.',
        'nuevo' => 'Nuevo generador',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Identificador…',
        'filtro_base' => 'Base',
        'filtro_estado' => 'Estado',
        'filtro_todos' => 'Todos',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio' => 'Todavía no hay generadores registrados.',
        'filtro_vacio' => 'Ningún generador coincide con estos filtros.',
        'col_identificador' => 'Identificador',
        'col_modelo' => 'Modelo',
        'col_base' => 'Base',
        'col_estado' => 'Estado',
        'sin_modelo' => '—',
        'sin_base' => '—',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmás la baja de este generador?',
        'paginacion_aria' => 'Paginación de generadores',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
        'titulo_crear' => 'Nuevo generador',
        'titulo_editar' => 'Editar generador',
        'subtitulo_form' => 'Identificador, modelo, base asignada, estado y horas de uso del generador.',
        'seccion_datos' => 'Datos del generador',
        'campos_contador' => ':cantidad campos',
        'campo_identificador' => 'Identificador',
        'campo_modelo' => 'Modelo',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Sin asignar',
        'campo_estado' => 'Estado',
        'campo_horas_uso' => 'Horas de uso',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'creado' => 'Generador creado correctamente.',
        'actualizado' => 'Generador actualizado correctamente.',
        'eliminado' => 'Generador dado de baja correctamente.',
    ],

    // Pantalla de panel "Recursos › Baterías" (HU-39, tarea 51): ABM del
    // catálogo de baterías, con sus ciclos acumulados, estado, base
    // asignada y la alerta de retiro calculada por fila (ver
    // ListarBaterias). Mismo molde de claves que 'vehiculos' arriba.
    'baterias' => [
        'titulo' => 'Baterías',
        'subtitulo' => 'Catálogo de baterías registrado, con sus ciclos acumulados, estado y base asignada.',
        'nuevo' => 'Nueva batería',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Identificador…',
        'filtro_base' => 'Base',
        'filtro_estado' => 'Estado',
        'filtro_todos' => 'Todos',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio' => 'Todavía no hay baterías registradas.',
        'filtro_vacio' => 'Ninguna batería coincide con estos filtros.',
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
        'confirmar_baja' => '¿Confirmás la baja de esta batería?',
        'paginacion_aria' => 'Paginación de baterías',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
        'titulo_crear' => 'Nueva batería',
        'titulo_editar' => 'Editar batería',
        'subtitulo_form' => 'Identificador, ciclos acumulados, base asignada y estado de la batería.',
        'seccion_datos' => 'Datos de la batería',
        'campos_contador' => ':cantidad campos',
        'campo_identificador' => 'Identificador',
        'campo_ciclos' => 'Ciclos acumulados',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Sin asignar',
        'campo_estado' => 'Estado',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'creado' => 'Batería creada correctamente.',
        'actualizado' => 'Batería actualizada correctamente.',
        'eliminado' => 'Batería dada de baja correctamente.',
    ],

    // Estado de una orden de mantenimiento (EstadoOrdenMantenimiento) —
    // namespace propio, mismo criterio que 'estado_bateria': solo dos
    // valores, gobernados por la máquina de estados (a diferencia de
    // 'estado'/'estado_bateria', que son descriptivos libres).
    'estado_orden' => [
        'abierta' => 'Abierta',
        'cerrada' => 'Cerrada',
    ],

    // Tipo de orden de mantenimiento (man_ordenes_mantenimiento.tipo).
    'tipo_orden' => [
        'preventivo' => 'Preventivo',
        'correctivo' => 'Correctivo',
    ],

    // Tipo de equipo de una orden (man_ordenes_mantenimiento.equipo_tipo) —
    // sin 'generador': no existe catálogo de generadores en este alcance.
    'equipo_tipo' => [
        'dron' => 'Dron',
        'vehiculo' => 'Vehículo',
    ],

    // Pantalla de panel "Mantenimiento › Órdenes" (HU-37, tarea 53): apertura
    // y cierre de órdenes de mantenimiento, con la máquina de estados que
    // consume repuestos e imputa el gasto. Mismo molde de claves que
    // 'vehiculos'/'baterias' arriba para listado/alta; el detalle/cierre
    // ('edit') suma sus propias claves al final.
    'ordenes' => [
        'titulo' => 'Órdenes de mantenimiento',
        'subtitulo' => 'Órdenes abiertas y cerradas, con el equipo, el tipo y el estado de cada una.',
        'nuevo' => 'Nueva orden',
        'filtro_estado' => 'Estado',
        'filtro_equipo_tipo' => 'Equipo',
        'filtro_todos' => 'Todos',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio' => 'Todavía no hay órdenes de mantenimiento registradas.',
        'filtro_vacio' => 'Ninguna orden coincide con estos filtros.',
        'col_equipo' => 'Equipo',
        'col_tipo' => 'Tipo',
        'col_estado' => 'Estado',
        'col_fecha_apertura' => 'Apertura',
        'col_fecha_cierre' => 'Cierre',
        'sin_fecha_cierre' => '—',
        'ver_accion' => 'Ver',
        'paginacion_aria' => 'Paginación de órdenes de mantenimiento',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        'titulo_crear' => 'Nueva orden de mantenimiento',
        'subtitulo_form' => 'Equipo, tipo y descripción de la orden.',
        'seccion_datos' => 'Datos de la orden',
        'campos_contador' => ':cantidad campos',
        'campo_equipo_tipo' => 'Tipo de equipo',
        'campo_equipo_tipo_placeholder' => 'Seleccioná un tipo de equipo',
        'campo_equipo_dron' => 'Dron',
        'campo_equipo_dron_placeholder' => 'Seleccioná un dron',
        'campo_equipo_vehiculo' => 'Vehículo',
        'campo_equipo_vehiculo_placeholder' => 'Seleccioná un vehículo',
        'campo_tipo' => 'Tipo de orden',
        'campo_tipo_placeholder' => 'Seleccioná un tipo de orden',
        'campo_descripcion' => 'Descripción',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'creada' => 'Orden de mantenimiento creada correctamente.',

        'titulo_detalle' => 'Orden de mantenimiento',
        'subtitulo_detalle' => 'Detalle de la orden y, si está abierta, su cierre consumiendo repuestos.',
        'detalle_equipo' => 'Equipo',
        'detalle_tipo' => 'Tipo',
        'detalle_estado' => 'Estado',
        'detalle_descripcion' => 'Descripción',
        'detalle_fecha_apertura' => 'Fecha de apertura',
        'detalle_fecha_cierre' => 'Fecha de cierre',
        'detalle_gasto' => 'Gasto generado',
        'detalle_gasto_valor' => 'Gasto #:id',

        'seccion_cierre' => 'Cerrar orden',
        'seccion_cierre_ayuda' => 'El cierre descuenta el stock de cada repuesto y genera el gasto correspondiente. Si el stock de algún repuesto no alcanza, la orden no se cierra y no se descuenta ni se imputa nada.',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Seleccioná una base',
        'campo_cantidad' => 'Cantidad',
        'boton_cerrar' => 'Cerrar orden',

        // Selector por casillas de repuestos (HU-57, tarea 80): reemplaza la
        // fila repetible de dos selects + cantidad de la tarea 53.
        'campo_repuestos' => 'Repuestos',
        'campo_base_orden' => 'Base de la orden',
        'campo_base_orden_placeholder' => 'Elegí una base para ver la disponibilidad',
        'campo_base_orden_ayuda' => 'Se aplica a todos los repuestos elegidos. Podés cambiarla para uno en particular.',
        'repuesto_sin_base' => 'Elegí una base para ver la disponibilidad.',
        'repuesto_disponible' => 'Disponible en esta base: :cantidad',
        'repuesto_cambiar_base' => 'Cambiar base para este repuesto',
        'repuesto_aviso_stock' => 'La cantidad supera el stock disponible (:disponible).',
        'resumen_titulo' => 'Repuestos elegidos',
        'resumen_vacio' => 'Todavía no elegiste ningún repuesto.',
        'resumen_contador' => ':cantidad repuestos elegidos',
        'confirmar_cierre' => '¿Confirmás el cierre de esta orden? Esta acción descuenta stock y genera un gasto.',
        'cerrada' => 'Orden de mantenimiento cerrada correctamente.',
        'ya_cerrada' => 'Esta orden ya está cerrada.',
    ],

    // Pantalla de panel "Mantenimiento › Planes" (HU-38, tarea 54): ABM de
    // planes de mantenimiento preventivo por horas de vuelo de dron, con la
    // alerta calculada por fila (ver ListarPlanesMantenimiento). Mismo
    // molde de claves que 'vehiculos'/'baterias' arriba, sin filtros (la
    // lista completa de planes suele ser corta) ni columna de estado (no es
    // una máquina de estados).
    'planes' => [
        'titulo' => 'Planes de mantenimiento',
        'subtitulo' => 'Planes de mantenimiento preventivo por horas de vuelo, con la alerta activada cuando algún dron del modelo cruza el umbral.',
        'nuevo' => 'Nuevo plan',
        'vacio' => 'Todavía no hay planes de mantenimiento registrados.',
        'col_modelo' => 'Modelo',
        'col_tarea' => 'Tarea',
        'col_horas_umbral' => 'Umbral (horas)',
        'col_alerta' => 'Alerta',
        'sin_alerta' => '—',
        'alerta_activa' => 'Alerta',
        'alerta_titulo' => 'Algún dron de este modelo alcanzó o superó el umbral de horas de vuelo.',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmás la baja de este plan de mantenimiento?',
        'paginacion_aria' => 'Paginación de planes de mantenimiento',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
        'titulo_crear' => 'Nuevo plan de mantenimiento',
        'titulo_editar' => 'Editar plan de mantenimiento',
        'subtitulo_form' => 'Modelo de dron, tarea preventiva y umbral de horas de vuelo.',
        'seccion_datos' => 'Datos del plan',
        'campos_contador' => ':cantidad campos',
        'campo_modelo' => 'Modelo de dron',
        'campo_modelo_ayuda' => 'Texto libre: se compara contra el modelo cargado en cada dron.',
        'campo_tarea' => 'Tarea preventiva',
        'campo_horas_umbral' => 'Umbral de horas de vuelo',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'creado' => 'Plan de mantenimiento creado correctamente.',
        'actualizado' => 'Plan de mantenimiento actualizado correctamente.',
        'eliminado' => 'Plan de mantenimiento dado de baja correctamente.',
    ],

];

<?php

/*
 * Copy de vocabulario del dominio Mantenimiento (HU-40, tarea 50: flota de
 * vehículos). Mismo criterio que lang/es/operaciones.php: las claves de
 * estado nunca se hardcodean en la vista, se resuelven acá contra el valor
 * crudo que viaja como dato (ADR 0013).
 */

return [

    // Estado descriptivo de un vehículo (EstadoVehiculo) — compartido por el
    // filtro, el badge del listado y el select del formulario.
    'estado' => [
        'activo' => 'Activo',
        'taller' => 'En taller',
        'de_baja' => 'De baja',
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

];

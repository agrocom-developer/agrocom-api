<?php

/*
 * Copy de vocabulario del dominio Campania (ADR 0015 punto 1, tarea 69).
 * Mismo criterio que lang/es/comercial.php: las claves de estado nunca se
 * hardcodean en la vista, se resuelven acá contra el valor crudo que
 * viaja como dato (ADR 0013).
 */

return [

    'campania' => [
        'estado' => [
            'planificada' => 'Planificada',
            'abierta' => 'Abierta',
            'cerrada' => 'Cerrada',
        ],
        'estacion' => [
            'invierno' => 'Invierno',
            'verano' => 'Verano',
        ],
    ],

    // HU-46 (tarea 69): la campaña como eje transversal del sistema. ABM
    // simple con una máquina de estados encima — mismo molde que
    // `comercial.contratos`, sin sub-entidad.
    'campanias' => [
        'creado' => 'La campaña se dio de alta correctamente, en estado planificada.',
        'actualizado' => 'Los datos de la campaña se actualizaron correctamente.',
        'estado_cambiado' => 'El estado de la campaña se actualizó correctamente.',

        // Listado
        'titulo' => 'Campañas',
        'subtitulo' => 'La campaña es la temporada: cada contrato indica para cuál trabaja.',
        'nueva' => 'Nueva campaña',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Código o nombre',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio_titulo' => 'Todavía no hay campañas',
        'vacio_detalle' => 'Las campañas organizan contratos y gastos por temporada. En cuanto se dé de alta la primera, vas a verla acá.',
        'filtro_vacio' => 'Ninguna campaña coincide con la búsqueda.',
        'col_codigo' => 'Código',
        'col_nombre' => 'Nombre',
        'col_vigencia' => 'Vigencia',
        'col_estado' => 'Estado',
        'col_actividad' => 'Actividad',
        'actividad_activa' => 'Activa',
        'actividad_inactiva' => 'Inactiva',
        'editar' => 'Editar',
        'vigencia' => ':inicio – :fin',
        'paginacion_aria' => 'Paginación de campañas',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Cambio de estado (listado)
        'accion_abrir' => 'Abrir',
        'accion_cerrar' => 'Cerrar',
        'confirmar_abrir' => '¿Abrir esta campaña? Desde ese momento admite contratos y gastos.',
        'confirmar_cerrar' => '¿Cerrar esta campaña? No se puede volver a abrir desde el panel.',

        // Formulario (create/edit)
        'titulo_crear' => 'Nueva campaña',
        'titulo_editar' => 'Editar campaña',
        'subtitulo_form' => 'El código identifica la campaña en reportes y filtros (por ejemplo, 2025-2026).',
        'seccion_datos' => 'Datos de la campaña',
        'campos_contador' => ':cantidad campos',
        'campo_codigo' => 'Código',
        'campo_nombre' => 'Nombre',
        'campo_nombre_ayuda' => 'Si lo dejás vacío, se arma solo con la estación y los años (por ejemplo, Verano/2025/2026).',
        'campo_estacion' => 'Estación',
        'campo_estacion_placeholder' => 'Seleccioná una estación',
        'campo_fecha_inicio' => 'Fecha de inicio',
        'campo_fecha_fin' => 'Fecha de fin',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'volver' => 'Volver a campañas',

        // Errores de validación
        'error_codigo_requerido' => 'Ingresá un código para la campaña.',
        'error_fechas_rango' => 'La fecha de fin tiene que ser igual o posterior a la de inicio.',
    ],

];

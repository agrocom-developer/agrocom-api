<?php

/*
 * Copy de vocabulario del dominio Comercial (clientes, contratos, lotes).
 * Mismo criterio que lang/es/operaciones.php: las claves de estado nunca se
 * hardcodean en la vista, se resuelven acá contra el valor crudo que
 * viaja como dato (ADR 0013).
 */

return [

    // HU-22 (tarea 33): alta y mantenimiento de clientes con sus contactos.
    // Primer ABM completo del panel — molde de HU-23 a HU-27 y HU-45.
    'clientes' => [
        'creado' => 'El cliente se dio de alta correctamente.',
        'actualizado' => 'Los datos del cliente se actualizaron correctamente.',
        'eliminado' => 'El cliente se dio de baja correctamente.',

        // Listado
        'titulo' => 'Clientes',
        'subtitulo' => 'Alta y mantenimiento de clientes con sus contactos.',
        'nuevo' => 'Nuevo cliente',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Razón social o NIT',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio' => 'Todavía no se dio de alta ningún cliente.',
        'filtro_vacio' => 'Ningún cliente coincide con la búsqueda.',
        'col_razon_social' => 'Razón social',
        'col_nit' => 'NIT',
        'col_contactos' => 'Contactos',
        'sin_nit' => 'Sin NIT',
        'contactos_cantidad' => ':cantidad contactos',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja este cliente? Sus contratos y campos no se ven afectados.',
        'paginacion_aria' => 'Paginación de clientes',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo cliente',
        'titulo_editar' => 'Editar cliente',
        'subtitulo_form' => 'El cliente se guarda junto con sus contactos en una sola operación.',
        'seccion_datos' => 'Datos del cliente',
        'campos_contador' => ':cantidad campos',
        'campo_razon_social' => 'Razón social',
        'campo_nit' => 'NIT',
        'campo_nit_ayuda' => 'Opcional. No puede repetirse entre clientes activos.',
        'seccion_contactos' => 'Contactos',
        'contacto_agregar' => 'Agregar contacto',
        'contacto_quitar' => 'Quitar',
        'contacto_tipo' => 'Tipo',
        'contacto_tipo_placeholder' => 'Seleccioná un tipo',
        'contacto_tipo_opcion' => [
            'dueno' => 'Dueño',
            'agronomo' => 'Agrónomo',
            'encargado_propiedad' => 'Encargado de la propiedad',
            'otro' => 'Otro',
        ],
        'contacto_nombre' => 'Nombre',
        'contacto_telefono' => 'Teléfono',
        'contacto_email' => 'Email',
        'contacto_observaciones' => 'Observaciones',
        'estado_form' => 'Los cambios se guardan al confirmar.',
    ],

    'contrato' => [
        'estado' => [
            'borrador' => 'Borrador',
            'vigente' => 'Vigente',
            'finalizado' => 'Finalizado',
            'cancelado' => 'Cancelado',
        ],
    ],

    // HU-23 (tarea 34): administración de contratos con sus ventanas de
    // aplicación. Segundo ABM del panel — molde de la tarea 33 (clientes)
    // con una máquina de estados encima.
    'contratos' => [
        'creado' => 'El contrato se dio de alta correctamente, en estado borrador.',
        'actualizado' => 'Los datos del contrato se actualizaron correctamente.',
        'estado_cambiado' => 'El estado del contrato se actualizó correctamente.',

        // Listado
        'titulo' => 'Contratos',
        'subtitulo' => 'Administración de contratos con sus ventanas de aplicación y tarifa.',
        'nuevo' => 'Nuevo contrato',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Razón social del cliente',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio' => 'Todavía no se dio de alta ningún contrato.',
        'filtro_vacio' => 'Ningún contrato coincide con la búsqueda.',
        'col_cliente' => 'Cliente',
        'col_hectareas' => 'Hectáreas',
        'col_monto_total' => 'Monto total',
        'col_vigencia' => 'Vigencia',
        'col_estado' => 'Estado',
        'editar' => 'Editar',
        'vigencia_con_fin' => ':inicio – :fin',
        'vigencia_sin_fin' => 'Desde :inicio',
        'paginacion_aria' => 'Paginación de contratos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Cambio de estado (listado)
        'accion_activar' => 'Activar',
        'accion_finalizar' => 'Finalizar',
        'accion_cancelar' => 'Cancelar',
        'confirmar_activar' => '¿Pasar este contrato a vigente?',
        'confirmar_finalizar' => '¿Dar este contrato por finalizado?',
        'confirmar_cancelar' => '¿Cancelar este contrato? La baja no se puede deshacer desde el panel.',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo contrato',
        'titulo_editar' => 'Editar contrato',
        'subtitulo_form' => 'El contrato se guarda junto con sus ventanas de aplicación en una sola operación.',
        'seccion_datos' => 'Datos del contrato',
        'campos_contador' => ':cantidad campos',
        'campo_cliente' => 'Cliente',
        'campo_cliente_placeholder' => 'Seleccioná un cliente',
        'campo_hectareas_contratadas' => 'Hectáreas contratadas',
        'campo_aplicaciones_previstas' => 'Aplicaciones previstas',
        'campo_precio_ha' => 'Precio por hectárea (Bs)',
        'campo_monto_total_ayuda' => 'Se calcula automáticamente: hectáreas × aplicaciones × precio por hectárea.',
        'campo_adelanto_monto' => 'Adelanto (monto)',
        'campo_adelanto_pct' => 'Adelanto (%)',
        'campo_fecha_inicio' => 'Fecha de inicio',
        'campo_fecha_fin' => 'Fecha de fin',
        'campo_fecha_fin_ayuda' => 'Opcional. Si no se define, el contrato queda abierto.',

        'seccion_clima' => 'Parámetros de vuelo',
        'seccion_clima_ayuda' => 'Opcionales. En blanco, rige el valor por defecto del sistema.',
        'campo_viento_max_kmh' => 'Viento máximo (km/h)',
        'campo_temperatura_max_c' => 'Temperatura máxima (°C)',
        'campo_humedad_min_pct' => 'Humedad mínima (%)',
        'campo_humedad_max_pct' => 'Humedad máxima (%)',
        'campo_velocidad_max_kmh' => 'Velocidad máxima de vuelo (km/h)',
        'campo_umbral_reporte_avance_ha' => 'Umbral de reporte de avance (ha)',

        'seccion_ventanas' => 'Ventanas de aplicación',
        'ventana_agregar' => 'Agregar ventana',
        'ventana_quitar' => 'Quitar',
        'ventana_hora_inicio' => 'Desde',
        'ventana_hora_fin' => 'Hasta',
        'estado_form' => 'Los cambios se guardan al confirmar.',

        // Errores de validación
        'error_cliente_requerido' => 'Seleccioná un cliente.',
        'error_cliente_invalido' => 'El cliente seleccionado no es válido.',
        'error_ventanas_minimo' => 'Agregá al menos una ventana de aplicación.',
        'error_ventana_ajena' => 'Una de las ventanas enviadas no pertenece a este contrato.',
        'error_ventana_horas' => 'La hora de fin tiene que ser posterior a la hora de inicio.',
        'error_humedad_rango' => 'La humedad mínima no puede ser mayor que la máxima.',
    ],

    // HU-24 (tarea 35): administración de campos con sus lotes. Tercer ABM
    // del panel — mismo molde que clientes (tarea 33): un campo se
    // crea/edita con sus lotes en la misma operación, sin pantalla propia
    // para lotes.
    'campos' => [
        'creado' => 'El campo se dio de alta correctamente.',
        'actualizado' => 'Los datos del campo se actualizaron correctamente.',
        'eliminado' => 'El campo se dio de baja correctamente.',

        // Listado
        'titulo' => 'Campos',
        'subtitulo' => 'Administración de campos y sus lotes.',
        'nuevo' => 'Nuevo campo',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Nombre del campo o cliente',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio' => 'Todavía no se dio de alta ningún campo.',
        'filtro_vacio' => 'Ningún campo coincide con la búsqueda.',
        'col_nombre' => 'Campo',
        'col_cliente' => 'Cliente',
        'col_lotes' => 'Lotes',
        'col_hectareas' => 'Hectáreas',
        'lotes_cantidad' => ':cantidad lotes',
        'hectareas_valor' => ':cantidad ha',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja este campo? Sus lotes no se ven afectados.',
        'paginacion_aria' => 'Paginación de campos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo campo',
        'titulo_editar' => 'Editar campo',
        'subtitulo_form' => 'El campo se guarda junto con sus lotes en una sola operación.',
        'seccion_datos' => 'Datos del campo',
        'campos_contador' => ':cantidad campos',
        'campo_cliente' => 'Cliente',
        'campo_cliente_placeholder' => 'Seleccioná un cliente',
        'campo_nombre' => 'Nombre',
        'campo_ubicacion' => 'Ubicación',
        'seccion_lotes' => 'Lotes',
        'lote_agregar' => 'Agregar lote',
        'lote_quitar' => 'Quitar',
        'lote_codigo' => 'Código',
        'lote_hectareas' => 'Hectáreas',
        'lote_geometria' => 'Geometría (GeoJSON)',
        'lote_geometria_placeholder' => '{"type": "Polygon", "coordinates": [[[lng, lat], ...]]}',
        'lote_geometria_ayuda' => 'Opcional. Polígono en formato GeoJSON, se guarda y se dibuja tal cual — no se valida contra el estándar completo.',
        'lote_restricciones' => 'Restricciones',
        'lote_restricciones_placeholder' => 'Cables, viviendas, colmenas, vecinos sensibles',
        'estado_form' => 'Los cambios se guardan al confirmar.',

        // Errores de validación
        'error_geometria_invalida' => 'La geometría tiene que ser un JSON con "type": "Polygon" y "coordinates" como arreglo.',
    ],

    // HU-31 (tarea 45): "como encargado, quiero emitir la factura de un
    // trabajo desde su acta conformada, para cobrar sobre hectáreas ya
    // firmadas" — abre Sprint 9. Sin edición ni baja: una factura emitida es
    // un snapshot inmutable (ver `Aplicacion/EmitirFactura`).
    'facturas' => [
        'creada' => 'La factura se emitió correctamente.',

        // Listado
        'titulo' => 'Facturas',
        'subtitulo' => 'Facturas emitidas desde actas de conformidad ya firmadas.',
        'nueva' => 'Emitir factura',
        'vacio' => 'Todavía no se emitió ninguna factura.',
        'col_cliente' => 'Cliente',
        'col_acta' => 'Acta',
        'col_hectareas' => 'Hectáreas facturadas',
        'col_precio_ha' => 'Precio/ha',
        'col_monto' => 'Monto',
        'col_fecha_emision' => 'Fecha de emisión',
        'acta_valor' => 'Acta #:id',
        'monto_valor' => 'Bs :monto',
        'precio_ha_valor' => 'Bs :monto',
        'paginacion_aria' => 'Paginación de facturas',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create)
        'titulo_crear' => 'Emitir factura',
        'subtitulo_form' => 'El monto se calcula automáticamente: hectáreas conformadas del acta × precio por hectárea del contrato.',
        'seccion_datos' => 'Datos de la factura',
        'campos_contador' => ':cantidad campo',
        'campo_acta' => 'Acta conformada',
        'campo_acta_placeholder' => 'Seleccioná un acta firmada',
        'campo_acta_opcion' => ':cliente — Acta #:id (:hectareas ha)',
        'sin_actas_disponibles' => 'No hay actas firmadas pendientes de facturar.',
        'estado_form' => 'El monto se calcula al confirmar.',
        'error_acta_requerida' => 'Seleccioná un acta conformada.',
        'error_acta_invalida' => 'El acta seleccionada no está disponible para facturar.',
    ],

];

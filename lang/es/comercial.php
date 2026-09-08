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
        'filtro_campania' => 'Campaña',
        'filtro_campania_placeholder' => 'Todas las campañas',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio' => 'Todavía no se dio de alta ningún contrato.',
        'filtro_vacio' => 'Ningún contrato coincide con la búsqueda.',
        'col_cliente' => 'Cliente',
        'col_campania' => 'Campaña',
        'col_hectareas' => 'Hectáreas',
        'col_monto_total' => 'Monto total',
        'col_vigencia' => 'Vigencia',
        'col_ventanas' => 'Ventanas',
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
        'campo_campania' => 'Campaña',
        'campo_campania_placeholder' => 'Seleccioná primero un cliente',
        'campo_campania_ayuda' => 'Solo se listan las campañas del cliente elegido (ADR 0015): el contrato es con un cliente y para una campaña suya.',
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
        'campo_altura_vuelo_m' => 'Altura de vuelo (m)',

        // Ventanas de aplicación (HU-47, tarea 70): "Día completo" convive con
        // las filas cargadas, nunca un booleano en la base (ADR 0015 punto 5)
        // — el interruptor es puramente de presentación, arranca encendido
        // sin ventanas cargadas y las filas se muestran/ocultan según su
        // estado (resources/js/pages/contratos-form.js).
        'seccion_ventanas' => 'Ventanas de aplicación',
        'ventana_dia_completo' => 'Día completo',
        'ventana_dia_completo_ayuda' => 'Sin restricción de horario. Apagalo para cargar franjas horarias.',
        'ventana_agregar' => 'Agregar ventana',
        'ventana_quitar' => 'Quitar',
        'ventana_hora_inicio' => 'Desde',
        'ventana_hora_fin' => 'Hasta',
        'ventana_rango' => ':inicio – :fin',
        'estado_form' => 'Los cambios se guardan al confirmar.',

        // Errores de validación
        'error_cliente_requerido' => 'Seleccioná un cliente.',
        'error_cliente_invalido' => 'El cliente seleccionado no es válido.',
        'error_campania_requerida' => 'Seleccioná la campaña del contrato.',
        'error_campania_invalida' => 'La campaña seleccionada no es válida.',
        'error_ventana_ajena' => 'Una de las ventanas enviadas no pertenece a este contrato.',
        'error_ventana_incompleta' => 'Completá la hora de inicio y la hora de fin de la ventana.',
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
        'lote_geometria' => 'Perímetro del lote',
        'lote_geometria_ayuda' => 'Opcional. Dibujá el contorno del lote sobre la imagen satelital con la herramienta de polígono. Se guarda como GeoJSON y es lo que colorea el mapa del tablero.',
        'lote_usar_superficie' => 'Usar como hectáreas',
        'lote_restricciones' => 'Restricciones',
        'lote_restricciones_placeholder' => 'Cables, viviendas, colmenas, vecinos sensibles',
        'estado_form' => 'Los cambios se guardan al confirmar.',

        // Errores de validación
        'error_geometria_invalida' => 'La geometría tiene que ser un JSON con "type": "Polygon" y "coordinates" como arreglo.',
    ],

    // Tarea 77 (HU-54, etapa 2): ficha propia de un lote — antes solo se
    // podía tocar entrando por su propiedad (`campos`, arriba). Mismo molde
    // de listado/formulario que el resto del panel; los rótulos
    // `lote_codigo`/`lote_hectareas`/`lote_geometria*`/`lote_restricciones*`
    // del bloque `campos` de arriba se reusan tal cual (mismo copy, misma
    // fila `_lote-fila.blade.php` compartida por las dos pantallas).
    'lotes' => [
        'creado' => 'El lote se dio de alta correctamente.',
        'actualizado' => 'Los datos del lote se actualizaron correctamente.',
        'eliminado' => 'El lote se dio de baja correctamente.',

        // Listado
        'titulo' => 'Lotes',
        'subtitulo' => 'Listado y ficha de lotes, con su perímetro en el mapa.',
        'nuevo' => 'Nuevo lote',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Código del lote',
        'filtro_cliente' => 'Cliente',
        'filtro_propiedad' => 'Propiedad',
        'filtro_todos' => 'Todas',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar filtros',
        'vacio' => 'Todavía no se dio de alta ningún lote.',
        'filtro_vacio' => 'Ningún lote coincide con el filtro.',
        'col_codigo' => 'Código',
        'col_propiedad' => 'Propiedad',
        'col_cliente' => 'Cliente',
        'col_hectareas' => 'Hectáreas',
        'hectareas_valor' => ':cantidad ha',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja este lote?',
        'paginacion_aria' => 'Paginación de lotes',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo lote',
        'titulo_editar' => 'Editar lote',
        'subtitulo_form' => 'Alta o edición de un lote suelto, con su perímetro en el mapa.',
        'seccion_datos' => 'Propiedad',
        'campos_contador' => ':cantidad campos',
        'campo_cliente' => 'Cliente',
        'campo_cliente_placeholder' => 'Todos los clientes',
        'campo_cliente_ayuda' => 'Filtra las propiedades de abajo. No se guarda: la propiedad ya define el cliente del lote.',
        'campo_propiedad' => 'Propiedad',
        'campo_propiedad_placeholder' => 'Seleccioná una propiedad',
        'campo_propiedad_opcion' => ':nombre — :cliente',
        'seccion_lote' => 'Datos del lote',
        'estado_form' => 'Los cambios se guardan al confirmar.',

        // Errores de validación
        'error_geometria_invalida' => 'La geometría tiene que ser un JSON con "type": "Polygon" y "coordinates" como arreglo.',
    ],

    // HU-48 (tarea 71, ADR 0015 punto 4): catálogo de cultivos. Cuarto ABM
    // simple del panel — mismo molde que `lotes`/`bases`, sin sub-entidad.
    'cultivos' => [
        'creado' => 'El cultivo se dio de alta correctamente.',
        'actualizado' => 'Los datos del cultivo se actualizaron correctamente.',
        'eliminado' => 'El cultivo se dio de baja correctamente.',

        // Listado
        'titulo' => 'Cultivos',
        'subtitulo' => 'Catálogo de cultivos disponibles para la siembra por lote y campaña.',
        'nuevo' => 'Nuevo cultivo',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Nombre del cultivo',
        'filtrar' => 'Buscar',
        'limpiar_filtro' => 'Limpiar búsqueda',
        'vacio' => 'Todavía no se dio de alta ningún cultivo.',
        'filtro_vacio' => 'Ningún cultivo coincide con la búsqueda.',
        'col_nombre' => 'Cultivo',
        'col_estado' => 'Estado',
        'estado_activo' => 'Activo',
        'estado_inactivo' => 'Inactivo',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja este cultivo? Las siembras que ya lo tienen cargado no se ven afectadas.',
        'paginacion_aria' => 'Paginación de cultivos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario (create/edit)
        'titulo_crear' => 'Nuevo cultivo',
        'titulo_editar' => 'Editar cultivo',
        'subtitulo_form' => 'Catálogo simple: nombre y disponibilidad.',
        'seccion_datos' => 'Datos del cultivo',
        'campos_contador' => ':cantidad campos',
        'campo_nombre' => 'Nombre',
        'campo_activo' => 'Activo',
        'campo_activo_ayuda' => 'Un cultivo inactivo deja de ofrecerse para nuevas siembras, sin afectar las ya cargadas.',
        'estado_form' => 'Los cambios se guardan al confirmar.',
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

    // HU-32 (tarea 46): "como dueño, quiero un reporte comercial de avance
    // por cliente, contrato y campaña, para saber cuánto queda por aplicar
    // y por cobrar" — cierra Sprint 9. Solo lectura, exportable a CSV.
    'reportes_comerciales' => [
        'titulo' => 'Avance comercial',
        'subtitulo' => 'Hectáreas contratadas, aplicadas y facturadas por contrato.',
        'exportar' => 'Exportar CSV',
        'vacio' => 'Todavía no hay contratos para mostrar.',
        'filtro_vacio' => 'Ningún contrato coincide con el filtro.',

        'filtro_cliente' => 'Cliente',
        'filtro_contrato' => 'Contrato',
        'filtro_todos' => 'Todos',
        'filtro_contrato_opcion' => 'Contrato #:id — :cliente',
        'filtrar' => 'Filtrar',
        'limpiar_filtros' => 'Limpiar filtros',

        'col_cliente' => 'Cliente',
        'col_contrato' => 'Contrato',
        'col_hectareas_contratadas' => 'Ha. contratadas',
        'col_hectareas_aplicadas' => 'Ha. aplicadas',
        'col_hectareas_facturadas' => 'Ha. facturadas',
        'col_monto_facturado' => 'Monto facturado',
        'contrato_valor' => 'Contrato #:id',
        'hectareas_valor' => ':cantidad ha',
        'monto_valor' => 'Bs :monto',
    ],

];

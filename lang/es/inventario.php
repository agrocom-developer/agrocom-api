<?php

/*
 * Copy de vocabulario del dominio Inventario (HU-36, tarea 52): catálogo de
 * repuestos con stock por base y alerta de mínimo. Mismo criterio que
 * lang/es/mantenimiento.php: las claves de tipo/sentido nunca se hardcodean
 * en la vista, se resuelven acá contra el valor crudo que viaja como dato
 * (ADR 0013).
 */

return [

    // Tipo de movimiento de stock (TipoMovimientoInventario) — compartido
    // por el select del formulario y el listado de movimientos.
    'tipo_movimiento' => [
        'compra' => 'Compra',
        'salida' => 'Salida',
        'ajuste' => 'Ajuste',
        'traslado' => 'Traslado entre bases',
    ],

    // Sentido de un ajuste (SentidoAjusteInventario) — solo se muestra
    // cuando el tipo elegido es "ajuste".
    'sentido_ajuste' => [
        'incremento' => 'Suma stock',
        'decremento' => 'Resta stock',
    ],

    // Pantalla de panel "Mantenimiento › Repuestos" (HU-36, tarea 52): alta
    // y mantenimiento del catálogo de repuestos.
    'repuestos' => [
        'titulo' => 'Repuestos',
        'subtitulo' => 'Catálogo de repuestos, con el costo de su última compra.',
        'nuevo' => 'Nuevo repuesto',
        'filtro_busqueda_placeholder' => 'Código o descripción…',
        'filtro_vacio_titulo' => 'Ningún repuesto coincide',
        'filtro_vacio_detalle' => 'Prueba con otro código o descripción, o quita la búsqueda.',
        'vacio_titulo' => 'Todavía no hay repuestos registrados',
        'vacio_detalle' => 'Cada pieza del equipamiento de Agrocom se registra acá. Crea el primero con el botón de arriba.',
        'col_codigo' => 'Código',
        'col_descripcion' => 'Descripción',
        'col_unidad' => 'Unidad',
        'col_costo' => 'Última compra',
        'costo_valor' => 'Bs :monto',
        'sin_costo' => '—',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja_titulo' => 'Dar de baja el repuesto',
        'confirmar_baja' => '¿Confirmas la baja de este repuesto?',
        'paginacion_aria' => 'Paginación de repuestos',
        'titulo_crear' => 'Nuevo repuesto',
        'titulo_editar' => 'Editar repuesto',
        'subtitulo_form' => 'Código, descripción, unidad y costo de la última compra.',
        'seccion_datos' => 'Datos del repuesto',
        'campos_contador' => ':cantidad campos',
        'campo_codigo' => 'Código',
        'campo_codigo_ayuda' => 'No puede repetirse entre los repuestos activos.',
        'campo_descripcion' => 'Descripción',
        'campo_unidad' => 'Unidad',
        'campo_unidad_placeholder' => 'kg, litros, unidad…',
        'campo_unidad_ayuda' => 'La medida con la que se cuenta el stock.',
        'campo_costo' => 'Costo de la última compra',
        'campo_costo_placeholder' => 'Sin compras todavía',
        'campo_costo_ayuda' => 'Se actualiza con cada compra que registres en Stock. Puedes corregirlo a mano.',
        'unidad_moneda' => 'Bs',
        'estado_form' => 'Sin guardar',
        'creado' => 'Repuesto creado.',
        'actualizado' => 'Repuesto actualizado.',
        'eliminado' => 'Repuesto dado de baja.',
        'volver' => 'Volver a repuestos',
        'error_codigo_requerido' => 'Ingresa el código del repuesto.',
        'error_descripcion_requerida' => 'Ingresa la descripción del repuesto.',
        'error_unidad_requerida' => 'Ingresa la unidad del repuesto.',
    ],

    // Resumen relacionado de la ficha de un repuesto (tarea 117): lo que hay
    // de él en las bases, sus últimos movimientos y las órdenes de
    // mantenimiento que lo consumieron.
    'aside' => [
        'accion_registrar_movimiento' => 'Registrar movimiento',
        'existencias_titulo' => 'Existencias',
        'existencias_total' => 'Existencia total',
        'existencias_bases' => 'Bases con stock',
        'existencias_bajo_minimo' => 'Bajo el mínimo',
        'existencias_accion_ver' => 'Ver stock',
        'existencias_vacio_titulo' => 'Sin existencias',
        'existencias_vacio_detalle' => 'Este repuesto todavía no tiene stock en ninguna base. Se crea con su primer movimiento.',
        'movimientos_titulo' => 'Últimos movimientos',
        'movimientos_total' => 'Movimientos registrados',
        'movimiento_linea' => ':tipo · :fecha',
        'tipo_corto' => [
            'compra' => 'Compra',
            'salida' => 'Salida',
            'ajuste' => 'Ajuste',
            'traslado' => 'Traslado',
        ],
        'movimientos_vacio_titulo' => 'Sin movimientos',
        'movimientos_vacio_detalle' => 'Cada compra, salida, ajuste o traslado de este repuesto quedará anotado acá.',
        'ordenes_titulo' => 'Órdenes de mantenimiento',
        'ordenes_total' => 'Órdenes que lo consumieron',
        'ordenes_correctivas' => 'Correctivas',
        'ordenes_unidades' => 'Unidades consumidas',
        'ordenes_ultimo_cierre' => 'Último cierre',
        'ordenes_accion_ver' => 'Ver órdenes de mantenimiento',
        'ordenes_accion_nueva' => 'Nueva orden de mantenimiento',
        'ordenes_vacio_titulo' => 'Ninguna orden lo consumió',
        'ordenes_vacio_detalle' => 'Cuando una orden de mantenimiento se cierre usando este repuesto, aparecerá acá.',
    ],

    // Pantalla de panel "Mantenimiento › Stock" (HU-36, tarea 52): stock
    // agregado por repuesto y base, con alerta de mínimo, y alta de
    // movimientos.
    'stock' => [
        'titulo' => 'Stock por base',
        'subtitulo' => 'Stock agregado por repuesto y base, con alerta al cruzar el mínimo.',
        'nuevo_movimiento' => 'Registrar movimiento',
        'filtro_busqueda_placeholder' => 'Código o descripción del repuesto…',
        'filtro_base' => 'Base',
        'filtro_todos' => 'Todas',
        'filtro_vacio_titulo' => 'Ningún registro coincide',
        'filtro_vacio_detalle' => 'Prueba con otra base o con otro código, o quita los filtros.',
        'vacio_titulo' => 'Todavía no hay stock registrado',
        'vacio_detalle' => 'El stock se crea con el primer movimiento de compra, salida o ajuste. Registra uno con el botón de arriba.',
        'kpi_repuestos' => 'Repuestos',
        'kpi_repuestos_pie' => '{0} Sin bases|{1} En 1 base|[2,*] En :cantidad bases',
        'kpi_bajo_minimo' => 'Bajo el mínimo',
        'kpi_bajo_minimo_pie' => 'Piden reposición',
        'kpi_sin_existencias' => 'Sin existencias',
        'kpi_sin_existencias_pie' => 'Repuesto y base en cero',
        'kpi_movimientos' => 'Movimientos',
        'kpi_movimientos_pie' => 'Últimos :dias días',
        'col_codigo' => 'Repuesto',
        'col_base' => 'Base',
        'col_cantidad' => 'Cantidad',
        'col_minimo' => 'Mínimo',
        'col_alerta' => 'Alerta',
        'alerta_activa' => 'Por debajo del mínimo',
        'alerta_sin_existencias' => 'Sin existencias',
        'sin_alerta' => '—',
        'paginacion_aria' => 'Paginación de stock',
        'movimiento_registrado' => 'Movimiento registrado.',
        'titulo_crear' => 'Registrar movimiento de stock',
        'subtitulo_form' => 'Compra, salida, ajuste o traslado entre bases.',
        'seccion_datos' => 'Datos del movimiento',
        'campos_contador' => ':cantidad campos',
        'campo_tipo' => 'Tipo de movimiento',
        'campo_repuesto' => 'Repuesto',
        'campo_repuesto_placeholder' => 'Seleccionar repuesto…',
        'campo_base' => 'Base',
        'campo_base_placeholder' => 'Seleccionar base…',
        'campo_base_ayuda' => 'En un traslado es la base de origen.',
        'campo_base_destino' => 'Base de destino',
        'campo_base_destino_placeholder' => 'Seleccionar base…',
        'campo_cantidad' => 'Cantidad',
        'campo_cantidad_ayuda' => 'Siempre positiva: el tipo de movimiento decide si suma o resta.',
        'campo_sentido' => 'Sentido del ajuste',
        'campo_costo_unitario' => 'Costo unitario',
        'campo_costo_unitario_ayuda' => 'Pasa a ser el costo de la última compra del repuesto.',
        'unidad_moneda' => 'Bs',
        'campo_motivo' => 'Motivo',
        'campo_motivo_placeholder' => 'Por qué se hace este movimiento',
        'estado_form' => 'Sin guardar',
        'volver' => 'Volver a stock',
        'error_tipo_requerido' => 'Elige el tipo de movimiento.',
        'error_repuesto_requerido' => 'Elige el repuesto.',
        'error_base_requerida' => 'Elige la base.',
        'error_cantidad_requerida' => 'Ingresa la cantidad.',
    ],

    // Mensajes de error.
    'errores' => [
        'stock_insuficiente' => "Stock insuficiente de ':codigo_repuesto' en la base #:base_id: disponible :disponible, solicitado :solicitada.",
        'repuesto_duplicado' => "Ya existe un repuesto activo con el código ':codigo'.",
        'traslado_sin_stock_destino' => 'Un traslado necesita la fila de stock de destino ya bloqueada.',
    ],

    // Mensajes de los formularios.
    'validacion' => [
        'base_destino_distinta' => 'La base de destino tiene que ser distinta de la base de origen.',
        'traslado_base_destino_requerida' => 'El traslado necesita una base de destino.',
        'ajuste_sentido_requerido' => 'El ajuste necesita indicar si suma o resta stock.',
        'compra_costo_unitario_requerido' => 'La compra necesita el costo unitario.',
        'motivo_requerido' => 'Este tipo de movimiento necesita un motivo.',
    ],

];

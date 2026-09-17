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
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Código o descripción…',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'filtro_vacio' => 'Ningún repuesto coincide con esta búsqueda.',
        'vacio_titulo' => 'Todavía no hay repuestos registrados',
        'vacio_detalle' => 'El catálogo de repuestos se compone con cada pieza del equipamiento de Agrocom. Se registra uno nuevo desde el formulario de alta arriba.',
        'col_codigo' => 'Código',
        'col_descripcion' => 'Descripción',
        'col_unidad' => 'Unidad',
        'col_costo' => 'Última compra',
        'sin_costo' => '—',
        'editar' => 'Editar',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmas la baja de este repuesto?',
        'paginacion_aria' => 'Paginación de repuestos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
        'titulo_crear' => 'Nuevo repuesto',
        'titulo_editar' => 'Editar repuesto',
        'subtitulo_form' => 'Código, descripción, unidad y costo de la última compra.',
        'seccion_datos' => 'Datos del repuesto',
        'campos_contador' => ':cantidad campos',
        'campo_codigo' => 'Código',
        'campo_descripcion' => 'Descripción',
        'campo_unidad' => 'Unidad',
        'campo_unidad_placeholder' => 'kg, litros, unidad…',
        'campo_costo' => 'Costo de la última compra',
        'campo_costo_placeholder' => 'Sin compras todavía',
        'estado_form' => 'Sin guardar',
        'creado' => 'Repuesto creado.',
        'actualizado' => 'Repuesto actualizado.',
        'eliminado' => 'Repuesto dado de baja.',
        'volver' => 'Volver a repuestos',
    ],

    // Pantalla de panel "Mantenimiento › Stock" (HU-36, tarea 52): stock
    // agregado por repuesto y base, con alerta de mínimo, y alta de
    // movimientos.
    'stock' => [
        'titulo' => 'Stock por base',
        'subtitulo' => 'Stock agregado por repuesto y base, con alerta al cruzar el mínimo.',
        'nuevo_movimiento' => 'Registrar movimiento',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Código o descripción del repuesto…',
        'filtro_base' => 'Base',
        'filtro_todos' => 'Todas',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'filtro_vacio' => 'Ningún registro coincide con estos filtros.',
        'vacio_titulo' => 'Todavía no hay stock registrado',
        'vacio_detalle' => 'El stock de repuestos se crea con el primer movimiento de compra, salida o ajuste. Se registra uno nuevo desde el botón de arriba.',
        'col_codigo' => 'Repuesto',
        'col_base' => 'Base',
        'col_cantidad' => 'Cantidad',
        'col_minimo' => 'Mínimo',
        'col_alerta' => 'Alerta',
        'alerta_activa' => 'Por debajo del mínimo',
        'sin_alerta' => '—',
        'paginacion_aria' => 'Paginación de stock',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',
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
        'campo_base_destino' => 'Base de destino',
        'campo_base_destino_placeholder' => 'Solo para traslados',
        'campo_cantidad' => 'Cantidad',
        'campo_sentido' => 'Sentido del ajuste',
        'campo_costo_unitario' => 'Costo unitario',
        'campo_motivo' => 'Motivo',
        'campo_motivo_placeholder' => 'Obligatorio en ajuste y traslado',
        'estado_form' => 'Sin guardar',
        'volver' => 'Volver a stock',
    ],

];

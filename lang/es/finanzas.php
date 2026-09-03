<?php

/*
 * Copy de vocabulario del dominio Finanzas. Primer archivo del módulo (HU-28,
 * tarea 40) — mismo criterio que lang/es/personal.php: las cifras nunca se
 * hardcodean en la vista, se formatean acá contra el valor crudo que viaja
 * como dato (ADR 0013).
 */

return [

    // HU-28 (tarea 40): "como piloto o auxiliar, quiero ver mis devengos por
    // período" — tabla de solo lectura, sin alta/edición/baja.
    'devengos' => [
        'titulo' => 'Devengos',
        'subtitulo' => 'Tus devengos por período, con hectáreas, tarifa y monto de cada sesión validada.',
        'filtro_periodo' => 'Período',
        'filtrar' => 'Filtrar',
        'vacio' => 'No hay devengos registrados en este período.',
        'col_fecha' => 'Fecha',
        'col_hectareas' => 'Hectáreas',
        'col_tarifa' => 'Tarifa/ha',
        'col_monto' => 'Monto',
        'monto_valor' => 'Bs :monto',
        'total' => 'Total del período',
    ],

    // HU-29 (tarea 41): "como encargado, quiero registrar anticipos
    // validando el tope, para no adelantar más de lo devengado" — ABM
    // acotado, sin edición: alta, listado y baja.
    'anticipos' => [
        'titulo' => 'Anticipos',
        'subtitulo' => 'Anticipos de personal, validados contra el tope del mes (3.000 Bs o el 70% de lo devengado).',
        'nueva' => 'Nuevo anticipo',
        'creado' => 'Anticipo registrado correctamente.',
        'eliminado' => 'Anticipo dado de baja correctamente.',
        'filtro_persona' => 'Persona',
        'filtro_persona_placeholder' => 'Todas',
        'filtro_periodo' => 'Período',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio' => 'No hay anticipos registrados.',
        'filtro_vacio' => 'No hay anticipos que coincidan con el filtro.',
        'col_persona' => 'Persona',
        'col_fecha' => 'Fecha',
        'col_monto' => 'Monto',
        'col_motivo' => 'Motivo',
        'monto_valor' => 'Bs :monto',
        'sin_motivo' => 'Sin motivo registrado',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Dar de baja este anticipo? Esta acción no se puede deshacer.',
        'paginacion_aria' => 'Paginación de anticipos',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        'titulo_crear' => 'Nuevo anticipo',
        'subtitulo_form' => 'Registrá un anticipo validado contra el disponible del mes de la persona.',
        'seccion_datos' => 'Datos del anticipo',
        'campos_contador' => ':cantidad campos',
        'campo_persona' => 'Persona',
        'campo_persona_placeholder' => 'Seleccioná una persona',
        'campo_monto' => 'Monto (Bs)',
        'campo_fecha' => 'Fecha',
        'campo_motivo' => 'Motivo (opcional)',
        'estado_form' => 'Sin guardar',
        'error_persona_requerida' => 'Elegí la persona que recibe el anticipo.',
        'error_persona_invalida' => 'La persona seleccionada no es válida.',

        'consulta_titulo' => 'Consultar disponible',
        'consulta_ayuda' => 'Elegí una persona para ver cuánto puede adelantar todavía este mes, antes de completar el formulario.',
        'consulta_boton' => 'Consultar',
        'consulta_resultado' => 'Disponible para :persona este mes: Bs :monto',
    ],

];

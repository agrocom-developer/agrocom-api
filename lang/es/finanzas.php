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

];

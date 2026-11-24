<?php

/*
|--------------------------------------------------------------------------
| Solicitudes de servicio del marketplace (ADR 0027)
|--------------------------------------------------------------------------
|
| Parámetros de la tarifa (política de comisión, «Tarifa al cliente»): se fija
| por hectárea según el tipo de aplicación, con recargos explícitos y sin
| tarifa dinámica por demanda. Se congela al publicar la solicitud. Los
| valores de aquí son el respaldo; no hay secretos. Importes en la moneda de la
| operación, siempre como cadena decimal exacta (invariante 6 de CLAUDE.md).
|
*/

return [
    /* Tarifa base por hectárea, por tipo de aplicación. `defecto` cubre los que no figuran. */
    'tarifa_hectarea' => [
        'defecto' => env('SOLICITUDES_TARIFA_HECTAREA', '120.00'),
        'herbicida' => env('SOLICITUDES_TARIFA_HERBICIDA', '120.00'),
        'insecticida' => env('SOLICITUDES_TARIFA_INSECTICIDA', '130.00'),
        'fungicida' => env('SOLICITUDES_TARIFA_FUNGICIDA', '135.00'),
        'fertilizante_foliar' => env('SOLICITUDES_TARIFA_FERTILIZANTE_FOLIAR', '110.00'),
        'desecante' => env('SOLICITUDES_TARIFA_DESECANTE', '125.00'),
    ],

    /* Recargo por urgencia, en porcentaje sobre el subtotal base. */
    'recargo_urgente_pct' => env('SOLICITUDES_RECARGO_URGENTE_PCT', '20'),

    /* Recargo por terreno de difícil acceso, en porcentaje sobre el subtotal base. */
    'recargo_acceso_dificil_pct' => env('SOLICITUDES_RECARGO_ACCESO_DIFICIL_PCT', '10'),

    /*
     * Porcentaje mínimo de las hectáreas pedidas que deben declarar los
     * trabajos cerrados para dar la solicitud por ejecutada. La cobertura exacta
     * rara vez coincide con lo pedido; el acta de conformidad fija lo que se
     * factura (política de comisión).
     */
    'cobertura_minima_pct' => env('SOLICITUDES_COBERTURA_MINIMA_PCT', '95'),

    /* Recargo por hectárea cuando el proveedor aporta el producto. */
    'recargo_producto_proveedor_hectarea' => env('SOLICITUDES_RECARGO_PRODUCTO_HECTAREA', '60.00'),

    /*
     * Horas que tiene el cliente, desde que la solicitud queda `ejecutada`, para
     * dar la conformidad o abrir una disputa. Vencido el plazo sin observación,
     * la solicitud pasa a `completada` por conformidad tácita (especificación
     * §5.1 y política de disputas).
     */
    'conformidad_horas' => (int) env('SOLICITUDES_CONFORMIDAD_HORAS', 72),

    /*
     * Tolerancia, en porcentaje, de las hectáreas confirmadas sobre las
     * solicitadas: lo que excede ese tope no se factura (política de comisión).
     */
    'tolerancia_hectareas_pct' => env('SOLICITUDES_TOLERANCIA_HECTAREAS_PCT', '5'),

    /*
     * Comisión que Agrocom devenga por cada servicio completado, en porcentaje
     * sobre el monto final (política de comisión, ADR 0034). Se calcula y se
     * congela en la solicitud; no se cobra dentro de la aplicación en la versión 1.
     * El propio no paga (el ingreso es de Agrocom); el dron vendido por Agrocom
     * tiene una tasa preferencial del grupo.
     */
    'comision' => [
        'propio' => env('SOLICITUDES_COMISION_PROPIO_PCT', '0'),
        'independiente' => env('ASIGNACION_COMISION_EXTERNO_PCT', '15'),
        'tercero' => env('ASIGNACION_COMISION_EXTERNO_PCT', '15'),
        'dron_vendido' => env('SOLICITUDES_COMISION_DRON_VENDIDO_PCT', '10'),
    ],

    /*
     * Política de calificación aplicada al cliente: promedio mínimo de lo que le
     * califican los proveedores, mínimo de calificaciones para evaluarlo y días
     * que pasan entre el aviso y la limitación de la publicación.
     */
    'calificacion_cliente' => [
        'umbral' => env('SOLICITUDES_CLIENTE_CALIFICACION_UMBRAL', '3.5'),
        'minimo_conteo' => (int) env('SOLICITUDES_CLIENTE_CALIFICACION_MINIMO', 10),
        'plazo_limitacion_dias' => (int) env('SOLICITUDES_CLIENTE_PLAZO_LIMITACION_DIAS', 30),
    ],
];

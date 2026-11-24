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
];

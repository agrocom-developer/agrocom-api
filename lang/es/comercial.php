<?php

/*
 * Copy de vocabulario del dominio Comercial (clientes, contratos, lotes).
 * Mismo criterio que lang/es/operaciones.php: las claves de estado nunca se
 * hardcodean en la vista, se resuelven acá contra el valor crudo que
 * viaja como dato (ADR 0013).
 */

return [

    'contrato' => [
        'estado' => [
            'borrador' => 'Borrador',
            'vigente' => 'Vigente',
            'finalizado' => 'Finalizado',
            'cancelado' => 'Cancelado',
        ],
    ],

];

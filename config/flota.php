<?php

/*
|--------------------------------------------------------------------------
| Flota de drones (`docs/especificacion/negociacion-contrato.md` §9, ADR 0042)
|--------------------------------------------------------------------------
|
| Un dron no se aprueba sin las fotos que el administrador pide y revisa.
| El valor de aquí es el respaldo; no hay secretos.
|
*/

return [
    /** Tipos de foto que el dron debe tener `aprobada` antes de aprobarse (separados por coma en el entorno). */
    'fotos_obligatorias' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FLOTA_FOTOS_OBLIGATORIAS', 'frontal,lateral,placa_serie')),
    ))),
];

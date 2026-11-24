<?php

/*
|--------------------------------------------------------------------------
| Flota: parámetros del dron (especificación del marketplace §11)
|--------------------------------------------------------------------------
|
| `fotos_obligatorias`: los tipos de foto que el administrador debe haber
| aprobado antes de aprobar un dron (`negociacion-contrato.md` §9). El proveedor
| las sube desde la app; el administrador las revisa una por una en
| `panel/drones-flota`. Un dron sin ellas no se aprueba y no entra a un equipo
| apto.
*/

return [
    'fotos_obligatorias' => ['frontal', 'lateral', 'placa_serie'],
];

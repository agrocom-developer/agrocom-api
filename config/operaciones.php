<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tolerancia de solape entre sesiones
    |--------------------------------------------------------------------------
    |
    | Espec §5: "la suma de sesiones no puede superar las hectáreas del lote
    | más una tolerancia configurable por solape. Si la excede, el trabajo
    | queda `observado`" — y espec línea 454: "parámetro configurable, a
    | definir con la experiencia de campo".
    |
    | El valor real todavía NO está decidido por el negocio (HU-07, tarea 20,
    | runs/20.md): el default de acá es un placeholder deliberadamente
    | conservador (cero tolerancia, cualquier exceso dispara `observado`), no
    | una cifra de campo. Cuando el negocio defina el número real, se fija en
    | `.env` (`OPERACIONES_TOLERANCIA_SOLAPE_HECTAREAS`) sin tocar código.
    |
    | Hectáreas, DECIMAL como string (invariante 6 de CLAUDE.md) — nunca float.
    |
    */

    'tolerancia_solape_hectareas' => env('OPERACIONES_TOLERANCIA_SOLAPE_HECTAREAS', '0.00'),

];

<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use RuntimeException;

/**
 * Guarda de `Aplicacion/RegistrarPausa` (HU-44, tarea 58): una pausa no puede
 * terminar antes de (ni en el mismo instante que) empezar — mismo criterio de
 * exactitud que sostiene `duracion_minutos` (invariante 6 de CLAUDE.md
 * extendida a lo que se va a sumar y mostrar, no solo a dinero/hectáreas).
 * Replica en el caso de uso el `CHECK (fin > inicio)` de la migración, que
 * SQLite no puede probar en la suite local (ver skill `modelo-datos`).
 */
final class PausaFinAnteriorAInicio extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El fin de la pausa debe ser posterior a su inicio.');
    }
}

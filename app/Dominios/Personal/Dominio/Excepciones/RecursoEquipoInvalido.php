<?php

namespace App\Dominios\Personal\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use RuntimeException;

/**
 * `per_equipo_recursos.recurso_id` no existe (o existe pero no está activo)
 * en la tabla que le corresponde según `recurso_tipo` (tarea 72, HU-49, ADR
 * 0015 punto 3). Es la guarda que reemplaza a la FK que esa columna no puede
 * tener — ver el docblock de la migración
 * `2026_09_08_500003_create_per_equipo_recursos_table.php` para el porqué
 * completo. La lanza `AsignarRecursoEquipo` ANTES de guardar; el caso de uso
 * que persiste la traduce a un error de validación legible.
 */
final class RecursoEquipoInvalido extends RuntimeException
{
    public static function porTipoYId(RecursoTipoEquipo $tipo, int $recursoId): self
    {
        return new self(Texto::de('personal.errores.recurso_equipo_invalido', [
            'tipo' => $tipo->value,
            'recurso_id' => $recursoId,
        ]));
    }
}

<?php

namespace App\Dominios\Compartido\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\Excepciones\BorradoFisicoNoPermitido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Clase base de todo modelo Eloquent de dominio (ADR 0003: "Eloquent ES el
 * modelo de dominio dentro del módulo dueño"). Empaqueta las obligaciones de
 * plataforma del ADR 0007 para que ningún modelo pueda adoptarlas a medias:
 *
 * - Soft delete por defecto (`SoftDeletes`, columna `deleted_at`).
 * - Borrado físico bloqueado: `forceDelete()` lanza {@see BorradoFisicoNoPermitido}.
 *   Se eligió clase base y no trait suelto porque el bundle es indivisible
 *   (un modelo no puede tomar SoftDeletes y "olvidar" la guarda) y porque un
 *   test de arquitectura puede exigir "todo modelo extiende ModeloDominio".
 * - Autoría por fila (`created_by` / `updated_by`) vía {@see RegistraAutoria}.
 *
 * Cobertura de la guarda: `forceDelete()`, `forceDeleteQuietly()` y
 * `forceDestroy()` terminan en el `forceDelete()` de la instancia, así que
 * el override los bloquea a todos. Un `->forceDelete()` sobre el query
 * builder (sin hidratar modelos) queda fuera del alcance de Eloquent —
 * equivale a SQL crudo de mutación, prohibido por el ADR 0012.
 *
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
abstract class ModeloDominio extends Model
{
    use RegistraAutoria;
    use SoftDeletes;

    /**
     * Bloqueado por el ADR 0007: los modelos de dominio no admiten DELETE
     * físico. Usar `delete()` (borrado lógico) en su lugar.
     *
     * @throws BorradoFisicoNoPermitido siempre
     */
    public function forceDelete(): never
    {
        throw BorradoFisicoNoPermitido::paraModelo(static::class);
    }
}

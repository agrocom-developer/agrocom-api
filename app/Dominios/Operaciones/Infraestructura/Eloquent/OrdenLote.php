<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote (y hectáreas solicitadas de él) dentro de una Orden de Aplicación
 * (HU-92, tarea 107, espec §4.3 ampliada): una orden puede cubrir varios
 * lotes de la propiedad — ver docblock de la migración
 * `create_ope_orden_lotes_table`.
 *
 * `lote_id` referencia `com_lotes` SOLO por ID (ADR 0003, regla 3): sin
 * relación Eloquent hacia Comercial. `orden_id` sí es relación Eloquent
 * (`orden()`, abajo): ambas tablas son del mismo módulo Operaciones.
 *
 * `RegistraBitacora` (invariante 9), mismo criterio que `OrdenAplicacion`.
 *
 * @property int $id
 * @property int $orden_id
 * @property int $lote_id
 * @property string $hectareas_solicitadas
 */
class OrdenLote extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_orden_lotes';

    /** @var list<string> */
    protected $fillable = [
        'orden_id',
        'lote_id',
        'hectareas_solicitadas',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hectareas_solicitadas' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<OrdenAplicacion, $this> */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenAplicacion::class, 'orden_id');
    }
}

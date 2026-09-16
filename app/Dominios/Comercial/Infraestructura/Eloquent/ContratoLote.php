<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote concreto de la propiedad que cubre un contrato (pedido del dueño: el
 * contrato elige una propiedad del cliente y uno o más lotes de ella, para
 * poder validar más adelante que no se sobre-comprometan los lotes de una
 * propiedad dentro de la misma campaña). Sin hectáreas propias por fila: el
 * agregado sigue siendo `com_contratos.hectareas_contratadas` — a diferencia
 * de {@see \App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenLote},
 * que sí desglosa `hectareas_solicitadas` por ser una orden concreta de
 * vuelo, no un pacto comercial.
 *
 * Modelo Eloquent propio, navegado vía `HasMany` desde {@see
 * Contrato::lotes()} — NO `belongsToMany()->using()` hacia `Lote`: mismo
 * motivo documentado en {@see
 * \App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole} y en {@see
 * \App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenLote} (el pivote
 * lleva soft delete + auditoría propia, y los stubs de tipos de
 * `BelongsToMany::using()` exigen que el pivote sea subtipo de `Pivot`, lo
 * que rompería esa obligación). Sacar un lote del contrato es un soft
 * delete de esta fila (`->delete()`, incluso en bloque vía la query del
 * `HasMany`, que respeta `SoftDeletes`), nunca un `detach()`/`sync()` de
 * Laravel — esos métodos no existen en `HasMany` a propósito: con
 * `belongsToMany()` habrían hecho `DELETE` físico directo sobre la tabla
 * pivote, sin pasar por `ModeloDominio::forceDelete()` (invariante 8).
 *
 * `lote()` sí es relación Eloquent (a diferencia de `OrdenLote::lote_id`,
 * que queda en entero opaco por ser Operaciones cruzando hacia Comercial,
 * ADR 0003 regla 3): acá `Contrato` y `Lote` son del mismo módulo, así que
 * no hay frontera que cruzar.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md), mismo criterio que
 * {@see ContratoVentana}: quién agregó o quitó un lote del contrato y
 * cuándo es auditable.
 *
 * @property int $id
 * @property int $contrato_id
 * @property int $lote_id
 */
class ContratoLote extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_contrato_lotes';

    /** @var list<string> */
    protected $fillable = [
        'contrato_id',
        'lote_id',
    ];

    /** @return BelongsTo<Contrato, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    /** @return BelongsTo<Lote, $this> */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }
}

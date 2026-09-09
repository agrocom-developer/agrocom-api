<?php

namespace App\Dominios\Inventario\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila de AGREGADO de stock por `(repuesto_id, base_id)` (HU-36, tarea 52).
 * Ver docblock de
 * `database/migrations/2026_09_03_300002_create_inv_stock_table.php` para el
 * porqué de extender `ModeloDominio` pese a ser una fila derivada.
 *
 * Sin `RegistraBitacora`: la invariante 9 de CLAUDE.md pide bitácora en toda
 * MUTACIÓN DE NEGOCIO — acá la mutación de negocio es el movimiento
 * (`inv_movimientos`, que sí la lleva, con tipo/motivo/autor), no el
 * recálculo mecánico del agregado que dispara. Sumarle bitácora a esta fila
 * duplicaría cada asiento sin agregar ningún "por qué" que `inv_movimientos`
 * no tenga ya.
 *
 * `base_id` es un entero plano — FK hacia otro módulo (`Personal`),
 * prohibido tener `belongsTo` cross-módulo por ADR 0003 regla 3.
 * `repuesto_id` sí es del mismo módulo (`Inventario`): la relación
 * `repuesto()` existe para que `ListarStock` traiga código/descripción sin
 * N+1 (`with('repuesto')`), la única razón por la que esta fila necesita
 * lazy-load — `RegistrarMovimientoStock` sigue resolviendo todo por id.
 *
 * @property int $id
 * @property int $repuesto_id
 * @property int $base_id
 * @property string $cantidad
 * @property string $stock_minimo
 * @property bool $alerta atributo NO persistido, calculado y asignado por
 *                        `ListarStock` — ausente fuera de ese caso de uso.
 */
class Stock extends ModeloDominio
{
    protected $table = 'inv_stock';

    /** @var list<string> */
    protected $fillable = [
        'repuesto_id',
        'base_id',
        'cantidad',
        'stock_minimo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Repuesto, $this> */
    public function repuesto(): BelongsTo
    {
        return $this->belongsTo(Repuesto::class);
    }
}

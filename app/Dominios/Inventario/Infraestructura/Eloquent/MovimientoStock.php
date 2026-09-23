<?php

namespace App\Dominios\Inventario\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Asiento de un movimiento de stock (HU-36, tarea 52): `compra`, `salida`,
 * `ajuste` o `traslado`. Ver docblock de
 * `database/migrations/2026_09_03_300003_create_inv_movimientos_table.php`
 * para el detalle de columnas, el porqué de `sentido` como campo aparte de
 * `cantidad`, y el porqué de extender `ModeloDominio` pese a ser
 * conceptualmente un asiento contable.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): cada movimiento es la
 * mutación de negocio auditable en sí misma — quién, cuándo, qué tipo, qué
 * motivo.
 *
 * `base_id`/`base_destino_id` son enteros planos — FK hacia otro módulo
 * (`Personal`), prohibido tener `belongsTo` cross-módulo por ADR 0003 regla
 * 3. `repuesto_id` sí es del mismo módulo: la relación `repuesto()` existe
 * para que `ListarMovimientosStock` traiga código/descripción sin N+1
 * (mismo criterio que `Stock::repuesto()`).
 *
 * @property int $id
 * @property int $repuesto_id
 * @property int $base_id
 * @property int|null $base_destino_id
 * @property string $tipo
 * @property string $cantidad
 * @property string|null $sentido
 * @property string|null $costo_unitario
 * @property string|null $motivo
 * @property int|null $orden_mantenimiento_id
 * @property Carbon $instante atributo NO persistido: `created_at` ya
 *                            convertido a la zona horaria de quien mira. Lo
 *                            calcula y asigna `ListarMovimientosStock` —
 *                            ausente fuera de ese caso de uso.
 */
class MovimientoStock extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'inv_movimientos';

    /** @var list<string> */
    protected $fillable = [
        'repuesto_id',
        'base_id',
        'base_destino_id',
        'tipo',
        'cantidad',
        'sentido',
        'costo_unitario',
        'motivo',
        'orden_mantenimiento_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Repuesto, $this> */
    public function repuesto(): BelongsTo
    {
        return $this->belongsTo(Repuesto::class);
    }
}

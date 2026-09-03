<?php

namespace App\Dominios\Inventario\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

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
}

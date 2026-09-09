<?php

namespace App\Dominios\Inventario\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Repuesto del catálogo (HU-36, tarea 52): código, descripción, unidad y el
 * costo de su última compra. Ver docblock de
 * `database/migrations/2026_09_03_300001_create_inv_repuestos_table.php`
 * para el detalle de columnas, constraints y el recorte de "costo promedio
 * ponderado" a "último costo conocido".
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): el alta, edición y baja de
 * un repuesto desde el panel es una mutación de negocio con autor y momento
 * auditables, mismo criterio que `Bateria`/`Vehiculo`.
 *
 * @property int $id
 * @property string $codigo
 * @property string $descripcion
 * @property string $unidad
 * @property string|null $costo_unitario
 * @property bool $alerta atributo NO persistido, calculado y asignado por
 *                        `ListarRepuestos` — ausente fuera de ese caso de uso.
 */
class Repuesto extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'inv_repuestos';

    /** @var list<string> */
    protected $fillable = [
        'codigo',
        'descripcion',
        'unidad',
        'costo_unitario',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'costo_unitario' => 'decimal:2',
        ];
    }
}

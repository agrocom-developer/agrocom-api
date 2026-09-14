<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Catálogo mínimo de dron (HU-07, tarea 20; ver docblock de
 * `database/migrations/2026_09_01_100013_create_ope_drones_table.php` y
 * runs/20.md). Sin ciclo de vida propio (sin uso acumulado, sin historial de
 * mantenimiento) — cuando exista el módulo `Mantenimiento`/`Inventario`
 * (ADR 0011 punto 3), esa es la tabla que absorbe esta.
 *
 * `modelo`/`capacidad_l` (HU-27, tarea 36): ver docblock de
 * `2026_09_02_100004_add_modelo_capacidad_a_ope_drones_table.php`.
 * `capacidad_kg` (HU-81, tarea 96): ver docblock de
 * `2026_09_14_100010_add_capacidad_kg_a_ope_drones_table.php`.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md, HU-27): el alta, edición y
 * baja de un dron desde el panel es una mutación de negocio con autor y
 * momento auditables, mismo criterio que `Cliente` en Comercial.
 *
 * @property int $id
 * @property string $identificador
 * @property string|null $modelo
 * @property string|null $capacidad_l
 * @property string|null $capacidad_kg
 */
class Dron extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_drones';

    /** @var list<string> */
    protected $fillable = [
        'identificador',
        'modelo',
        'capacidad_l',
        'capacidad_kg',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capacidad_l' => 'decimal:2',
            'capacidad_kg' => 'decimal:2',
        ];
    }
}

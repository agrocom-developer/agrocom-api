<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Ficha de inventario de un dron (HU-82, tarea 97): serie, chasis, versión
 * de software, región, serie del control y accesorios. Ver docblock de
 * `database/migrations/2026_09_14_100011_create_man_drones_table.php` para
 * el detalle de columnas y el porqué de la correlación sin FK contra
 * `ope_drones`.
 *
 * Nombrado `FichaDron`, NO `Dron`: ya existe
 * `App\Dominios\Operaciones\Infraestructura\Eloquent\Dron`, el catálogo
 * mínimo operativo de otro módulo — son clases sin relación entre sí
 * (ADR 0003, sin `belongsTo` cross-módulo).
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): el alta, edición y baja de
 * una ficha desde el panel es una mutación de negocio con autor y momento
 * auditables, mismo criterio que `Bateria`/`Vehiculo`.
 *
 * @property int $id
 * @property string $identificador_dron
 * @property string|null $numero_serie
 * @property string|null $chasis
 * @property string|null $version_software
 * @property string|null $region
 * @property string|null $serie_control
 * @property bool $tiene_cargador_control
 * @property bool $tiene_modem
 * @property bool $tiene_maletin
 */
class FichaDron extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'man_drones';

    /** @var list<string> */
    protected $fillable = [
        'identificador_dron',
        'numero_serie',
        'chasis',
        'version_software',
        'region',
        'serie_control',
        'tiene_cargador_control',
        'tiene_modem',
        'tiene_maletin',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tiene_cargador_control' => 'boolean',
            'tiene_modem' => 'boolean',
            'tiene_maletin' => 'boolean',
        ];
    }
}

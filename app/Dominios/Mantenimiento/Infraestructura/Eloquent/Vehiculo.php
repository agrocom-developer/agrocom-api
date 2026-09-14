<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Vehículo de la flota (HU-40, tarea 50): identificador (placa o código
 * interno), asignación a base y estado descriptivo. Ver docblock de
 * `database/migrations/2026_09_03_200001_create_man_vehiculos_table.php`
 * para el detalle de columnas y constraints.
 *
 * `base_id` es un entero plano — sin `belongsTo(PerBase::class)`: es una FK
 * hacia otro módulo (`Personal`), prohibido por ADR 0003 regla 3, mismo
 * criterio que `Gasto::base_id` en `Finanzas`.
 *
 * `estado` no es una máquina de estados de negocio: no hay guardas ni
 * transiciones gobernadas por una regla del dominio (CLAUDE.md invariante 7
 * no aplica a este campo, ver docblock de la migración).
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): el alta, edición y baja de
 * un vehículo desde el panel es una mutación de negocio con autor y momento
 * auditables, mismo criterio que `Dron` en Operaciones.
 *
 * `marca`/`modelo`/`anio`/`combustible`/`es_4x4`/`kilometraje_inicial`/
 * `kilometraje_actual` (HU-84, tarea 99): ver docblock de
 * `database/migrations/2026_09_14_100013_add_ficha_completa_y_pausa_a_man_vehiculos_table.php`.
 *
 * @property int $id
 * @property string $identificador
 * @property string|null $marca
 * @property string|null $modelo
 * @property int|null $anio
 * @property string|null $combustible
 * @property bool $es_4x4
 * @property string|null $kilometraje_inicial
 * @property string|null $kilometraje_actual
 * @property int|null $base_id
 * @property string $estado
 */
class Vehiculo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'man_vehiculos';

    /** @var list<string> */
    protected $fillable = [
        'identificador',
        'marca',
        'modelo',
        'anio',
        'combustible',
        'es_4x4',
        'kilometraje_inicial',
        'kilometraje_actual',
        'base_id',
        'estado',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'es_4x4' => 'boolean',
            'kilometraje_inicial' => 'decimal:2',
            'kilometraje_actual' => 'decimal:2',
        ];
    }
}

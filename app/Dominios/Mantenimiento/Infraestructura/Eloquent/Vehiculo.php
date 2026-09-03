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
 * @property int $id
 * @property string $identificador
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
        'base_id',
        'estado',
    ];
}

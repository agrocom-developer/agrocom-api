<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Generador de catálogo (tarea 72, HU-49, ADR 0015 punto 3): identificador,
 * modelo, asignación a base, estado descriptivo y horas de uso. Tabla de
 * catálogo mínima cuyo único consumidor es la asignación de equipamiento a
 * un equipo de trabajo — ver docblock de
 * `database/migrations/2026_09_08_400001_create_man_generadores_table.php`
 * para el detalle de columnas y constraints.
 *
 * `base_id` es un entero plano — sin `belongsTo(PerBase::class)`: es una FK
 * hacia otro módulo (`Personal`), prohibido por ADR 0003 regla 3, mismo
 * criterio que `Vehiculo::base_id`.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): el alta, edición y baja de
 * un generador desde el panel es una mutación de negocio con autor y momento
 * auditables, mismo criterio que `Vehiculo`/`Bateria`.
 *
 * @property int $id
 * @property string $identificador
 * @property string|null $modelo
 * @property int|null $base_id
 * @property string $estado
 * @property string|null $horas_uso
 */
class Generador extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'man_generadores';

    /** @var list<string> */
    protected $fillable = [
        'identificador',
        'modelo',
        'base_id',
        'estado',
        'horas_uso',
    ];
}

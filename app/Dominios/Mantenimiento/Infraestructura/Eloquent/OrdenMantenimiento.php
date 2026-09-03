<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;

/**
 * Orden de mantenimiento de un equipo (HU-37, tarea 53). Ver docblock de
 * `database/migrations/2026_09_03_200003_create_man_ordenes_mantenimiento_table.php`
 * para el detalle de columnas y constraints.
 *
 * `equipo_id` es un entero plano — sin `belongsTo`: apunta a `ope_drones.id`
 * o `man_vehiculos.id` según `equipo_tipo`, dos módulos distintos, sin FK
 * real posible con una sola columna (ADR 0003 regla 3).
 *
 * `gasto_id` también es un entero plano — apunta a `fin_gastos.id` con FK
 * real en la base, pero sin `belongsTo`: `Mantenimiento` no lee el modelo
 * `Gasto` de `Finanzas` directamente, solo guarda su id (invariante 5 de
 * ADR 0003).
 *
 * `estado` SÍ es una máquina de estados de negocio (a diferencia de
 * `Vehiculo::estado`/`Bateria::estado`): solo
 * `Aplicacion/MaquinaEstados/MaquinaEstadosOrdenMantenimiento` puede
 * mutarlo (invariante 7 de CLAUDE.md).
 *
 * `RegistraBitacora` (invariante 9): alta, edición y cierre son mutaciones
 * de negocio con autor y momento auditables.
 *
 * @property int $id
 * @property string $equipo_tipo
 * @property int $equipo_id
 * @property string $tipo
 * @property string $descripcion
 * @property EstadoOrdenMantenimiento $estado
 * @property \Illuminate\Support\Carbon $fecha_apertura
 * @property \Illuminate\Support\Carbon|null $fecha_cierre
 * @property int|null $gasto_id
 */
class OrdenMantenimiento extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'man_ordenes_mantenimiento';

    /** @var list<string> */
    protected $fillable = [
        'equipo_tipo',
        'equipo_id',
        'tipo',
        'descripcion',
        'estado',
        'fecha_apertura',
        'fecha_cierre',
        'gasto_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoOrdenMantenimiento::class,
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
        ];
    }
}

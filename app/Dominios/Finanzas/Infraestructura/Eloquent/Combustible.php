<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Carga de combustible de un equipo de trabajo, imputada al recurso concreto
 * que la consumió (espec §4.4, línea 146; HU-35, tarea 49; reescrita por la
 * tarea 73, HU-50): "como encargado, quiero registrar el combustible del
 * generador y de los vehículos, para imputarlo a la campaña" — resuelto
 * hasta el equipo y la unidad exacta: "así sabemos qué vehículo solicitó
 * nuevo combustible". Lo crea únicamente
 * `Finanzas/Aplicacion/CrearCombustible.php`.
 *
 * Entidad independiente de `ope_recargas.litros_combustible_generador`
 * (ver docblock de la migración) — no confundir ambas.
 *
 * `base_id`/`equipo_trabajo_id`/`campania_id` referencian `per_bases`/
 * `per_equipos_trabajo`/`cpn_campanias` solo por FK + entero plano (ADR 0003
 * regla 3) — sin `belongsTo` cross-módulo, mismo criterio que `Gasto`.
 * `equipo_trabajo_id` es OBLIGATORIA (el combustible siempre lo consume una
 * cuadrilla, a diferencia de `Gasto::equipo_trabajo_id`). `campania_id` es
 * nullable — en qué campaña se CONSUMIÓ la carga, atribución de costo nunca
 * de cobro (ADR 0015 punto 6); vacío = consumo interno.
 *
 * `recurso_tipo` (`dron`/`vehiculo`/`generador`) + `recurso_id` reemplazan a
 * la columna genérica que traía la tarea 49: polimórfico SIN FK, mismo
 * criterio que `Personal\Infraestructura\Eloquent\EquipoRecurso` —
 * `recurso_id` apunta según el tipo a `ope_drones`/`man_vehiculos`/
 * `man_generadores`, de otros módulos. `Aplicacion/CrearCombustible`
 * verifica que el recurso estuviera asignado al equipo elegido en la fecha
 * de la carga antes de guardar.
 *
 * Editable (tarea 134, `Aplicacion/ActualizarCombustible`) — a diferencia de
 * `Gasto`, sin `rendicion_id` que la bloquee, siempre se corrige.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): es dinero, mismo criterio
 * que `Gasto`/`Anticipo`/`DevengoPersonal`.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property int $base_id
 * @property int $equipo_trabajo_id
 * @property int|null $campania_id
 * @property string $recurso_tipo
 * @property int $recurso_id
 * @property string $litros
 * @property string $monto
 * @property string|null $descripcion
 */
class Combustible extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'fin_combustibles';

    /** @var list<string> */
    protected $fillable = [
        'fecha',
        'base_id',
        'equipo_trabajo_id',
        'campania_id',
        'recurso_tipo',
        'recurso_id',
        'litros',
        'monto',
        'descripcion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'litros' => 'decimal:2',
            'monto' => 'decimal:2',
        ];
    }
}

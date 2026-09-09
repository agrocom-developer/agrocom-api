<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Carbon\CarbonImmutable;

/**
 * Estadía del equipo de trabajo en una hacienda (espec §2.1, HU-51, tarea
 * 74). Nace en la app de campo con su `uuid_cliente` (invariante 1 de
 * CLAUDE.md, resuelto por el motor de sync) al llegar el equipo, y se cierra
 * con un segundo evento (`estadia_salida`) que referencia esta fila por su
 * propio `uuid_cliente` — nunca por id de servidor (ver
 * `EscrituraSincronizacionEloquent::cerrarEstadia()`).
 *
 * `equipo_trabajo_id`, `campo_id` y `vehiculo_id` referencian tablas de otros
 * módulos (`Personal`, `Comercial`, `Mantenimiento` respectivamente) solo por
 * FK + entero plano (ADR 0003, regla 3) — sin relaciones Eloquent cruzadas.
 *
 * Sin columna `estado`: `salida === null` significa "en curso" (ver docblock
 * de la migración) — no hay máquina de estados de negocio acá (invariante 7
 * de CLAUDE.md no aplica, mismo criterio que `man_vehiculos.estado`).
 *
 * `cierre_uuid_cliente`: `uuid_cliente` del EVENTO de salida, distinto del de
 * entrada — mismo mecanismo de idempotencia de una mutación sobre fila
 * existente que `Trabajo::$cierre_uuid_cliente`/`Sesion::$cierre_uuid_cliente`.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): igual que `Trabajo`/`Sesion`,
 * la entrada y la salida de un equipo son mutaciones de negocio auditables.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $equipo_trabajo_id
 * @property int $campo_id
 * @property CarbonImmutable $entrada
 * @property CarbonImmutable|null $salida
 * @property int|null $vehiculo_id
 * @property string|null $observacion
 * @property string|null $cierre_uuid_cliente
 */
class EstadiaHacienda extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_estadias_hacienda';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'equipo_trabajo_id',
        'campo_id',
        'entrada',
        'salida',
        'vehiculo_id',
        'observacion',
        'cierre_uuid_cliente',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entrada' => 'immutable_datetime',
            'salida' => 'immutable_datetime',
        ];
    }
}

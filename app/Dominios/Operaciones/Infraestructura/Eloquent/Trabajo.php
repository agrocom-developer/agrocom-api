<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trabajo (espec §4.3, tabla ope_trabajos; TE-05). Nace en la app de campo
 * con su `uuid_cliente` (invariante 1 de CLAUDE.md, resuelto por el motor de
 * sync).
 *
 * `orden_id` y `lote_id` referencian tablas de otro módulo (`Operaciones`
 * dueño de `ope_ordenes_aplicacion`, `Comercial` dueño de `com_lotes`) solo
 * por FK + entero plano (ADR 0003, regla 3) — sin relaciones Eloquent
 * cruzadas. `sesiones()`, en cambio, SÍ es una relación Eloquent normal:
 * `Sesion` vive en el mismo módulo (ADR 0003, regla 1).
 *
 * Las transiciones de `estado` (abierto → cerrado) pasan por
 * `Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo.php` (invariante 7); este
 * modelo no ofrece atajos para mutarlas. `RegistraBitacora`: `estado` es un
 * estado operativo (ADR 0007), misma categoría que `ope_ordenes_aplicacion`.
 *
 * `cierre_uuid_cliente` (HU-05, tarea 13): `uuid_cliente` del EVENTO de
 * cierre, distinto del de apertura — mecanismo de idempotencia de una
 * mutación sobre fila existente, documentado en runs/13.md.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $orden_id
 * @property int $lote_id
 * @property int $nro_aplicacion
 * @property string $hectareas_declaradas
 * @property EstadoTrabajo $estado
 * @property CarbonImmutable $inicio
 * @property CarbonImmutable|null $fin
 * @property string|null $cierre_uuid_cliente
 */
class Trabajo extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_trabajos';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'orden_id',
        'lote_id',
        'nro_aplicacion',
        'hectareas_declaradas',
        'estado',
        'inicio',
        'fin',
        'cierre_uuid_cliente',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'nro_aplicacion' => 'integer',
            'hectareas_declaradas' => 'decimal:2',
            'estado' => EstadoTrabajo::class,
            'inicio' => 'immutable_datetime',
            'fin' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<Sesion, $this> */
    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class, 'trabajo_id');
    }
}

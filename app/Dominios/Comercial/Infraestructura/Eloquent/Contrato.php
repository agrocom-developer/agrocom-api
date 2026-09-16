<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contrato comercial (espec §4.1, tabla com_contratos).
 *
 * HU-91 (tarea 106): el contrato ya no tiene parámetros operativos propios
 * (viento, temperatura, humedad, velocidad, umbral de reporte, altura de
 * vuelo) ni `adelanto_pct`. Esos límites (RF-60) heredan siempre de la Orden
 * o del valor por defecto del sistema — nunca del contrato.
 *
 * Dinero y hectáreas en DECIMAL — el cast `decimal:2` entrega string, nunca
 * float (invariante 6). Las transiciones de `estado` pasan por
 * `Aplicacion/MaquinaEstados/MaquinaEstadosContrato` (invariante 7, HU-23,
 * tarea 34).
 *
 * `RegistraBitacora` (invariante 9, HU-23): el esquema no lo marca como
 * catálogo de rol/permiso (no lo exige el gate automático de
 * `tests/Unit/BitacoraAuditoriaTest.php`), pero es "dinero" y "estados
 * operativos" — las otras dos categorías que el ADR 0007 nombra — y esta es
 * la tarea que instrumenta su máquina de estados, así que se adopta igual
 * (mismo criterio que {@see Cliente}
 * en Finanzas/`DevengoPersonal`).
 *
 * `campania_id` (ADR 0015 punto 1): obligatorio de negocio, pero el tipo PHP
 * queda `int|null` porque el `NOT NULL` de la migración solo se aplica en
 * `pgsql` (`ALTER COLUMN` sin `doctrine/dbal` no es posible en SQLite vía
 * Blueprint) — en los tests (SQLite) la columna admite `null`.
 *
 * @property int $id
 * @property int $cliente_id
 * @property int|null $campania_id
 * @property string $hectareas_contratadas
 * @property int $aplicaciones_previstas
 * @property string $precio_ha
 * @property string $monto_total
 * @property string|null $adelanto_monto
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable|null $fecha_fin
 * @property EstadoContrato $estado
 * @property bool $brinda_alimentacion
 * @property bool $brinda_hospedaje
 * @property bool $brinda_combustible
 * @property string|null $observaciones_logistica
 */
class Contrato extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_contratos';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'campania_id',
        'hectareas_contratadas',
        'aplicaciones_previstas',
        'precio_ha',
        'monto_total',
        'adelanto_monto',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'brinda_alimentacion',
        'brinda_hospedaje',
        'brinda_combustible',
        'observaciones_logistica',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hectareas_contratadas' => 'decimal:2',
            'aplicaciones_previstas' => 'integer',
            'precio_ha' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'adelanto_monto' => 'decimal:2',
            'fecha_inicio' => 'immutable_date',
            'fecha_fin' => 'immutable_date',
            'estado' => EstadoContrato::class,
            'brinda_alimentacion' => 'boolean',
            'brinda_hospedaje' => 'boolean',
            'brinda_combustible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return HasMany<ContratoVentana, $this> */
    public function ventanas(): HasMany
    {
        return $this->hasMany(ContratoVentana::class, 'contrato_id');
    }

    /**
     * Lotes concretos del cliente que cubre este contrato (pedido del dueño:
     * elegir una propiedad y uno o más lotes de ella, no solo un número
     * suelto de hectáreas). Devuelve filas {@see ContratoLote}, NO `Lote`
     * directo: es `HasMany` hacia el pivote propio, no `belongsToMany` hacia
     * `Lote` — ver el docblock de `ContratoLote` para el porqué (soft delete
     * + auditoría propia del pivote, mismo criterio que
     * {@see \App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion::ordenLotes()}).
     * Para llegar al `Lote` real: `$contrato->lotes->pluck('lote')` (eager
     * loading `lotes.lote`), nunca asumir que la colección ya son `Lote`.
     *
     * @return HasMany<ContratoLote, $this>
     */
    public function lotes(): HasMany
    {
        return $this->hasMany(ContratoLote::class, 'contrato_id');
    }
}

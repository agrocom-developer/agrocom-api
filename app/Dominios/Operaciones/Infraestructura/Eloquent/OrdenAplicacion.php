<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Operaciones\Dominio\CausaCancelacionOrden;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orden de aplicación (espec §4.3, tabla ope_ordenes_aplicacion).
 *
 * `contrato_id` y `emitida_por_contacto_id` referencian tablas del módulo
 * Comercial **solo por ID** (ADR 0003, regla 3): acá no hay relaciones
 * Eloquent hacia Comercial — si Operaciones necesita datos del contrato o de
 * un lote, los pide por `Contratos/` del módulo dueño, nunca importando sus
 * modelos.
 *
 * Qué lotes cubre la orden vive en `ope_orden_lotes` (`ordenLotes()`, abajo) —
 * esta tabla ya NO tiene `lote_id` propio (ver docblock de la migración
 * `create_ope_orden_lotes_table`). Desde la reforma 19/9/2026 (ADR 0022) esa
 * lista NO se elige: es una copia, tomada al emitir la orden, de TODOS los
 * lotes del contrato con sus hectáreas completas — la orden es una aplicación
 * completa del contrato, correlativa (`nro_aplicacion` lo calcula el servidor)
 * y de a una por vez. Se guarda como copia (no se deriva del contrato en cada
 * lectura) para que una orden ya emitida conserve el conjunto de lotes sobre
 * el que se emitió, y para que el catálogo de la app de campo siga igual.
 *
 * Los límites climáticos y parámetros de vuelo (RF-60) ya NO viven acá:
 * describen el vuelo que ejecuta cada equipo, no la orden — se movieron a
 * `Trabajo` (`humedad_min_pct`, `viento_max_kmh`, etc., ver migración
 * `2026_09_18_100001_mueve_clima_vuelo_de_ordenes_a_trabajos_table`). Las
 * transiciones de `estado` (emitida → vigente ⇄ pausada → consumida |
 * cancelada) pasan por `Aplicacion/MaquinaEstados/MaquinaEstadosOrden`
 * (invariante 7, HU-25, tarea 38) — este modelo no ofrece atajos para
 * mutarlas. Las columnas de pausa (`motivo_pausa`, `pausada_at`,
 * `reanudada_at`), cierre (`cerrada_at`) y cancelación (`cancelada_at`,
 * `causa_cancelacion`, `motivo_cancelacion`) las escribe esa misma clase, en
 * la misma operación que el cambio de estado.
 *
 * `RegistraBitacora` (invariante 9, HU-25): el esquema no lo marca como
 * catálogo de rol/permiso (no lo exige el gate automático de
 * `tests/Unit/BitacoraAuditoriaTest.php`, que deja `estado` fuera de su
 * regla a propósito), pero el alta, edición y baja de una orden es una
 * mutación de negocio con autor y momento auditables, y el criterio de
 * aceptación de esta HU lo pide explícito — mismo criterio que {@see
 * \App\Dominios\Comercial\Infraestructura\Eloquent\Contrato}.
 *
 * @property int $id
 * @property int $contrato_id
 * @property int $nro_aplicacion
 * @property int $cantidad_equipos_necesarios
 * @property TipoAplicacion $tipo_aplicacion
 * @property int|null $categoria_insumo_id
 * @property string|null $kilos_por_vuelo
 * @property string|null $litros_ha
 * @property string|null $observaciones
 * @property int|null $emitida_por_contacto_id
 * @property CarbonImmutable $fecha_emision
 * @property EstadoOrdenAplicacion $estado
 * @property string|null $motivo_pausa
 * @property CarbonImmutable|null $pausada_at
 * @property CarbonImmutable|null $reanudada_at
 * @property CarbonImmutable|null $cerrada_at
 * @property CarbonImmutable|null $cancelada_at
 * @property CausaCancelacionOrden|null $causa_cancelacion
 * @property string|null $motivo_cancelacion
 */
class OrdenAplicacion extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_ordenes_aplicacion';

    /** @var list<string> */
    protected $fillable = [
        'contrato_id',
        'nro_aplicacion',
        'cantidad_equipos_necesarios',
        'tipo_aplicacion',
        'categoria_insumo_id',
        'kilos_por_vuelo',
        'litros_ha',
        'observaciones',
        'emitida_por_contacto_id',
        'fecha_emision',
        'estado',
        'motivo_pausa',
        'pausada_at',
        'reanudada_at',
        'cerrada_at',
        'cancelada_at',
        'causa_cancelacion',
        'motivo_cancelacion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'nro_aplicacion' => 'integer',
            'cantidad_equipos_necesarios' => 'integer',
            'tipo_aplicacion' => TipoAplicacion::class,
            'kilos_por_vuelo' => 'decimal:2',
            'litros_ha' => 'decimal:2',
            'fecha_emision' => 'immutable_date',
            'estado' => EstadoOrdenAplicacion::class,
            'pausada_at' => 'immutable_datetime',
            'reanudada_at' => 'immutable_datetime',
            'cerrada_at' => 'immutable_datetime',
            'cancelada_at' => 'immutable_datetime',
            'causa_cancelacion' => CausaCancelacionOrden::class,
        ];
    }

    /** @return HasMany<OrdenLote, $this> */
    public function ordenLotes(): HasMany
    {
        return $this->hasMany(OrdenLote::class, 'orden_id');
    }

    /**
     * Categoría de insumo (HU-79, tarea 110): de dónde sale si la orden es
     * sólida (kilos por vuelo) o líquida (litros por hectárea) — la orden no
     * repite ese `tipo_insumo`, ver docblock de la migración.
     *
     * @return BelongsTo<CategoriaInsumo, $this>
     */
    public function categoriaInsumo(): BelongsTo
    {
        return $this->belongsTo(CategoriaInsumo::class, 'categoria_insumo_id');
    }
}

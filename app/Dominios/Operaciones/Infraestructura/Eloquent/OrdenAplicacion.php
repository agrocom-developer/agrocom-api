<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use Carbon\CarbonImmutable;
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
 * Qué lotes cubre la orden (HU-92, tarea 107, ampliación de HU-70: una orden
 * puede cubrir varios lotes de la propiedad) vive en `ope_orden_lotes`
 * (`ordenLotes()`, abajo) — esta tabla ya NO tiene `lote_id` propio: hasta
 * la tarea 107 una orden era 1:1 con un lote y esa columna alcanzaba, pero
 * mantenerla junto con la tabla de detalle habría dejado dos fuentes de
 * verdad sobre el mismo dato (ver docblock de la migración
 * `create_ope_orden_lotes_table`).
 *
 * Los límites por orden en NULL heredan del contrato o del parámetro por
 * defecto del sistema (RF-60). Las transiciones de `estado` (emitida →
 * vigente → consumida | vencida) pasan por
 * `Aplicacion/MaquinaEstados/MaquinaEstadosOrden` (invariante 7, HU-25, tarea
 * 38) — este modelo no ofrece atajos para mutarlas.
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
 * @property string $litros_ha
 * @property string|null $humedad_min_pct
 * @property string|null $viento_max_kmh
 * @property string|null $temperatura_max_c
 * @property string|null $humedad_max_pct
 * @property string|null $velocidad_max_kmh
 * @property string|null $altura_vuelo_m
 * @property string|null $velocidad_vuelo_kmh
 * @property string|null $ancho_pasada_m
 * @property string|null $observaciones
 * @property int|null $emitida_por_contacto_id
 * @property CarbonImmutable $fecha_emision
 * @property EstadoOrdenAplicacion $estado
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
        'litros_ha',
        'humedad_min_pct',
        'viento_max_kmh',
        'temperatura_max_c',
        'humedad_max_pct',
        'velocidad_max_kmh',
        'altura_vuelo_m',
        'velocidad_vuelo_kmh',
        'ancho_pasada_m',
        'observaciones',
        'emitida_por_contacto_id',
        'fecha_emision',
        'estado',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'nro_aplicacion' => 'integer',
            'cantidad_equipos_necesarios' => 'integer',
            'tipo_aplicacion' => TipoAplicacion::class,
            'litros_ha' => 'decimal:2',
            'humedad_min_pct' => 'decimal:2',
            'viento_max_kmh' => 'decimal:2',
            'temperatura_max_c' => 'decimal:2',
            'humedad_max_pct' => 'decimal:2',
            'velocidad_max_kmh' => 'decimal:2',
            'altura_vuelo_m' => 'decimal:2',
            'velocidad_vuelo_kmh' => 'decimal:2',
            'ancho_pasada_m' => 'decimal:2',
            'fecha_emision' => 'immutable_date',
            'estado' => EstadoOrdenAplicacion::class,
        ];
    }

    /** @return HasMany<OrdenLote, $this> */
    public function ordenLotes(): HasMany
    {
        return $this->hasMany(OrdenLote::class, 'orden_id');
    }
}

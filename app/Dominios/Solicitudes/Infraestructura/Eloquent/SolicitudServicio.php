<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\AportaProducto;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\TipoAplicacionServicio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Solicitud de servicio (`sol_solicitudes_servicio`, especificación §4.2, ADR
 * 0027): la unidad de negocio. El `estado` solo lo cambia
 * `MaquinaEstadosSolicitud`; por eso no está en `$fillable`. Importes y
 * hectáreas son decimales exactos (string, invariante 6 de CLAUDE.md).
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $cliente_id
 * @property int $terreno_id
 * @property TipoAplicacionServicio $tipo_aplicacion
 * @property string $hectareas_solicitadas
 * @property string|null $cultivo
 * @property string $producto_indicado
 * @property AportaProducto $aporta_producto
 * @property Carbon $ventana_desde
 * @property Carbon $ventana_hasta
 * @property bool $urgente
 * @property string|null $observaciones
 * @property string|null $tarifa_hectarea
 * @property string|null $monto_estimado
 * @property string|null $monto_final
 * @property string|null $comision_porcentaje
 * @property string|null $comision_monto
 * @property EstadoSolicitud $estado
 * @property int|null $proveedor_id
 * @property int|null $equipo_id
 * @property Carbon|null $publicada_en
 * @property string|null $motivo_cancelacion
 * @property ActorSolicitud|null $cancelada_por
 * @property-read Terreno $terreno
 * @property-read Cliente $cliente
 */
class SolicitudServicio extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sol_solicitudes_servicio';

    /**
     * Sin `estado`, `proveedor_id`, `equipo_id`, `tarifa_*`, `monto_*`,
     * `comision_*`, `motivo_cancelacion` ni `cancelada_por`: los fijan la
     * máquina de estados y los casos de uso, no la entrada del cliente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid_cliente',
        'cliente_id',
        'terreno_id',
        'tipo_aplicacion',
        'hectareas_solicitadas',
        'cultivo',
        'producto_indicado',
        'aporta_producto',
        'ventana_desde',
        'ventana_hasta',
        'urgente',
        'observaciones',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'terreno_id' => 'integer',
            'proveedor_id' => 'integer',
            'equipo_id' => 'integer',
            'tipo_aplicacion' => TipoAplicacionServicio::class,
            'aporta_producto' => AportaProducto::class,
            'estado' => EstadoSolicitud::class,
            'cancelada_por' => ActorSolicitud::class,
            'hectareas_solicitadas' => 'decimal:4',
            'tarifa_hectarea' => 'decimal:4',
            'monto_estimado' => 'decimal:2',
            'monto_final' => 'decimal:2',
            'comision_porcentaje' => 'decimal:2',
            'comision_monto' => 'decimal:2',
            'urgente' => 'boolean',
            'ventana_desde' => 'date',
            'ventana_hasta' => 'date',
            'publicada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Terreno, $this> */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return HasMany<HistorialSolicitud, $this> */
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialSolicitud::class, 'solicitud_id')->orderBy('id');
    }
}

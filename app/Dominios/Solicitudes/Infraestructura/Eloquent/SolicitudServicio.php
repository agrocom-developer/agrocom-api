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
 * @property int|null $contacto_id
 * @property TipoAplicacionServicio $tipo_aplicacion
 * @property string $hectareas_solicitadas
 * @property string|null $cultivo
 * @property string $producto_indicado
 * @property AportaProducto $aporta_producto
 * @property Carbon|null $ventana_desde fecha deseada (opcional; no vence nada)
 * @property Carbon|null $ventana_hasta
 * @property bool $urgente
 * @property string|null $observaciones
 * @property string|null $tarifa_hectarea
 * @property string|null $monto_estimado
 * @property string|null $monto_final
 * @property string|null $precio_ha_propuesto
 * @property string|null $justificacion_precio
 * @property string|null $precio_ha_acordado
 * @property int|null $oferta_adjudicada_id
 * @property string|null $hectareas_ejecutadas
 * @property string $porcentaje_avance
 * @property Carbon|null $adjudicada_en
 * @property Carbon|null $iniciada_en
 * @property Carbon|null $cierre_solicitado_en
 * @property Carbon|null $finalizada_en
 * @property Carbon|null $ultimo_avance_en
 * @property EstadoSolicitud $estado
 * @property int|null $proveedor_id
 * @property int|null $equipo_id
 * @property Carbon|null $publicada_en
 * @property string|null $motivo_cancelacion
 * @property string|null $departamento_codigo Ubicación congelada al publicar (ADR 0045, punto 3).
 * @property string|null $provincia_codigo
 * @property string|null $municipio
 * @property string|null $comunidad
 * @property ActorSolicitud|null $cancelada_por
 * @property-read Terreno $terreno
 * @property-read Cliente $cliente
 * @property-read ClienteContacto|null $contacto
 */
class SolicitudServicio extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sol_solicitudes_servicio';

    /**
     * Sin `estado`, `proveedor_id`, `equipo_id`, `precio_ha_acordado`,
     * `monto_*`, avance ni `motivo_cancelacion`: los fijan la máquina de
     * estados y los casos de uso, no la entrada del cliente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid_cliente',
        'cliente_id',
        'terreno_id',
        'contacto_id',
        'tipo_aplicacion',
        'hectareas_solicitadas',
        'cultivo',
        'producto_indicado',
        'aporta_producto',
        'ventana_desde',
        'ventana_hasta',
        'urgente',
        'observaciones',
        'precio_ha_propuesto',
        'justificacion_precio',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'terreno_id' => 'integer',
            'contacto_id' => 'integer',
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
            'precio_ha_propuesto' => 'decimal:4',
            'precio_ha_acordado' => 'decimal:4',
            'oferta_adjudicada_id' => 'integer',
            'hectareas_ejecutadas' => 'decimal:4',
            'porcentaje_avance' => 'decimal:2',
            'adjudicada_en' => 'datetime',
            'iniciada_en' => 'datetime',
            'cierre_solicitado_en' => 'datetime',
            'finalizada_en' => 'datetime',
            'ultimo_avance_en' => 'datetime',
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

    /** @return BelongsTo<ClienteContacto, $this> Con los dados de baja: la solicitud conserva a la persona elegida. */
    public function contacto(): BelongsTo
    {
        return $this->belongsTo(ClienteContacto::class, 'contacto_id')->withTrashed();
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

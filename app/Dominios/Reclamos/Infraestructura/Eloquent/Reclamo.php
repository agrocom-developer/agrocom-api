<?php

namespace App\Dominios\Reclamos\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use App\Dominios\Reclamos\Dominio\MotivoReclamo;
use App\Dominios\Reclamos\Dominio\ParteDeReclamo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Reclamo de un servicio (`rcl_reclamos`, especificación §4.8): una por solicitud.
 * El `estado`, la `ronda` y las marcas de la mediación solo las cambia
 * `MaquinaEstadosReclamo`; por eso no están en `$fillable`. Importes en decimal
 * exacto (invariante 6 de CLAUDE.md).
 *
 * @property int $id
 * @property int $solicitud_id
 * @property int $cliente_id
 * @property int $cliente_usuario_id
 * @property int|null $proveedor_id
 * @property ParteDeReclamo $abierta_por_rol
 * @property int $abierta_por_usuario_id
 * @property MotivoReclamo $motivo
 * @property string $detalle
 * @property string $monto_en_reclamo
 * @property EstadoReclamo $estado
 * @property int $ronda
 * @property bool $movio_solicitud
 * @property int|null $atendida_por
 * @property Carbon|null $responde_antes_de
 * @property Carbon|null $apelable_hasta
 * @property-read Collection<int, MensajeDeReclamo> $mensajes
 * @property-read Collection<int, ResolucionDeReclamo> $resoluciones
 */
class Reclamo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'rcl_reclamos';

    /** @var list<string> */
    protected $fillable = [
        'solicitud_id',
        'cliente_id',
        'cliente_usuario_id',
        'proveedor_id',
        'abierta_por_rol',
        'abierta_por_usuario_id',
        'motivo',
        'detalle',
        'monto_en_reclamo',
        'responde_antes_de',
        'movio_solicitud',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'solicitud_id' => 'integer',
            'cliente_id' => 'integer',
            'cliente_usuario_id' => 'integer',
            'proveedor_id' => 'integer',
            'abierta_por_usuario_id' => 'integer',
            'abierta_por_rol' => ParteDeReclamo::class,
            'motivo' => MotivoReclamo::class,
            'estado' => EstadoReclamo::class,
            'monto_en_reclamo' => 'decimal:2',
            'ronda' => 'integer',
            'movio_solicitud' => 'boolean',
            'atendida_por' => 'integer',
            'responde_antes_de' => 'datetime',
            'apelable_hasta' => 'datetime',
        ];
    }

    /** @return HasMany<MensajeDeReclamo, $this> */
    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeDeReclamo::class, 'reclamo_id')->orderBy('id');
    }

    /** @return HasMany<ResolucionDeReclamo, $this> */
    public function resoluciones(): HasMany
    {
        return $this->hasMany(ResolucionDeReclamo::class, 'reclamo_id')->orderBy('ronda');
    }

    /** @return HasMany<HistorialDeReclamo, $this> */
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialDeReclamo::class, 'reclamo_id')->orderBy('id');
    }
}

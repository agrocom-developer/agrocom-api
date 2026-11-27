<?php

namespace App\Dominios\Disputas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Disputas\Dominio\MotivoDisputa;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Disputa de un servicio (`dsp_disputas`, especificación §4.8): una por solicitud.
 * El `estado`, la `ronda` y las marcas de la mediación solo las cambia
 * `MaquinaEstadosDisputa`; por eso no están en `$fillable`. Importes en decimal
 * exacto (invariante 6 de CLAUDE.md).
 *
 * @property int $id
 * @property int $solicitud_id
 * @property int $cliente_id
 * @property int $cliente_usuario_id
 * @property int|null $proveedor_id
 * @property ParteDeDisputa $abierta_por_rol
 * @property int $abierta_por_usuario_id
 * @property MotivoDisputa $motivo
 * @property string $detalle
 * @property string $monto_en_disputa
 * @property EstadoDisputa $estado
 * @property int $ronda
 * @property bool $movio_solicitud
 * @property int|null $atendida_por
 * @property Carbon|null $responde_antes_de
 * @property Carbon|null $apelable_hasta
 * @property-read Collection<int, MensajeDeDisputa> $mensajes
 * @property-read Collection<int, ResolucionDeDisputa> $resoluciones
 */
class Disputa extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'dsp_disputas';

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
        'monto_en_disputa',
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
            'abierta_por_rol' => ParteDeDisputa::class,
            'motivo' => MotivoDisputa::class,
            'estado' => EstadoDisputa::class,
            'monto_en_disputa' => 'decimal:2',
            'ronda' => 'integer',
            'movio_solicitud' => 'boolean',
            'atendida_por' => 'integer',
            'responde_antes_de' => 'datetime',
            'apelable_hasta' => 'datetime',
        ];
    }

    /** @return HasMany<MensajeDeDisputa, $this> */
    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeDeDisputa::class, 'disputa_id')->orderBy('id');
    }

    /** @return HasMany<ResolucionDeDisputa, $this> */
    public function resoluciones(): HasMany
    {
        return $this->hasMany(ResolucionDeDisputa::class, 'disputa_id')->orderBy('ronda');
    }

    /** @return HasMany<HistorialDeDisputa, $this> */
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialDeDisputa::class, 'disputa_id')->orderBy('id');
    }
}

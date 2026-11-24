<?php

namespace App\Dominios\Asignacion\Infraestructura\Eloquent;

use App\Dominios\Asignacion\Dominio\EstadoOferta;
use App\Dominios\Asignacion\Dominio\GrupoOferta;
use App\Dominios\Asignacion\Dominio\MotivoRetiro;
use App\Dominios\Asignacion\Dominio\OrigenOferta;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Oferta (`asg_ofertas`, especificación §4.5 y §5.3, ADR 0030): lo que el motor
 * le ofrece a un proveedor sobre una solicitud. El `estado` solo lo cambia
 * `MaquinaEstadosOferta` (invariante 7 de CLAUDE.md), por eso no es `fillable`.
 * `solicitud_id`, `proveedor_id` y `equipo_id` viajan sin relación Eloquent: son
 * de otros módulos.
 *
 * @property int $id
 * @property int $solicitud_id
 * @property int $proveedor_id
 * @property int|null $equipo_id
 * @property int $ciclo
 * @property int $ronda
 * @property GrupoOferta $grupo
 * @property OrigenOferta $origen
 * @property EstadoOferta $estado
 * @property Carbon $ofrecida_en
 * @property Carbon $vence_en
 * @property Carbon|null $respondida_en
 * @property string|null $motivo_rechazo
 * @property MotivoRetiro|null $motivo_retiro
 * @property string|null $pago_estimado_proveedor
 * @property string|null $distancia_km
 * @property string|null $puntuacion
 */
class Oferta extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'asg_ofertas';

    /** @var list<string> */
    protected $fillable = [
        'solicitud_id',
        'proveedor_id',
        'ciclo',
        'ronda',
        'grupo',
        'origen',
        'ofrecida_en',
        'vence_en',
        'pago_estimado_proveedor',
        'distancia_km',
        'puntuacion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'solicitud_id' => 'integer',
            'proveedor_id' => 'integer',
            'equipo_id' => 'integer',
            'ciclo' => 'integer',
            'ronda' => 'integer',
            'grupo' => GrupoOferta::class,
            'origen' => OrigenOferta::class,
            'estado' => EstadoOferta::class,
            'motivo_retiro' => MotivoRetiro::class,
            'ofrecida_en' => 'datetime',
            'vence_en' => 'datetime',
            'respondida_en' => 'datetime',
            'pago_estimado_proveedor' => 'decimal:2',
            'distancia_km' => 'decimal:2',
            'puntuacion' => 'decimal:4',
        ];
    }
}

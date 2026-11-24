<?php

namespace App\Dominios\Asignacion\Infraestructura\Eloquent;

use App\Dominios\Asignacion\Dominio\CausaCancelacion;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Cancelación de un servicio ya aceptado por su proveedor
 * (`asg_cancelaciones_proveedor`, política de cancelación). El `peso` es lo que
 * cuenta en su tasa de cancelación. Solo se inserta.
 *
 * @property int $id
 * @property int $solicitud_id
 * @property int $proveedor_id
 * @property int|null $oferta_id
 * @property int|null $equipo_id
 * @property CausaCancelacion $causa
 * @property string $motivo
 * @property int $peso
 * @property int|null $actor_usuario_id
 * @property Carbon $cancelada_en
 */
class CancelacionDeProveedor extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'asg_cancelaciones_proveedor';

    /** @var list<string> */
    protected $fillable = ['solicitud_id', 'proveedor_id', 'oferta_id', 'equipo_id', 'causa', 'motivo', 'peso', 'actor_usuario_id', 'cancelada_en'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'solicitud_id' => 'integer',
            'proveedor_id' => 'integer',
            'oferta_id' => 'integer',
            'equipo_id' => 'integer',
            'causa' => CausaCancelacion::class,
            'peso' => 'integer',
            'actor_usuario_id' => 'integer',
            'cancelada_en' => 'datetime',
        ];
    }
}

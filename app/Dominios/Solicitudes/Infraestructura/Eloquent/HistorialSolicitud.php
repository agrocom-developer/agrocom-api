<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use Illuminate\Support\Carbon;

/**
 * Fila del historial de una solicitud (`sol_solicitud_historial`): solo se inserta.
 *
 * @property int $id
 * @property int $solicitud_id
 * @property EstadoSolicitud|null $estado_anterior
 * @property EstadoSolicitud $estado_nuevo
 * @property ActorSolicitud $origen
 * @property int|null $actor_usuario_id
 * @property string|null $motivo
 * @property Carbon $created_at
 */
class HistorialSolicitud extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sol_solicitud_historial';

    /** @var list<string> */
    protected $fillable = [
        'solicitud_id',
        'estado_anterior',
        'estado_nuevo',
        'origen',
        'actor_usuario_id',
        'motivo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'solicitud_id' => 'integer',
            'actor_usuario_id' => 'integer',
            'estado_anterior' => EstadoSolicitud::class,
            'estado_nuevo' => EstadoSolicitud::class,
            'origen' => ActorSolicitud::class,
        ];
    }
}

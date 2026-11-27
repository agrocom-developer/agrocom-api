<?php

namespace App\Dominios\Reclamos\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use Illuminate\Support\Carbon;

/**
 * Fila del historial de un reclamo (`rcl_historial`): solo se inserta.
 *
 * @property int $id
 * @property int $reclamo_id
 * @property EstadoReclamo|null $estado_anterior
 * @property EstadoReclamo $estado_nuevo
 * @property int|null $actor_usuario_id
 * @property string $actor_rol
 * @property string|null $motivo
 * @property Carbon $created_at
 */
class HistorialDeReclamo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'rcl_historial';

    /** @var list<string> */
    protected $fillable = ['reclamo_id', 'estado_anterior', 'estado_nuevo', 'actor_usuario_id', 'actor_rol', 'motivo'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reclamo_id' => 'integer',
            'actor_usuario_id' => 'integer',
            'estado_anterior' => EstadoReclamo::class,
            'estado_nuevo' => EstadoReclamo::class,
        ];
    }
}

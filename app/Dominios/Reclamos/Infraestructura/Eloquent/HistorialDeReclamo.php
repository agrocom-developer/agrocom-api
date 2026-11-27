<?php

namespace App\Dominios\Disputas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Disputas\Dominio\EstadoDisputa;
use Illuminate\Support\Carbon;

/**
 * Fila del historial de una disputa (`dsp_historial`): solo se inserta.
 *
 * @property int $id
 * @property int $disputa_id
 * @property EstadoDisputa|null $estado_anterior
 * @property EstadoDisputa $estado_nuevo
 * @property int|null $actor_usuario_id
 * @property string $actor_rol
 * @property string|null $motivo
 * @property Carbon $created_at
 */
class HistorialDeDisputa extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'dsp_historial';

    /** @var list<string> */
    protected $fillable = ['disputa_id', 'estado_anterior', 'estado_nuevo', 'actor_usuario_id', 'actor_rol', 'motivo'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'disputa_id' => 'integer',
            'actor_usuario_id' => 'integer',
            'estado_anterior' => EstadoDisputa::class,
            'estado_nuevo' => EstadoDisputa::class,
        ];
    }
}

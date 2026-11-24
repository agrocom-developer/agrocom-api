<?php

namespace App\Dominios\Flota\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Fila del historial de aprobación de un dron (`flo_historial_estados`): solo se inserta.
 *
 * @property int $id
 * @property int $dron_id
 * @property EstadoAprobacion $estado_anterior
 * @property EstadoAprobacion $estado_nuevo
 * @property AccionAprobacion $accion
 * @property OrigenCambio $origen
 * @property int|null $actor_usuario_id
 * @property string|null $motivo
 * @property Carbon $created_at
 */
class HistorialEstadoDron extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'flo_historial_estados';

    /** @var list<string> */
    protected $fillable = [
        'dron_id',
        'estado_anterior',
        'estado_nuevo',
        'accion',
        'origen',
        'actor_usuario_id',
        'motivo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoAprobacion::class,
            'estado_nuevo' => EstadoAprobacion::class,
            'accion' => AccionAprobacion::class,
            'origen' => OrigenCambio::class,
            'dron_id' => 'integer',
            'actor_usuario_id' => 'integer',
        ];
    }
}

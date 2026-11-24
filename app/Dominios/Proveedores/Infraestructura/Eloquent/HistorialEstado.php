<?php

namespace App\Dominios\Proveedores\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use Illuminate\Support\Carbon;

/**
 * Fila del historial de aprobación (`prv_historial_estados`): solo se inserta.
 *
 * @property int $id
 * @property TipoSujeto $sujeto_tipo
 * @property int $sujeto_id
 * @property EstadoAprobacion $estado_anterior
 * @property EstadoAprobacion $estado_nuevo
 * @property AccionAprobacion $accion
 * @property OrigenCambio $origen
 * @property int|null $actor_usuario_id
 * @property string|null $motivo
 * @property Carbon $created_at
 */
class HistorialEstado extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'prv_historial_estados';

    /** @var list<string> */
    protected $fillable = [
        'sujeto_tipo',
        'sujeto_id',
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
            'sujeto_tipo' => TipoSujeto::class,
            'estado_anterior' => EstadoAprobacion::class,
            'estado_nuevo' => EstadoAprobacion::class,
            'accion' => AccionAprobacion::class,
            'origen' => OrigenCambio::class,
            'sujeto_id' => 'integer',
            'actor_usuario_id' => 'integer',
        ];
    }
}

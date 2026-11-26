<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Fila del historial de aprobación de un cliente (`sol_cliente_historial_estados`): solo se
 * inserta. Es la contraparte, para el cliente, de `prv_historial_estados` de proveedores y
 * pilotos (ADR 0046, punto 11): cada transición con su autor, su motivo y su momento.
 *
 * @property int $id
 * @property int $cliente_id
 * @property EstadoAprobacion|null $estado_anterior
 * @property EstadoAprobacion $estado_nuevo
 * @property AccionAprobacion $accion
 * @property OrigenCambio $origen
 * @property int|null $actor_usuario_id
 * @property string|null $motivo
 * @property Carbon $created_at
 */
class ClienteHistorialEstado extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sol_cliente_historial_estados';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
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
            'cliente_id' => 'integer',
            'actor_usuario_id' => 'integer',
        ];
    }
}

<?php

namespace App\Dominios\Notificaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Un aviso para UNA cuenta (`ntf_notificaciones`, ADR 0025): el módulo reparte
 * una fila por cuenta destinataria cuando ocurre el hecho, y el estado de
 * lectura es de esa cuenta.
 *
 * Ninguna consulta de lectura debe salir de este modelo sin pasar por
 * {@see self::scopeDeUsuario()}: es la garantía de código de que una cuenta
 * nunca ve —ni marca, ni abre— el aviso de otra (mismo criterio que el
 * portal del cliente con su contrato, invariante 5). Los casos de uso y
 * `LecturaNotificacionesEloquent` lo llaman siempre, y el id de la cuenta
 * sale de la sesión autenticada, nunca de un parámetro de la petición.
 *
 * `usuario_id` es un entero plano con FK real a `sec_user`, sin `belongsTo`
 * hacia `Seguridad` (ADR 0011, extensión 26/8/2026, punto 5). Lleva
 * {@see RegistraBitacora} como todo modelo de dominio (ADR 0025 punto 8).
 *
 * @property int $id
 * @property int $usuario_id
 * @property TipoNotificacion $tipo
 * @property string $clave_evento
 * @property array<string, string|int>|null $parametros
 * @property RecursoNotificable $recurso_tipo
 * @property int $recurso_id
 * @property CarbonImmutable|null $leida_en
 */
class Notificacion extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ntf_notificaciones';

    /** @var list<string> */
    protected $fillable = [
        'usuario_id',
        'tipo',
        'clave_evento',
        'parametros',
        'recurso_tipo',
        'recurso_id',
        'leida_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'usuario_id' => 'integer',
            'tipo' => TipoNotificacion::class,
            'parametros' => 'array',
            'recurso_tipo' => RecursoNotificable::class,
            'recurso_id' => 'integer',
            'leida_en' => 'immutable_datetime',
        ];
    }

    /**
     * Solo los avisos de esa cuenta. Toda lectura pasa por acá.
     *
     * @param  Builder<static>  $consulta
     * @return Builder<static>
     */
    public function scopeDeUsuario(Builder $consulta, int $usuarioId): Builder
    {
        return $consulta->where($this->getTable().'.usuario_id', $usuarioId);
    }

    /**
     * @param  Builder<static>  $consulta
     * @return Builder<static>
     */
    public function scopeSinLeer(Builder $consulta): Builder
    {
        return $consulta->whereNull($this->getTable().'.leida_en');
    }
}

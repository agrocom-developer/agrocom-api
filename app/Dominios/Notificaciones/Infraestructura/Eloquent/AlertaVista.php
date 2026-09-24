<?php

namespace App\Dominios\Notificaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que una cuenta hizo con una alerta técnica en la campana (abrirla,
 * limpiarla). Ver la migración de `ntf_alertas_vistas`: es estado de lectura
 * de UNA cuenta, no el estado de la alerta.
 *
 * Toda consulta parte de {@see self::scopeDeUsuario()}: el estado de una
 * cuenta nunca lo lee ni lo escribe otra (mismo criterio que `Notificacion`).
 */
class AlertaVista extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'ntf_alertas_vistas';

    protected $fillable = [
        'usuario_id',
        'alerta_id',
        'leida_en',
        'limpiada_en',
    ];

    protected function casts(): array
    {
        return [
            'usuario_id' => 'integer',
            'alerta_id' => 'integer',
            'leida_en' => 'immutable_datetime',
            'limpiada_en' => 'immutable_datetime',
        ];
    }

    /**
     * Solo lo que hizo esa cuenta. Toda lectura pasa por acá.
     *
     * @param  Builder<static>  $consulta
     * @return Builder<static>
     */
    public function scopeDeUsuario(Builder $consulta, int $usuarioId): Builder
    {
        return $consulta->where($this->getTable().'.usuario_id', $usuarioId);
    }
}

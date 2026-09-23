<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use LogicException;

/**
 * Las reglas del motor, indexadas por el evento que atiende. Las reglas se
 * registran por tag en `NotificacionesServiceProvider`; sumar un aviso no
 * toca esta clase. Dos reglas para el mismo evento serían dos avisos
 * distintos compitiendo por la misma identidad, así que se rechaza al armar
 * el registro en vez de dejar que una pise a la otra en silencio.
 */
final class ReglasDeNotificacion
{
    /** @var array<class-string, ReglaNotificacion> */
    private array $porEvento = [];

    /**
     * @param  iterable<ReglaNotificacion>  $reglas
     */
    public function __construct(iterable $reglas)
    {
        foreach ($reglas as $regla) {
            $evento = $regla->evento();

            if (isset($this->porEvento[$evento])) {
                throw new LogicException("Hay dos reglas de notificación para el evento {$evento}.");
            }

            $this->porEvento[$evento] = $regla;
        }
    }

    public function para(object $evento): ?ReglaNotificacion
    {
        return $this->porEvento[$evento::class] ?? null;
    }

    /** @return list<class-string> */
    public function eventos(): array
    {
        return array_keys($this->porEvento);
    }
}

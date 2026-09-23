<?php

namespace App\Dominios\Compartido\Infraestructura\Eloquent;

/**
 * Hace que un modelo respete {@see ModoSoloLectura}: guardar, borrar o
 * restaurar con el modo activo lanza en vez de escribir. Lo declara la clase
 * base {@see ModeloDominio}, así que ningún modelo de dominio puede olvidarlo.
 *
 * Se engancha a `saving`/`deleting`/`restoring` — los eventos que Eloquent
 * dispara ANTES de tocar la base — para que el rechazo ocurra sin haber
 * escrito nada, ni siquiera una fila de bitácora.
 */
trait RespetaModoSoloLectura
{
    public static function bootRespetaModoSoloLectura(): void
    {
        foreach (['saving', 'deleting', 'restoring'] as $evento) {
            static::registerModelEvent($evento, static function ($modelo): void {
                ModoSoloLectura::verificar($modelo);
            });
        }
    }
}

<?php

namespace App\Dominios\Compartido\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Un modelo de dominio intentó guardarse, borrarse o restaurarse mientras el
 * request corre en modo de solo lectura (ver `ModoSoloLectura`). Hoy lo activa
 * quien mira el sistema como otra persona (tarea 140).
 *
 * Extiende `AuthorizationException` para responder 403 en vez de un 500: no es
 * un fallo del sistema, es una escritura que no debía intentarse.
 */
final class EscrituraEnModoSoloLectura extends AuthorizationException
{
    /**
     * El mensaje no nombra la clase del modelo: un 403 que llega al navegador (o
     * a un `fetch`) no debe mostrar nombres de clases internas — mismo criterio
     * que `ErroresHttpEnEspanol`.
     */
    public static function porSerSoloLectura(): self
    {
        return new self(Texto::de('ui.errores.escritura_en_modo_solo_lectura'));
    }
}

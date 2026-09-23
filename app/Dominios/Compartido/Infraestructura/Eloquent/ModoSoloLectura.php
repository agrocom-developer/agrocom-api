<?php

namespace App\Dominios\Compartido\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\Excepciones\EscrituraEnModoSoloLectura;
use Illuminate\Database\Eloquent\Model;

/**
 * Interruptor de "solo lectura" para el request en curso (tarea 140).
 *
 * Es la segunda línea de defensa de la vista "como otro usuario": la primera
 * rechaza todo método HTTP que no sea de lectura antes de llegar a ningún
 * controlador; esta corta lo que la primera no puede ver — una ruta GET que
 * escribiera por descuido, o un caso de uso disparado desde una vista. Con el
 * modo activo, todo modelo de dominio ({@see ModeloDominio}) que intente
 * guardarse, borrarse o restaurarse lanza {@see EscrituraEnModoSoloLectura}.
 *
 * Por qué importa además del rechazo por método: sin esto, una escritura que
 * sí ocurriera quedaría firmada por la persona OBSERVADA (`created_by`,
 * `updated_by` y el actor de la bitácora salen del guard, que en esa vista es
 * la cuenta observada), indistinguible de algo que hizo ella de verdad.
 *
 * Alcance honesto: cubre los eventos `saving`/`deleting`/`restoring` de
 * Eloquent. No cubre `saveQuietly()`, `withoutEvents()` ni las escrituras
 * masivas del query builder (`Modelo::query()->update(...)`), que no disparan
 * eventos; el repo no usa SQL crudo de mutación (ADR 0012).
 *
 * Estado estático a propósito: el modo dura un request y el que lo activa
 * (`AplicarVistaComo`) lo desactiva en un `finally`, así que no se filtra al
 * siguiente request de un proceso que se reutilice.
 */
final class ModoSoloLectura
{
    private static bool $activo = false;

    public static function activar(): void
    {
        self::$activo = true;
    }

    public static function desactivar(): void
    {
        self::$activo = false;
    }

    public static function activo(): bool
    {
        return self::$activo;
    }

    /**
     * @throws EscrituraEnModoSoloLectura si el modo está activo
     */
    public static function verificar(Model $modelo): void
    {
        // `$modelo` no se usa hoy: queda en la firma para el día que haya una excepción
        // por modelo (p. ej. una tabla de plataforma que sí deba escribirse).
        if (self::$activo) {
            throw EscrituraEnModoSoloLectura::porSerSoloLectura();
        }
    }
}

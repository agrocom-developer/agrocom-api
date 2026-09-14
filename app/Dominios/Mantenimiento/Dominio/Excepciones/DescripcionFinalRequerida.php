<?php

namespace App\Dominios\Mantenimiento\Dominio\Excepciones;

use RuntimeException;

/**
 * `MaquinaEstadosOrdenMantenimiento::cerrar()` exige una descripción final no
 * vacía (HU-89, tarea 104) — la guarda vive en el dominio, no solo en
 * `CerrarOrdenMantenimientoRequest`, para que ningún llamador futuro (otro
 * controlador, un comando de consola) pueda cerrar una orden sin dejar
 * constancia de qué se hizo.
 */
final class DescripcionFinalRequerida extends RuntimeException
{
    public static function paraCierre(): self
    {
        return new self('El cierre de una orden de mantenimiento requiere una descripción final.');
    }
}

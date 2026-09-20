<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Guarda de `Aplicacion/ActualizarEstadiaHacienda` y `Aplicacion/FinalizarEstadiaHacienda`
 * (invariante 7 de CLAUDE.md, vía `Dominio\MaquinaEstados\TransicionesEstadia`):
 * una estadía `finalizada` ya cuenta días para justificar gastos, así que no
 * se edita ni se vuelve a finalizar — se da de baja y, si corresponde, se
 * vuelve a registrar. Cubre las dos guardas con la misma excepción porque el
 * motivo de fondo es el mismo: la estadía ya dejó de estar en curso.
 */
final class EstadiaYaFinalizada extends RuntimeException
{
    public static function porId(int $estadiaId): self
    {
        return new self(Texto::de('operaciones.errores.estadia_ya_finalizada', ['id' => $estadiaId]));
    }
}

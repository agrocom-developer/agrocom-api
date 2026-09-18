<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * `AsignarEquiposOrden` no pudo registrar la calda de la tanda en un `Trabajo`
 * recién creado (reforma 18/9/2026, "Orden de Trabajo"): el DTO
 * `Mezclas\Contratos\RegistroMezcla` no se pudo construir, o
 * `EscrituraMezclas::registrarMezcla()` devolvió algo distinto de `aplicado`.
 * No debería pasar en el uso normal (`AsignarEquipoOrdenRequest` ya validó la
 * forma de `calda[]` antes) — es una guarda defensiva, no una regla de
 * negocio esperable. Dentro de la misma transacción que crea los `Trabajo`:
 * si esto se lanza, revierte la tanda entera (comportamiento todo-o-nada,
 * correcto para una acción de panel).
 */
final class CaldaNoRegistrada extends RuntimeException
{
    public static function porOrdenId(int $ordenId): self
    {
        return new self(Texto::de('operaciones.errores.calda_no_registrada', ['orden' => $ordenId]));
    }
}

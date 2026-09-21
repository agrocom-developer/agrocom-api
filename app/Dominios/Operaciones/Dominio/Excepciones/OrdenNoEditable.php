<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrden;
use RuntimeException;

/**
 * Se intentó editar una orden cerrada (`consumida`, `cancelada` o `vencida`).
 * Una orden abierta —`emitida`, `vigente` o `pausada`— sí se corrige (ADR 0022,
 * adenda del 19/9/2026, {@see PoliticaEdicionOrden});
 * hasta entonces solo se editaba una `emitida` (HU-25, tarea 38), porque una
 * `vigente` puede estar ya en el catálogo de la app de campo con el piloto
 * operando sobre esos datos.
 */
final class OrdenNoEditable extends RuntimeException
{
    public static function porEstado(string $estado): self
    {
        return new self(Texto::de('operaciones.errores.orden_no_editable', ['estado' => $estado]));
    }
}

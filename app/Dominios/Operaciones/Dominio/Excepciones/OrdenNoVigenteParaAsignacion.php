<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use RuntimeException;

/**
 * Guarda de `AsignarEquiposOrden` (HU-70, tarea 85): solo una orden `vigente`
 * puede repartir sus hectáreas entre equipos — mismo criterio que
 * `abrirTrabajo()` (tarea 12): sin orden vigente no hay trabajo legítimo que
 * abrir, sea por sync o por asignación desde el panel.
 */
final class OrdenNoVigenteParaAsignacion extends RuntimeException
{
    public static function porOrden(int $ordenId): self
    {
        return new self("La orden #{$ordenId} no está vigente: no admite asignación de equipos.");
    }
}

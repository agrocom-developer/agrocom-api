<?php

namespace App\Dominios\Compartido\Infraestructura\Eloquent;

use Illuminate\Support\Facades\Auth;

/**
 * Completa las columnas de auditoría `created_by` / `updated_by` (ADR 0007)
 * desde el usuario autenticado, vía eventos de Eloquent — no depende de que
 * cada caso de uso lo recuerde. Si no hay usuario autenticado (seeders,
 * comandos, endpoints aún sin auth — HU-03 pendiente) quedan en NULL o en el
 * valor asignado explícitamente.
 *
 * La bitácora transversal (quién/qué/antes/después) del ADR 0007 es una pieza
 * aparte, todavía pendiente: este trait solo cubre la autoría por fila.
 */
trait RegistraAutoria
{
    public static function bootRegistraAutoria(): void
    {
        static::creating(function (ModeloDominio $modelo): void {
            $usuarioId = Auth::id();

            if ($usuarioId !== null) {
                $modelo->created_by ??= (int) $usuarioId;
                $modelo->updated_by ??= (int) $usuarioId;
            }
        });

        static::updating(function (ModeloDominio $modelo): void {
            $usuarioId = Auth::id();

            if ($usuarioId !== null) {
                $modelo->updated_by = (int) $usuarioId;
            }
        });
    }
}

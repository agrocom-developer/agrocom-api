<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\TrabajoValidadoNoEliminable;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un trabajo ya sincronizado (HU-93, tarea 108). Soft delete,
 * nunca físico (ADR 0007) — mismo molde que `EliminarOrden`.
 *
 * Guarda (invariante 2 de CLAUDE.md): un trabajo `validado` (TODAS sus
 * sesiones vigentes ya pasaron por HU-14) nunca se elimina — criterio por
 * defecto de `docs/negocio/observaciones_operaciones_comercial_2026-09-13.md`
 * §4.4. Un trabajo `abierto` o `cerrado` sin validar sí se da de baja
 * libremente: sus sesiones (si las tiene) quedan colgando de un trabajo
 * borrado lógicamente, mismo criterio de soft delete que el resto del panel.
 *
 * `updated_by` se fija a mano ANTES del `delete()` — el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarOrden`/`EliminarDron`.
 */
final class EliminarTrabajo
{
    /** @throws TrabajoValidadoNoEliminable si `$trabajo` está `validado`. */
    public function ejecutar(Trabajo $trabajo): void
    {
        if ($trabajo->estadoTablero() === EstadoTableroTrabajo::Validado) {
            throw TrabajoValidadoNoEliminable::porId((int) $trabajo->id);
        }

        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $trabajo->updated_by = (int) $usuarioId;
            $trabajo->save();
        }

        $trabajo->delete();
    }
}

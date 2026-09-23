<?php

namespace App\Dominios\Finanzas\Dominio;

/**
 * Regla de edición de un gasto de campaña (tarea 134, queja del dueño del
 * 22/9/2026: "casi nada de Finanzas se puede corregir después de creado").
 * Hasta entonces un gasto era inmutable salvo baja; ahora se corrige mientras
 * nadie más ya cuente con su monto:
 * - Un gasto sin rendición asociada (`rendicion_id` nulo) siempre se edita.
 * - Asociado a una rendición `Abierta`, también: esa rendición todavía no
 *   sumó su monto a nada (invariante 2 de CLAUDE.md — no hay total congelado
 *   que pisar).
 * - Asociado a una rendición `Presentada` o `Aprobada`, no: esa rendición ya
 *   recalculó y congeló su `monto` sumando este gasto
 *   (`MaquinaEstadosRendicion::presentar()`/`aprobar()`); cambiarlo por
 *   debajo dejaría el total de la rendición mintiendo.
 *
 * Regla pura, sin Eloquent (verificado por `tests/Unit/ArquitecturaModulosTest`).
 * La lee quien aplica la edición (`Aplicacion/ActualizarGasto`) y quien la
 * ofrece (el listado), mismo criterio que `PoliticaEdicionOrden`.
 */
final class PoliticaEdicionGasto
{
    /** ¿El gasto admite corregir sus datos? No si su rendición ya congeló el monto. */
    public static function admiteEdicion(?EstadoRendicion $estadoRendicion): bool
    {
        return $estadoRendicion === null || $estadoRendicion === EstadoRendicion::Abierta;
    }
}

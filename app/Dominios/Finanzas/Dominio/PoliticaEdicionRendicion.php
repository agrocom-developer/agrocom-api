<?php

namespace App\Dominios\Finanzas\Dominio;

/**
 * Regla de edición de la cabecera de una rendición de campo (tarea 134,
 * queja del dueño del 22/9/2026: "casi nada de Finanzas se puede corregir
 * después de creado"). Mismo criterio que `PoliticaEdicionOrdenTrabajo`:
 * - Se corrige mientras sigue `Abierta`: todavía no presentó nada, `monto`
 *   sigue en `0.00` y ningún otro rol depende todavía de estos datos.
 * - En cuanto pasa a `Presentada`, ya recalculó y congeló `monto` sumando
 *   sus gastos y quedó a la espera de que el encargado decida (invariante 2
 *   de CLAUDE.md: no se pisa lo que ya significa algo para otro rol). En
 *   `Aprobada` ya es historia liquidada. Ninguna de las dos se edita.
 *
 * Solo decide sobre la CABECERA (`base_id`, `jefe_campo_id`, `fecha`,
 * `descripcion`) — `estado`, `monto` y `aprobado_por` son responsabilidad
 * exclusiva de `MaquinaEstadosRendicion` (invariante 7): editar no es una
 * transición.
 *
 * Regla pura, sin Eloquent (verificado por `tests/Unit/ArquitecturaModulosTest`).
 * La lee quien aplica la edición (`Aplicacion/ActualizarRendicion`) y quien
 * la ofrece (el detalle y el listado).
 */
final class PoliticaEdicionRendicion
{
    /** ¿La rendición admite corregir su cabecera? Solo mientras sigue `Abierta`. */
    public static function admiteEdicion(EstadoRendicion $estado): bool
    {
        return $estado === EstadoRendicion::Abierta;
    }
}

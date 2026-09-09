<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Frontera de lectura de Comercial hacia otros módulos (ADR 0003, regla 2):
 * el avance de un contrato — hectáreas contratadas, aplicadas y facturadas,
 * monto facturado — para quien no puede importar `Aplicacion\ObtenerAvanceComercial`
 * directo. Único consumidor hoy: `Portal\AvancePortalController` (HU-41,
 * tarea 55), que pinta "mi avance" del contrato de la sesión — antes de la
 * tarea 68 importaba el caso de uso de `Aplicacion/` sin pasar por acá, la
 * única importación cross-módulo de ese tipo en todo `app/Dominios/`
 * (revisión línea por línea del PR #106, 4/9/2026).
 *
 * `porContrato` delega en `ObtenerAvanceComercial->ejecutar(contratoId: ...)`
 * sin duplicar la fórmula: es la misma cuenta que ya usan el reporte
 * comercial y el dashboard, no una segunda.
 */
interface LecturaAvanceComercial
{
    /** `null` si el contrato no existe o está borrado (soft delete). */
    public function porContrato(int $contratoId): ?DatosAvanceComercial;
}

<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Frontera de lectura de Comercial hacia el panel (ADR 0003, regla 2): los
 * contadores reales que alimentan los badges del menú lateral (`CascaraPanel`,
 * módulo `Seguridad`) para los ítems que este módulo posee — nunca el modelo
 * Eloquent cruzando la frontera. Mismo criterio que
 * `Operaciones\Contratos\LecturaContadoresPanel`.
 */
interface LecturaContadoresPanel
{
    /** Contratos «En ejecución» (estado `vigente`): los únicos que bloquean sus lotes y admiten órdenes. */
    public function contratosEnEjecucion(): int;
}

<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla 2):
 * el listener de `SesionValidada` (`Finanzas/Aplicacion/GenerarDevengosSesion.php`,
 * HU-16) solo recibe `$sesionId` — este contrato es cómo resuelve el resto
 * (`piloto_id`, `auxiliar_id`, `hectareas_declaradas`) sin importar `Sesion`.
 */
interface LecturaSesionValidada
{
    /**
     * `null` si la sesión no existe — no debería pasar cuando lo invoca el
     * listener de `SesionValidada` (el evento solo se dispara después de
     * persistir la fila), pero el contrato no asume ese contexto de quien
     * llama.
     */
    public function obtener(int $sesionId): ?DatosSesionValidada;
}

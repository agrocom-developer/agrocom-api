<?php

namespace App\Dominios\Seguridad\Contratos;

use Illuminate\Http\Request;

/**
 * Frontera de Seguridad hacia otros módulos (ADR 0003, regla 2) para que un
 * consumidor de la API de campo (`auth:sanctum`, ADR 0008) sepa qué persona
 * de `Personal` es la dueña del token que firma el request, sin importar
 * `SecUser` (`tests/Unit/ArquitecturaModulosTest.php` lo prohíbe).
 */
interface IdentidadOperarioToken
{
    /**
     * `persona_id` de la cuenta autenticada, o `null` si esa cuenta no tiene
     * persona asociada (hoy posible: `sec_user.persona_id` es nullable).
     */
    public function personaId(Request $request): ?int;

    /**
     * ¿La cuenta autenticada tiene el permiso `$codigo` en ALGUNO de sus
     * roles vivos (unión, no "rol activo")? Primer chequeo de `sec_permission`
     * dentro de `routes/api.php` (HU-17, tarea 24): un token de dispositivo
     * (Sanctum, ADR 0008) no tiene noción de "rol activo de sesión" — ese
     * concepto es de la cookie de sesión del panel (ADR 0004, extensión
     * 27/8/2026, punto 6, "fuera de alcance... para la app de campo") — así
     * que la unión de roles es la única evaluación posible, no un atajo.
     */
    public function tienePermiso(Request $request, string $codigo): bool;
}

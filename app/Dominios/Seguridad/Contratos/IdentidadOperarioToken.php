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
}

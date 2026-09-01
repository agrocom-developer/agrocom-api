<?php

namespace App\Dominios\Seguridad\Contratos;

use Illuminate\Http\Request;

/**
 * Frontera de Seguridad hacia otros módulos (ADR 0003, regla 2) para las
 * cosas que toda pantalla del panel necesita: verificar un permiso contra el
 * ROL ACTIVO de la sesión (nunca la unión de roles del usuario, invariante
 * 10 de CLAUDE.md), los datos de cáscara compartida (menú, roles, tema,
 * chrome), y — desde HU-14, tarea 14 — la `persona_id` del usuario del panel
 * autenticado. El consumidor nunca recibe `SecUser` ni ningún otro modelo
 * Eloquent de Seguridad — solo primitivos y arrays.
 */
interface AutorizacionPanelWeb
{
    public function tienePermiso(Request $request, string $codigoPermiso): bool;

    /** @return array<string, mixed> */
    public function cascara(Request $request): array;

    /**
     * `persona_id` del usuario de panel autenticado, o `null` si esa cuenta
     * no tiene persona asociada (mismo caso límite que
     * `IdentidadOperarioToken::personaId()`, del lado del token de campo).
     * Necesaria para la policy "validador ≠ piloto de esa sesión, a nivel de
     * persona" (invariante 4) — sin esto, `Operaciones` tendría que importar
     * `SecUser` directo para resolver quién es, de persona, el jefe que está
     * mirando la pantalla.
     */
    public function personaId(Request $request): ?int;
}

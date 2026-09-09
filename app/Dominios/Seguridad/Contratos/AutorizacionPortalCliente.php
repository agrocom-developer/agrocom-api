<?php

namespace App\Dominios\Seguridad\Contratos;

use Illuminate\Http\Request;

/**
 * Frontera de Seguridad hacia el módulo `Portal` (ADR 0003, regla 2), mismo
 * espíritu que {@see AutorizacionPanelWeb} pero para el guard `cliente`: una
 * cuenta de portal no tiene rol ni permiso de grano fino (invariante 10 no
 * aplica acá — no hay `sec_user_role` del lado del portal), así que el único
 * gate posible es "¿hay sesión de portal?" + "¿de qué contrato?".
 *
 * `contratoId()` es la pieza central de la invariante 5 de CLAUDE.md: TODO
 * endpoint de `/portal/*` resuelve el contrato desde ACÁ, nunca desde un
 * parámetro de ruta sin verificar. El consumidor (`Portal`) nunca importa
 * `SecUser`/`SecUsuarioCliente` directo — mismo criterio que el panel interno.
 */
interface AutorizacionPortalCliente
{
    /**
     * `contrato_id` de la cuenta de portal autenticada, o `null` si no hay
     * sesión de portal vigente (fail-closed: sin contrato resuelto, ningún
     * endpoint de `Portal` puede armar una consulta).
     */
    public function contratoId(Request $request): ?int;

    /**
     * Datos de cáscara mínimos del portal: tema persistido y nombre de la
     * cuenta — sin menú, sin roles: una cuenta de portal no tiene `sec_menu`
     * que listar (ADR 0002, punto 6).
     *
     * @return array<string, mixed>
     */
    public function cascara(Request $request): array;
}

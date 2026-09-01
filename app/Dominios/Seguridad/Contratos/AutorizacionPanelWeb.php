<?php

namespace App\Dominios\Seguridad\Contratos;

use Illuminate\Http\Request;

/**
 * Frontera de Seguridad hacia otros módulos (ADR 0003, regla 2) para las dos
 * cosas que toda pantalla del panel necesita: verificar un permiso contra el
 * ROL ACTIVO de la sesión (nunca la unión de roles del usuario, invariante
 * 10 de CLAUDE.md), y los datos de cáscara compartida (menú, roles, tema,
 * chrome). El consumidor nunca recibe `SecUser` ni ningún otro modelo
 * Eloquent de Seguridad — solo primitivos y arrays.
 */
interface AutorizacionPanelWeb
{
    public function tienePermiso(Request $request, string $codigoPermiso): bool;

    /** @return array<string, mixed> */
    public function cascara(Request $request): array;
}

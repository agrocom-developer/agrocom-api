<?php

namespace App\Dominios\Seguridad\Aplicacion;

/**
 * Nodo del árbol de menú ya resuelto por {@see ObtenerMenuPorRolActivo}
 * para el rol activo de la sesión: value object de solo lectura, sin ningún
 * método que vuelva a consultar `sec_permission`/`sec_role` — el filtrado
 * de permisos ya ocurrió antes de construirlo.
 *
 * `label`/`icono`/`ruta` viajan tal cual están en `sec_menu`: `label` es la
 * clave de traducción SIN resolver (ADR 0013 — traducirla vía `__()` es
 * responsabilidad de presentación) y `ruta` es el nombre de ruta o URL SIN
 * resolver a `href` (construir el link final tampoco es responsabilidad de
 * este módulo). Lo consumen los componentes
 * `menu-item`/`collapsible-menu-group`/`sidebar-nav`
 * (`docs/diseno/sistema_diseno_panel.md` §4.3/§4.4/§4.6) del lado de
 * `frontend` — este módulo no sabe de Blade ni de `route()`.
 */
final readonly class ItemMenu
{
    /**
     * @param  list<self>  $hijos  Solo los hijos ya visibles para el rol
     *                             activo — nunca los que quedaron ocultos
     *                             por falta de permiso.
     */
    public function __construct(
        public int $id,
        public string $label,
        public string $icono,
        public ?string $ruta,
        public int $orden,
        public array $hijos = [],
    ) {}
}

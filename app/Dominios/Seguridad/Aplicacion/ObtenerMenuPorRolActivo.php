<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Support\Collection;

/**
 * Árbol de menú del panel visible para el ROL ACTIVO de la sesión (ADR 0004,
 * extensión 27/8/2026; CLAUDE.md invariante 10) — nunca la unión de todos
 * los roles del usuario. Lo consume `frontend` para pintar
 * `sidebar-nav`/`collapsible-menu-group`/`menu-item`
 * (`docs/diseno/sistema_diseno_panel.md` §4.3/§4.4/§4.6); este caso de uso
 * no sabe nada de Blade ni de rutas resueltas — devuelve {@see ItemMenu}
 * "en crudo" (ver su docblock).
 *
 * No resuelve ni revalida el rol activo: lo recibe ya resuelto
 * (`$idRolActivo`), responsabilidad de `ResolverRolActivo`/`ElegirRolActivo`
 * (fuera de alcance de esta clase). Filtra exclusivamente con
 * {@see SecUser::tienePermisoEnRol()} — nunca con `tienePermiso()` (unión),
 * que dejaría pasar ítems de un rol que el usuario tiene asignado pero que
 * no es el activo en esta sesión.
 *
 * ### Regla de visibilidad de un ítem con hijos (decisión de diseño)
 *
 * Un ítem SIN hijos en el catálogo (hoja) es visible si `permission_id` es
 * `null` o el rol activo tiene ese permiso — tal cual define la migración
 * de `sec_menu`.
 *
 * Un ítem CON hijos en el catálogo (grupo/`collapsible-menu-group`) sigue
 * una regla distinta a propósito: un `permission_id` NULO en un grupo NO lo
 * hace visible por sí solo (a diferencia de una hoja). Si lo hiciera,
 * cualquier grupo cuyos hijos estén todos ocultos por falta de permiso
 * igual se mostraría vacío en el sidebar — justo el caso que se quiere
 * evitar. Un grupo es visible si:
 * - tiene su PROPIO `permission_id` (no nulo) y el rol activo lo cumple (el
 *   grupo también actúa como link directo, además de contenedor), o
 * - al menos uno de sus hijos quedó visible tras el filtrado recursivo.
 *
 * En corto: se oculta el padre completo si ningún hijo es visible Y el
 * padre tampoco tiene un permiso propio satisfecho; se muestra si al menos
 * una de las dos condiciones se cumple. Es el criterio más común en
 * AdminLTE (nunca un grupo colapsable vacío), con la excepción explícita de
 * un grupo que además es, él mismo, una acción permitida.
 */
final class ObtenerMenuPorRolActivo
{
    /**
     * @return list<ItemMenu>
     */
    public function ejecutar(SecUser $usuario, int $idRolActivo): array
    {
        $raices = SecMenu::query()
            ->whereNull('padre_id')
            ->orderBy('orden')
            ->get();

        return $this->nodosVisibles($raices, $usuario, $idRolActivo);
    }

    /**
     * @param  Collection<int, SecMenu>  $items
     * @return list<ItemMenu>
     */
    private function nodosVisibles(Collection $items, SecUser $usuario, int $idRolActivo): array
    {
        $resultado = [];

        foreach ($items as $item) {
            $nodo = $this->resolverNodo($item, $usuario, $idRolActivo);

            if ($nodo !== null) {
                $resultado[] = $nodo;
            }
        }

        return $resultado;
    }

    private function resolverNodo(SecMenu $item, SecUser $usuario, int $idRolActivo): ?ItemMenu
    {
        $hijosEnCatalogo = $item->hijos;
        $hijosVisibles = $this->nodosVisibles($hijosEnCatalogo, $usuario, $idRolActivo);

        $cumplePermisoPropio = $this->cumplePermiso($item, $usuario, $idRolActivo);

        $esVisible = $hijosEnCatalogo->isEmpty()
            // Hoja: la regla simple de la migración (permiso nulo o cumplido).
            ? $cumplePermisoPropio
            // Grupo: permiso propio EXPLÍCITO y cumplido, o algún hijo visible.
            // Un permission_id nulo NO habilita por sí solo (evita grupos vacíos).
            : (($item->permission_id !== null && $cumplePermisoPropio) || $hijosVisibles !== []);

        if (! $esVisible) {
            return null;
        }

        return new ItemMenu(
            id: $item->id,
            label: $item->label,
            icono: $item->icono,
            ruta: $item->ruta,
            orden: $item->orden,
            hijos: $hijosVisibles,
            descripcion: $item->descripcion,
        );
    }

    private function cumplePermiso(SecMenu $item, SecUser $usuario, int $idRolActivo): bool
    {
        if ($item->permission_id === null) {
            return true;
        }

        $codigo = $item->permiso?->code;

        // FK con RESTRICT + soft delete en `sec_permission`: la fila del
        // permiso no puede borrarse físicamente mientras este ítem exista,
        // pero SÍ puede quedar soft-deleteada (se retira el permiso del
        // catálogo sin tocar el menú). Sin código no hay permiso que
        // verificar — fail-closed, nunca "visible por defecto".
        if ($codigo === null) {
            return false;
        }

        return $usuario->tienePermisoEnRol($codigo, $idRolActivo);
    }
}

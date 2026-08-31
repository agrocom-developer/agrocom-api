<?php

namespace App\Dominios\Seguridad\Infraestructura;

use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PermisoVista;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Registra lo que el módulo `Seguridad` necesita exponer al framework y que
 * no se resuelve por convención (Eloquent y casos de uso ya se resuelven
 * solos vía autoload/constructor injection, sin necesitar esto).
 *
 * Primer `ServiceProvider` por módulo del proyecto — ADR 0003 preveía este
 * patrón ("cada módulo registra su propio ServiceProvider") sin haberlo
 * necesitado todavía. Nace acá porque las páginas Blade del panel (`pages/`,
 * `docs/diseno/sistema_diseno_panel.md` §3 nota de `pages/`: "son las
 * vistas reales bajo `Infraestructura/Http/` de cada módulo, ADR 0008 — no
 * le corresponde a `design-ui`") viven físicamente dentro de este módulo
 * (`Infraestructura/Http/Views/`), no bajo `resources/views/` — Blade
 * necesita que algo le informe esa ruta adicional. Un namespace de vista
 * (`seguridad::pages.dashboard`, etc.) en vez de agregar la ruta a secas
 * (`View::addLocation`) evita colisiones de nombre con vistas de otro módulo
 * que en el futuro adopte el mismo patrón.
 *
 * También registra la directiva `@puede('codigo.del.permiso')`, con la que
 * cualquier vista oculta un control sin permiso — ver {@see PermisoVista}
 * para por qué la decisión vive en una sola clase y por qué es fail-closed.
 */
final class SeguridadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('seguridad', app_path('Dominios/Seguridad/Infraestructura/Http/Views'));

        // Genera @puede / @elsepuede / @endpuede. Evalúa SIEMPRE contra el rol
        // activo de la sesión, nunca contra la unión de roles del usuario
        // (CLAUDE.md invariante 10).
        Blade::if('puede', fn (string $codigo): bool => PermisoVista::puede($codigo));
    }
}

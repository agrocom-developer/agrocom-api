<?php

namespace App\Dominios\Seguridad\Infraestructura;

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
 */
final class SeguridadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('seguridad', app_path('Dominios/Seguridad/Infraestructura/Http/Views'));
    }
}

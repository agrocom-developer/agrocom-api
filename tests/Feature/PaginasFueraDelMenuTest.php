<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/*
 * Páginas del panel que no están en el menú (búsqueda, perfil, Mis
 * notificaciones…): el módulo Inicio se activa y su panel muestra un solo
 * ítem, sin enlace, con el nombre de la página. Ese nombre vive en
 * `lang/es/paginas.php` (`rutas.<ruta>`).
 *
 * Dos compuertas:
 * - cada ruta GET del panel que no es de un ítem del menú, ni de su detalle, ni
 *   un archivo o una redirección, tiene su nombre declarado (una página nueva no
 *   puede quedar sin nombre);
 * - la vista del layout pinta el ítem sin enlace fuera del menú y nada en el
 *   tablero ni en una página de un ítem.
 */

$raiz = dirname(__DIR__, 2);

/**
 * Rutas GET `panel.*` que no son una pantalla con layout propio del panel:
 * descargas, redirecciones y la pantalla de elegir rol (otro cascarón).
 */
function rutasDelPanelQueNoSonPagina(string $nombre): bool
{
    return $nombre === 'panel.dashboard'
        || $nombre === 'panel.rol-activo.selector'
        || (bool) preg_match('/\.(archivo|foto|abrir)$/', $nombre);
}

/**
 * Misma regla que `$itemEsActivo` de templates/panel-layout: la ruta del ítem, o
 * cualquiera de su recurso (`panel.<recurso>.*`) si la ruta del ítem tiene 3+ segmentos.
 *
 * @param  list<string>  $rutasDelMenu
 */
function perteneceAUnItemDelMenu(string $nombre, array $rutasDelMenu): bool
{
    foreach ($rutasDelMenu as $ruta) {
        if ($ruta === $nombre) {
            return true;
        }

        $segmentos = explode('.', $ruta);

        if (count($segmentos) >= 3 && str_starts_with($nombre, implode('.', array_slice($segmentos, 0, -1)).'.')) {
            return true;
        }
    }

    return false;
}

it('toda página del panel fuera del menú tiene su nombre en lang/es/paginas.php', function () use ($raiz) {
    $codigo = (string) file_get_contents($raiz.'/database/seeders/Catalogo/SecMenuSeeder.php');
    preg_match_all('/ruta: \'([^\']+)\'/', $codigo, $m);
    $rutasDelMenu = array_values(array_unique($m[1]));

    $sinNombre = [];

    foreach (Route::getRoutes() as $ruta) {
        $nombre = $ruta->getName();

        if ($nombre === null
            || ! str_starts_with($nombre, 'panel.')
            || ! in_array('GET', $ruta->methods(), true)
            || rutasDelPanelQueNoSonPagina($nombre)
            || perteneceAUnItemDelMenu($nombre, $rutasDelMenu)) {
            continue;
        }

        $titulo = __("paginas.rutas.{$nombre}");

        if (! is_string($titulo) || $titulo === "paginas.rutas.{$nombre}") {
            $sinNombre[] = $nombre;
        }
    }

    expect($sinNombre)->toBe([]);
});

/**
 * Pinta el layout del panel con un menú mínimo (Inicio y Mercado con un ítem)
 * como si la petición cayera en la ruta con ese nombre, y devuelve el HTML.
 */
function panelPintadoEn(string $nombreDeRuta): string
{
    $uri = '/prueba-panel/'.str_replace('.', '-', $nombreDeRuta);

    Route::get($uri, function () {
        $menu = [
            ['label' => 'menu.inicio.label', 'descripcion' => 'menu.inicio.descripcion', 'icono' => 'home', 'ruta' => 'panel.dashboard', 'hijos' => []],
            ['label' => 'menu.solicitudes.label', 'descripcion' => 'menu.solicitudes.descripcion', 'icono' => 'storefront', 'ruta' => null, 'hijos' => [
                ['label' => 'menu.solicitudes.items.solicitudes', 'icono' => 'request_quote', 'ruta' => 'panel.solicitudes.index'],
            ]],
        ];

        return Blade::render(
            '<x-templates.panel-layout :menu="$menu" :roles="[]" :notifications="[]">contenido</x-templates.panel-layout>',
            ['menu' => $menu],
        );
    })->name($nombreDeRuta);
    Route::getRoutes()->refreshNameLookups();

    return test()->get($uri)->assertOk()->getContent();
}

it('fuera del menú, Inicio queda activo y muestra solo el nombre de la página, sin enlace', function () {
    $html = panelPintadoEn('panel.buscar');

    // El único ítem del panel de Inicio: el nombre de la página, seleccionado y sin enlace.
    expect($html)
        ->toMatch('/<span\s[^>]*class="[^"]*ag-menu-item--actual[^"]*"[^>]*aria-current="page"|<span\s[^>]*aria-current="page"[^>]*class="[^"]*ag-menu-item--actual/s')
        ->and(preg_match_all('/class="[^"]*ag-menu-item--actual/', $html))->toBe(1)
        // El riel marca Inicio como activo y sigue llevando al tablero.
        ->and($html)->toMatch('/<a\s[^>]*class="ag-rail__btn is-active"[^>]*>|<a\s[^>]*href="[^"]*panel\/dashboard"[^>]*is-active/s');

    // Con el texto «Búsqueda» solo en el ítem sin enlace: ningún <a> lo contiene.
    expect(preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*Búsqueda(?:(?!<\/a>).)*<\/a>/s', $html))->toBe(0);
    expect($html)->toContain('Búsqueda');
});

it('en una página de un ítem del menú, Inicio no muestra ningún ítem propio', function () {
    $html = panelPintadoEn('panel.solicitudes.index');

    expect($html)->not->toContain('ag-menu-item--actual')
        ->and($html)->toContain('ag-menu-item is-active');
});

it('en el tablero, Inicio sigue siendo un acceso directo sin ítems', function () {
    $html = panelPintadoEn('panel.dashboard');

    expect($html)->not->toContain('ag-menu-item--actual')
        ->and($html)->not->toContain('ag-drawer__actual');
});

it('una ruta fuera del menú sin nombre declarado no inventa ítem', function () {
    $html = panelPintadoEn('panel.ruta-sin-nombre.index');

    expect($html)->not->toContain('ag-menu-item--actual');
});

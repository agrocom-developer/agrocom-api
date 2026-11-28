<?php

use App\Dominios\Seguridad\Aplicacion\ItemMenu;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\AccesosRapidos;
use Tests\TestCase;

/*
 * Los accesos rápidos de «Mi perfil»: qué pantallas ofrece cada rol. Pura: sin
 * base de datos (arranca la aplicación solo para leer las rutas registradas).
 *
 * Lo que importa: el acceso sale del MENÚ del rol activo y nunca de una lista
 * propia de permisos. Si el menú no muestra una pantalla, el acceso tampoco
 * aparece, aunque el rol la tenga en su lista de preferidas.
 */

uses(TestCase::class);

function itemMenu(string $ruta, string $etiqueta = '', string $icono = 'circle'): ItemMenu
{
    return new ItemMenu(id: 1, label: $etiqueta ?: "menu.x.items.{$ruta}", icono: $icono, ruta: $ruta, orden: 1);
}

/** @param  list<ItemMenu>  $hijos */
function moduloMenu(string $clave, array $hijos): ItemMenu
{
    return new ItemMenu(id: 1, label: "menu.{$clave}.label", icono: 'folder', ruta: null, orden: 1, hijos: $hijos);
}

/** @param  list<array{ruta: string}>  $accesos */
function rutasDe(array $accesos): array
{
    return array_column($accesos, 'ruta');
}

test('ofrece las pantallas preferidas del rol, en su orden, y no las que el menú no tiene', function () {
    $menu = [
        moduloMenu('operacion', [
            itemMenu('panel.dashboard'),
            itemMenu('panel.documentos.index'),
            itemMenu('panel.solicitudes.index'),
        ]),
    ];

    // `operador` prefiere documentos → proveedores → solicitudes…; sin proveedores en su menú, no aparecen.
    expect(rutasDe(AccesosRapidos::para('operador', $menu)))
        ->toBe(['panel.documentos.index', 'panel.solicitudes.index']);
});

test('un permiso que el menú no muestra no se ofrece, aunque el rol lo prefiera', function () {
    $menu = [moduloMenu('seguridad', [itemMenu('panel.usuarios.index')])];

    expect(rutasDe(AccesosRapidos::para('administrador', $menu)))->toBe(['panel.usuarios.index']);
});

test('cada acceso lleva ícono, etiqueta y módulo del menú, sin traducir', function () {
    $menu = [moduloMenu('solicitudes', [itemMenu('panel.solicitudes.index', 'menu.solicitudes.items.solicitudes', 'request_quote')])];

    expect(AccesosRapidos::para('administrador', $menu))->toBe([[
        'ruta' => 'panel.solicitudes.index',
        'icono' => 'request_quote',
        'etiqueta' => 'menu.solicitudes.items.solicitudes',
        'modulo' => 'menu.solicitudes.label',
        'tono' => 'warning',
    ]]);
});

test('no pasa de seis accesos', function () {
    $rutas = ['panel.solicitudes.index', 'panel.proveedores.index', 'panel.documentos.index', 'panel.drones-flota.index', 'panel.reclamos.index', 'panel.usuarios.index'];
    $menu = [moduloMenu('todo', [...array_map(itemMenu(...), $rutas), itemMenu('panel.clientes.index')])];

    expect(AccesosRapidos::para('administrador', $menu))->toHaveCount(AccesosRapidos::MAXIMO)
        ->and(rutasDe(AccesosRapidos::para('administrador', $menu)))->toBe($rutas);
});

test('un rol sin lista propia cae en las primeras pantallas de su menú, sin repetir el tablero', function () {
    $menu = [
        moduloMenu('operacion', [itemMenu('panel.dashboard'), itemMenu('panel.solicitudes.index')]),
        moduloMenu('recursos', [itemMenu('panel.drones-flota.index')]),
    ];

    expect(rutasDe(AccesosRapidos::para('un_rol_nuevo', $menu)))->toBe(['panel.solicitudes.index', 'panel.drones-flota.index'])
        ->and(rutasDe(AccesosRapidos::para(null, $menu)))->toBe(['panel.solicitudes.index', 'panel.drones-flota.index']);
});

test('un rol con lista propia pero sin ninguna de sus pantallas también cae en el menú', function () {
    $menu = [moduloMenu('recursos', [itemMenu('panel.bases.index')])];

    expect(rutasDe(AccesosRapidos::para('administrador', $menu)))->toBe(['panel.bases.index']);
});

test('encuentra las pantallas anidadas a cualquier profundidad y una pantalla suelta en la raíz', function () {
    $anidado = new ItemMenu(id: 1, label: 'menu.a.label', icono: 'folder', ruta: null, orden: 1, hijos: [
        new ItemMenu(id: 2, label: 'menu.b.label', icono: 'folder', ruta: null, orden: 1, hijos: [itemMenu('panel.usuarios.index')]),
    ]);

    expect(rutasDe(AccesosRapidos::para(null, [$anidado, itemMenu('panel.roles.index')])))
        ->toBe(['panel.usuarios.index', 'panel.roles.index']);
});

test('una ruta repetida en el menú se ofrece una sola vez, y sin menú no hay accesos', function () {
    $menu = [moduloMenu('a', [itemMenu('panel.solicitudes.index')]), moduloMenu('b', [itemMenu('panel.solicitudes.index')])];

    expect(rutasDe(AccesosRapidos::para('operador', $menu)))->toBe(['panel.solicitudes.index'])
        ->and(AccesosRapidos::para('administrador', []))->toBe([]);
});

test('cada rol con lista propia usa solo rutas que existen en el panel', function () {
    $referencias = (new ReflectionClassConstant(AccesosRapidos::class, 'POR_ROL'))->getValue();
    $rutasRegistradas = collect(app('router')->getRoutes()->getRoutesByName())->keys();

    foreach ($referencias as $rol => $rutas) {
        foreach ($rutas as $ruta) {
            expect($rutasRegistradas->contains($ruta))->toBeTrue("«{$rol}» prefiere «{$ruta}», que no existe");
        }
    }
});

test('seis accesos de ejemplo nunca comparten tono y el tono es uno de los tokens del panel', function () {
    $rutas = ['panel.solicitudes.index', 'panel.proveedores.index', 'panel.documentos.index', 'panel.drones-flota.index', 'panel.reclamos.index', 'panel.usuarios.index'];
    $menu = [moduloMenu('todo', array_map(fn (string $ruta) => itemMenu($ruta), $rutas))];

    $tonos = array_column(AccesosRapidos::para('administrador', $menu), 'tono');

    expect($tonos)->toHaveCount(6)
        ->and(array_unique($tonos))->toHaveCount(6)
        ->and(array_diff($tonos, AccesosRapidos::TONOS))->toBe([]);
});

test('cualquier conjunto de seis rutas del panel recibe seis tonos distintos', function () {
    $todas = ['solicitudes', 'proveedores', 'documentos', 'drones-flota', 'reclamos', 'usuarios', 'clientes', 'calificaciones', 'roles', 'bitacora', 'avisos', 'metricas'];

    foreach (range(0, count($todas) - 6) as $desde) {
        $menu = [moduloMenu('todo', array_map(fn (string $d) => itemMenu("panel.$d.index"), array_slice($todas, $desde, 6)))];
        $tonos = array_column(AccesosRapidos::para(null, $menu), 'tono');

        expect(array_unique($tonos))->toHaveCount(6);
    }
});

test('el tono es estable: mismo conjunto, mismo tono, y la ruta que no choca conserva el suyo', function () {
    $menu = [moduloMenu('todo', [itemMenu('panel.solicitudes.index'), itemMenu('panel.usuarios.index')])];

    expect(AccesosRapidos::para('administrador', $menu))->toBe(AccesosRapidos::para('administrador', $menu));

    $solo = array_column(AccesosRapidos::para(null, [moduloMenu('t', [itemMenu('panel.solicitudes.index')])]), 'tono', 'ruta');
    $con = array_column(AccesosRapidos::para(null, [moduloMenu('t', [itemMenu('panel.solicitudes.index'), itemMenu('panel.usuarios.index')])]), 'tono', 'ruta');

    expect($con['panel.solicitudes.index'])->toBe($solo['panel.solicitudes.index']);
});

test('completa con las pantallas del menú cuando la lista preferida no llena los lugares', function () {
    $menu = [moduloMenu('todo', [itemMenu('panel.usuarios.index'), itemMenu('panel.bitacora.index'), itemMenu('panel.dashboard')])];

    expect(rutasDe(AccesosRapidos::para('administrador', $menu)))->toBe(['panel.usuarios.index', 'panel.bitacora.index']);
});

/** El valor de `--ag-color-<tono>-contrast-fill` en un tema, tal como está escrito en el CSS de tokens. */
function colorDeRelleno(string $tema, string $tono): string
{
    $css = (string) file_get_contents(dirname(__DIR__, 2)."/resources/css/tokens/semantic/theme-{$tema}.css");

    preg_match('~--ag-color-'.preg_quote($tono, '~').'-contrast-fill:\s*([^;]+);~s', $css, $coincidencia);

    return preg_replace('~\s+~', ' ', trim($coincidencia[1] ?? '')) ?: '';
}

test('cada tono de los accesos rápidos es un color distinto, no solo un nombre distinto', function () {
    foreach (['light', 'dark'] as $tema) {
        $colores = [];

        foreach (AccesosRapidos::TONOS as $tono) {
            $color = colorDeRelleno($tema, $tono);

            expect($color)->not->toBe('', "el tono {$tono} no tiene relleno en el tema {$tema}");
            $colores[$tono] = $color;
        }

        // Dos tonos con el mismo valor se ven como la misma tarjeta repetida (`info`, `distintivo-3`
        // y `primary-2` pintan el mismo azul).
        $repetidos = array_filter(array_count_values($colores), fn (int $veces): bool => $veces > 1);

        expect($repetidos)->toBe([], "tonos con el mismo color en el tema {$tema}: ".implode(', ', array_keys(array_intersect($colores, array_keys($repetidos)))));
    }
});

test('seis accesos nunca repiten color, con cualquier orden de rutas', function () {
    $rutas = ['panel.solicitudes.index', 'panel.proveedores.index', 'panel.documentos.index', 'panel.drones-flota.index', 'panel.reclamos.index', 'panel.usuarios.index'];

    $menu = [moduloMenu('todo', array_map(fn (string $ruta): ItemMenu => itemMenu($ruta), $rutas))];
    $tonos = array_column(AccesosRapidos::para('administrador', $menu), 'tono');

    expect($tonos)->toHaveCount(6)->and(array_unique($tonos))->toHaveCount(6);
});

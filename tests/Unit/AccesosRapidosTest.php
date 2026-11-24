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
            itemMenu('panel.proveedores.index'),
            itemMenu('panel.solicitudes.index'),
        ]),
    ];

    // `encargado_operaciones` prefiere solicitudes → trabajos → …; sin vuelos ni cuadrillas en su menú, no aparecen.
    expect(rutasDe(AccesosRapidos::para('encargado_operaciones', $menu)))
        ->toBe(['panel.solicitudes.index', 'panel.proveedores.index']);
});

test('un permiso que el menú no muestra no se ofrece, aunque el rol lo prefiera', function () {
    $menu = [moduloMenu('seguridad', [itemMenu('panel.usuarios.index')])];

    expect(rutasDe(AccesosRapidos::para('admin_plataforma', $menu)))->toBe(['panel.usuarios.index']);
});

test('cada acceso lleva ícono, etiqueta y módulo del menú, sin traducir', function () {
    $menu = [moduloMenu('solicitudes', [itemMenu('panel.solicitudes.index', 'menu.solicitudes.items.solicitudes', 'request_quote')])];

    expect(AccesosRapidos::para('dueno', $menu))->toBe([[
        'ruta' => 'panel.solicitudes.index',
        'icono' => 'request_quote',
        'etiqueta' => 'menu.solicitudes.items.solicitudes',
        'modulo' => 'menu.solicitudes.label',
    ]]);
});

test('no pasa de seis accesos', function () {
    $rutas = ['panel.solicitudes.index', 'panel.proveedores.index', 'panel.disputas.index', 'panel.documentos.index', 'panel.calificaciones.index', 'panel.metricas.index'];
    $menu = [moduloMenu('todo', [...array_map(itemMenu(...), $rutas), itemMenu('panel.clientes.index')])];

    expect(AccesosRapidos::para('dueno', $menu))->toHaveCount(AccesosRapidos::MAXIMO)
        ->and(rutasDe(AccesosRapidos::para('dueno', $menu)))->toBe($rutas);
});

test('un rol sin lista propia cae en las primeras pantallas de su menú, sin repetir el tablero', function () {
    $menu = [
        moduloMenu('operacion', [itemMenu('panel.dashboard'), itemMenu('panel.solicitudes.index')]),
        moduloMenu('recursos', [itemMenu('panel.clientes.index')]),
    ];

    expect(rutasDe(AccesosRapidos::para('un_rol_nuevo', $menu)))->toBe(['panel.solicitudes.index', 'panel.clientes.index'])
        ->and(rutasDe(AccesosRapidos::para(null, $menu)))->toBe(['panel.solicitudes.index', 'panel.clientes.index']);
});

test('un rol con lista propia pero sin ninguna de sus pantallas también cae en el menú', function () {
    $menu = [moduloMenu('recursos', [itemMenu('panel.zonas.index')])];

    expect(rutasDe(AccesosRapidos::para('dueno', $menu)))->toBe(['panel.zonas.index']);
});

test('encuentra las pantallas anidadas a cualquier profundidad y una pantalla suelta en la raíz', function () {
    $anidado = new ItemMenu(id: 1, label: 'menu.a.label', icono: 'folder', ruta: null, orden: 1, hijos: [
        new ItemMenu(id: 2, label: 'menu.b.label', icono: 'folder', ruta: null, orden: 1, hijos: [itemMenu('panel.usuarios.index')]),
    ]);

    expect(rutasDe(AccesosRapidos::para('admin_plataforma', [$anidado, itemMenu('panel.roles.index')])))
        ->toBe(['panel.usuarios.index', 'panel.roles.index']);
});

test('una ruta repetida en el menú se ofrece una sola vez, y sin menú no hay accesos', function () {
    $menu = [moduloMenu('a', [itemMenu('panel.solicitudes.index')]), moduloMenu('b', [itemMenu('panel.solicitudes.index')])];

    expect(rutasDe(AccesosRapidos::para('encargado_operaciones', $menu)))->toBe(['panel.solicitudes.index'])
        ->and(AccesosRapidos::para('dueno', []))->toBe([]);
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

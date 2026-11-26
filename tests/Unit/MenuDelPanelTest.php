<?php

/*
 * Fija el árbol del menú del panel (docs/especificacion/marketplace.md §10.4):
 * cinco módulos en el riel —Mercado, Proveedores, Soporte, Seguridad y
 * Sistema— con sus ítems en orden. Lee el código del `SecMenuSeeder` (única
 * fuente del menú) sin tocar la base, y comprueba que cada ítem tenga su
 * texto en `lang/es/menu.php` y su ruta en `routes/web.php`.
 *
 * Mover una pantalla de módulo es una decisión de producto: este test falla
 * para que el cambio sea consciente y quede escrito aquí y en la especificación.
 */

$raiz = dirname(__DIR__, 2);

/**
 * @return array<string, list<string>> módulo → ítems en el orden en que se siembran por `orden`
 */
function menuSembrado(string $raiz): array
{
    $codigo = (string) file_get_contents($raiz.'/database/seeders/Catalogo/SecMenuSeeder.php');

    preg_match_all(
        '/\$this->item\(\$(\w+), \'(\w+)\', \'(\w+)\', \'\w+\', (\d+), ruta: \'([^\']+)\', codigoPermiso: \'([^\']+)\'\)/',
        $codigo,
        $coincidencias,
        PREG_SET_ORDER,
    );

    $menu = [];

    foreach ($coincidencias as [, , $modulo, $item, $orden]) {
        $menu[$modulo][(int) $orden] = $item;
    }

    foreach ($menu as &$items) {
        ksort($items);
        $items = array_values($items);
    }

    return $menu;
}

it('siembra cinco módulos con sus ítems en este orden', function () use ($raiz) {
    expect(menuSembrado($raiz))->toBe([
        'solicitudes' => ['tablero', 'solicitudes', 'clientes', 'calificaciones', 'metricas'],
        'proveedores' => ['documentos', 'proveedores', 'pilotos', 'drones_flota'],
        'soporte' => ['disputas'],
        'seguridad' => ['usuarios', 'roles', 'dispositivos', 'bitacora'],
        'sistema' => ['zonas', 'organizacion', 'configuracion', 'versiones_apk', 'avisos'],
    ]);
});

it('ordena los módulos del riel: mercado, proveedores, soporte, seguridad, sistema', function () use ($raiz) {
    $codigo = (string) file_get_contents($raiz.'/database/seeders/Catalogo/SecMenuSeeder.php');

    preg_match_all('/\$this->modulo\(\'(\w+)\', \'\w+\', (\d+)\)/', $codigo, $m, PREG_SET_ORDER);
    $porOrden = [];
    foreach ($m as [, $clave, $orden]) {
        $porOrden[(int) $orden] = $clave;
    }
    ksort($porOrden);

    expect(array_values($porOrden))->toBe(['solicitudes', 'proveedores', 'soporte', 'seguridad', 'sistema']);
});

it('cada ítem del menú tiene su texto y su módulo tiene etiqueta y descripción', function () use ($raiz) {
    /** @var array<string, array<string, mixed>> $lang */
    $lang = require $raiz.'/lang/es/menu.php';
    $faltan = [];

    foreach (menuSembrado($raiz) as $modulo => $items) {
        foreach (['label', 'descripcion'] as $campo) {
            if (! isset($lang[$modulo][$campo])) {
                $faltan[] = "menu.{$modulo}.{$campo}";
            }
        }
        foreach ($items as $item) {
            if (! isset($lang[$modulo]['items'][$item])) {
                $faltan[] = "menu.{$modulo}.items.{$item}";
            }
        }
    }

    expect($faltan)->toBe([]);
});

it('cada ítem apunta a una ruta que existe', function () use ($raiz) {
    $codigo = (string) file_get_contents($raiz.'/database/seeders/Catalogo/SecMenuSeeder.php');
    $rutas = (string) file_get_contents($raiz.'/routes/web.php');

    preg_match_all('/ruta: \'([^\']+)\'/', $codigo, $m);
    $huerfanas = array_values(array_filter(
        array_unique($m[1]),
        fn (string $ruta) => ! str_contains($rutas, "->name('{$ruta}')"),
    ));

    expect($huerfanas)->toBe([]);
});

it('no siembra Auxiliares ni Equipos y los da de baja del menú', function () use ($raiz) {
    $codigo = (string) file_get_contents($raiz.'/database/seeders/Catalogo/SecMenuSeeder.php');

    expect($codigo)
        ->not->toContain("'auxiliares', 'support_agent'")
        ->not->toContain("'equipos_flota', 'groups'")
        ->toContain("'menu.proveedores.items.auxiliares'")
        ->toContain("'menu.proveedores.items.equipos_flota'")
        ->toContain("'menu.catalogos.label'");
});

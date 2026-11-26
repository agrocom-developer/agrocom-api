<?php

/*
 * Fija el árbol del menú del panel (docs/especificacion/marketplace.md §10.4):
 * un acceso directo (Inicio → tablero) y cinco módulos —Mercado, Proveedores,
 * Atención, Seguridad y Sistema— con sus ítems en orden (15 en total). Lee el código del `SecMenuSeeder` (única
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

it('siembra los módulos con sus ítems en este orden', function () use ($raiz) {
    expect(menuSembrado($raiz))->toBe([
        'solicitudes' => ['metricas', 'solicitudes', 'calificaciones'],
        'proveedores' => ['proveedores', 'pilotos', 'drones_flota', 'documentos'],
        'atencion' => ['clientes', 'reclamos'],
        'seguridad' => ['usuarios', 'roles', 'bitacora'],
        'sistema' => ['organizacion', 'avisos', 'configuracion'],
    ]);
});

it('el menú tiene 15 ítems y Zonas ya no está', function () use ($raiz) {
    $total = array_sum(array_map('count', menuSembrado($raiz)));
    expect($total)->toBe(15);

    $codigo = (string) file_get_contents($raiz.'/database/seeders/Catalogo/SecMenuSeeder.php');
    expect($codigo)
        ->not->toContain("'zonas', 'pin_drop'")
        ->toContain("'menu.sistema.items.zonas'");
});

it('los ítems tienen una frase corta que se entiende sola, sin repetir el módulo ni palabras sueltas', function () use ($raiz) {
    /** @var array<string, array<string, mixed>> $lang */
    $lang = require $raiz.'/lang/es/menu.php';

    expect($lang['proveedores']['label'])->toBe('Proveedores')
        ->and($lang['proveedores']['items']['proveedores'])->toBe('Cuentas de proveedores');

    foreach (['solicitudes', 'proveedores', 'atencion', 'seguridad', 'sistema'] as $modulo) {
        foreach ($lang[$modulo]['items'] as $clave => $etiqueta) {
            $palabras = count(preg_split('/\s+/', $etiqueta));
            // Caben en el drawer del móvil: de dos a cuatro palabras, y nunca el nombre del módulo solo.
            expect($palabras)->toBeBetween(2, 4);
            expect($etiqueta)->not->toBe($lang[$modulo]['label']);
        }
    }
});

it('ordena el riel: inicio, mercado, proveedores, atención, seguridad, sistema', function () use ($raiz) {
    $codigo = (string) file_get_contents($raiz.'/database/seeders/Catalogo/SecMenuSeeder.php');

    preg_match_all('/\$this->modulo\(\'(\w+)\', \'\w+\', (\d+)\)/', $codigo, $m, PREG_SET_ORDER);
    $porOrden = [];
    foreach ($m as [, $clave, $orden]) {
        $porOrden[(int) $orden] = $clave;
    }
    ksort($porOrden);

    expect(array_values($porOrden))->toBe(['solicitudes', 'proveedores', 'atencion', 'seguridad', 'sistema']);

    // Inicio va primero y no tiene hijos: es un acceso directo al tablero.
    expect($codigo)->toContain("\$this->acceso('inicio', 'home', 0, ruta: 'panel.dashboard'");
});

it('cada ítem del menú tiene su texto y su módulo tiene etiqueta y descripción', function () use ($raiz) {
    /** @var array<string, array<string, mixed>> $lang */
    $lang = require $raiz.'/lang/es/menu.php';
    $faltan = [];

    foreach (['label', 'descripcion'] as $campo) {
        if (! isset($lang['inicio'][$campo])) {
            $faltan[] = "menu.inicio.{$campo}";
        }
    }

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

it('no siembra Auxiliares, Equipos ni Soporte y da de baja lo que ya no va', function () use ($raiz) {
    $codigo = (string) file_get_contents($raiz.'/database/seeders/Catalogo/SecMenuSeeder.php');

    expect($codigo)
        ->not->toContain("'auxiliares', 'support_agent'")
        ->not->toContain("'equipos_flota', 'groups'")
        ->toContain("'menu.proveedores.items.auxiliares'")
        ->toContain("'menu.proveedores.items.equipos_flota'")
        ->toContain("'menu.catalogos.label'")
        ->toContain("'menu.solicitudes.items.tablero'")
        ->toContain("'menu.seguridad.items.dispositivos'")
        ->toContain("'menu.sistema.items.versiones_apk'");
    expect($codigo)->not->toContain("'soporte'");
});

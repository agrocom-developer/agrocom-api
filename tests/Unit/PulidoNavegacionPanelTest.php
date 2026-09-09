<?php

/*
 * Cuatro reglas del sistema de diseño del panel que hasta ahora dependían de
 * que quien escribiera la pantalla se acordara. Las tres primeras se
 * rompieron a la vez y solo se vieron en una grabación de pantalla del
 * usuario navegando el menú (7/9/2026) — ninguna hacía fallar un test,
 * ninguna hacía fallar la cascada, y las tres afeaban cada navegación del
 * panel:
 *
 * 1. Título de pantalla sin la receta común: un `<h1>` suelto hereda el peso
 *    por defecto de Bootstrap y se lee como OTRA tipografía al lado de las
 *    pantallas que sí pasan por `organisms/page-header` (§9 regla 1 de
 *    docs/diseno/sistema_diseno_panel.md).
 * 2. `@view-transition` en el bundle compartido: es una regla de DOCUMENTO,
 *    no se acota por selector, y aplicaba a toda navegación same-origin —
 *    incluido cada clic del menú, que no la pidió nunca.
 * 3. Ícono sin caja reservada: Material Symbols declara `font-display: block`
 *    y hasta que aplica el navegador maqueta la LIGADURA como texto corriente
 *    ("calendar_month") en la fuente de respaldo. Ese ancho fantasma empujaba
 *    el `min-content` de `module-sidebar` por encima de su `flex: 0 0 252px`
 *    y el panel entero saltaba a la derecha y volvía en cada navegación.
 * 4. Campo en una fila de controles con su margen de apilado puesto: la
 *    barra de filtros anulaba el de `.ag-input` por nombre, así que cuando
 *    la tarea 76 migró los 70 selects del panel al átomo `atoms/select`
 *    —otra clase de raíz, `.ag-select`— el botón "Filtrar" volvió a caer
 *    16px por debajo del campo en las 29 pantallas con filtros a la vez
 *    (reportado con captura por el usuario el 8/9/2026). Esta cuarta
 *    compuerta se descubre sola: cualquier átomo de campo nuevo que declare
 *    `margin-bottom` en su regla raíz tiene que estar anulado en
 *    `components/filter-bar.css` o el test falla.
 */

$raizProyecto = dirname(__DIR__, 2);

/**
 * Blades de PANTALLA del panel y del portal — los de `Views/pdf/` no cuentan:
 * son plantillas de DomPDF con su propia hoja de estilo embebida, no
 * comparten ni el bundle ni el catálogo de componentes.
 *
 * Se descubre recorriendo el árbol para que una carpeta de vistas nueva quede
 * protegida sin editar este archivo (mismo criterio que TokensColorTest).
 *
 * @return list<string>
 */
function pantallasBladeDelPanel(string $raizProyecto): array
{
    $archivos = [];

    foreach (['resources/views', 'app/Dominios'] as $raiz) {
        $rutaRaiz = $raizProyecto.'/'.$raiz;

        if (! is_dir($rutaRaiz)) {
            continue;
        }

        /** @var iterable<SplFileInfo> $iterador */
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rutaRaiz, FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $archivo) {
            $relativa = substr($archivo->getPathname(), strlen($raizProyecto) + 1);

            if (! $archivo->isFile() || ! str_ends_with($relativa, '.blade.php')) {
                continue;
            }

            if (str_contains($relativa, '/Views/pdf/')) {
                continue;
            }

            $archivos[] = $relativa;
        }
    }

    sort($archivos);

    return $archivos;
}

// Red de seguridad del descubrimiento: si esto falla, las reglas de abajo no
// protegen nada (p. ej. porque se movió una carpeta de vistas).
test('el descubrimiento encuentra las pantallas Blade del panel', function () use ($raizProyecto) {
    expect(pantallasBladeDelPanel($raizProyecto))
        ->toContain('app/Dominios/Operaciones/Infraestructura/Http/Views/pages/trabajos/index.blade.php')
        ->toContain('resources/views/components/organisms/page-header.blade.php')
        ->not->toContain('app/Dominios/Operaciones/Infraestructura/Http/Views/pdf/acta.blade.php');
});

test('ningún título de pantalla es un <h1> sin clase (hereda el peso de Bootstrap)', function () use ($raizProyecto) {
    $sueltos = [];

    foreach (pantallasBladeDelPanel($raizProyecto) as $relativa) {
        $contenido = file_get_contents($raizProyecto.'/'.$relativa);

        if ($contenido === false) {
            continue;
        }

        foreach (explode("\n", $contenido) as $numero => $linea) {
            // `<h1>` o `<h1 ...>` sin un atributo `class` que aplique una
            // receta de título. Lo que se busca NO es prohibir el h1 —es el
            // encabezado correcto— sino que su tipografía quede al azar.
            if (preg_match('/<h1(?![^>]*\bclass=)/', $linea) === 1) {
                $sueltos[] = sprintf('%s:%d: %s', $relativa, $numero + 1, trim($linea));
            }
        }
    }

    expect($sueltos)->toBe([], "El título de una pantalla va por <x-organisms.page-header :title=\"…\" />.\n".implode("\n", $sueltos));
});

test('@view-transition no vive en el bundle que carga todo el panel', function () use ($raizProyecto) {
    $fugas = [];

    /** @var iterable<SplFileInfo> $iterador */
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raizProyecto.'/resources/css', FilesystemIterator::SKIP_DOTS));

    foreach ($iterador as $archivo) {
        $relativa = substr($archivo->getPathname(), strlen($raizProyecto) + 1);

        if (! $archivo->isFile() || ! str_ends_with($relativa, '.css')) {
            continue;
        }

        // Única hoja que puede declararla: la que se carga aparte, y solo
        // desde las pantallas del flujo de autenticación que la pidieron.
        if ($relativa === 'resources/css/transicion-vista.css') {
            continue;
        }

        $contenido = file_get_contents($raizProyecto.'/'.$relativa);

        if ($contenido === false) {
            continue;
        }

        // Sin comentarios: `app.css` deja anotado por qué la regla NO está
        // ahí, y esa nota nombra la regla. Es documentación, no una fuga.
        $sinComentarios = preg_replace('~/\*.*?\*/~s', '', $contenido) ?? $contenido;

        if (str_contains($sinComentarios, '@view-transition')) {
            $fugas[] = $relativa;
        }
    }

    expect($fugas)->toBe([], 'La transición entre navegaciones va en resources/css/transicion-vista.css, que se incluye por pantalla; en el bundle aplica a TODO clic del menú.');
    expect(file_get_contents($raizProyecto.'/resources/css/transicion-vista.css'))->toContain('@view-transition');
});

test('el átomo icon reserva su caja para que la ligadura no mueva el layout', function () use ($raizProyecto) {
    $iconCss = file_get_contents($raizProyecto.'/resources/css/components/icon.css');

    // 1em es el avance EXACTO de todos los glifos de la fuente (960/960
    // unidades): reservarlo no recorta ningún ícono y deja fuera del layout
    // al texto de respaldo de la ligadura mientras la fuente carga.
    expect($iconCss)
        ->toContain('width: 1em')
        ->toContain('display: inline-block');
});

test('la barra de filtros anula el margen de apilado de todo átomo de campo', function () use ($raizProyecto) {
    $barraFiltros = file_get_contents($raizProyecto.'/resources/css/components/filter-bar.css');
    $sinCubrir = [];

    foreach (glob($raizProyecto.'/resources/views/components/atoms/*.blade.php') ?: [] as $blade) {
        $hoja = $raizProyecto.'/resources/css/components/'.basename($blade, '.blade.php').'.css';

        if (! is_file($hoja)) {
            continue;
        }

        $contenido = file_get_contents($hoja);

        if ($contenido === false) {
            continue;
        }

        // Solo las reglas RAÍZ del átomo (`.ag-select { … }`), nunca un
        // elemento BEM (`.ag-select__listbox`): el margen que importa es el
        // que separa un campo del siguiente en un formulario apilado, y es
        // el que sobra cuando el campo está en línea con un botón.
        preg_match_all('~^\.(ag-[a-z-]+) \{([^}]*)\}~m', $contenido, $reglas, PREG_SET_ORDER);

        foreach ($reglas as $regla) {
            if (! str_contains($regla[2], 'margin-bottom')) {
                continue;
            }

            if (! str_contains($barraFiltros, ".ag-filtros .{$regla[1]},") && ! str_contains($barraFiltros, ".ag-filtros .{$regla[1]} {")) {
                $sinCubrir[] = $regla[1];
            }
        }
    }

    expect($sinCubrir)->toBe([], 'Estos átomos de campo llevan margen de apilado y `components/filter-bar.css` no lo anula: dentro de una barra de filtros van a empujar el botón por debajo del campo. Sumalos a la regla de `margin-bottom: 0`.');
});

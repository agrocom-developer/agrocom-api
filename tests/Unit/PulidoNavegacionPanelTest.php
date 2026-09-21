<?php

/*
 * Cinco reglas del sistema de diseño del panel que hasta ahora dependían de
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
 * 5. Listado con menos columnas declaradas que celdas por fila: CSS Grid no
 *    avisa, ABRE UNA FILA IMPLÍCITA. En `campanias/index` las acciones
 *    ("Editar", "Cerrar") caían así debajo del nombre del cliente, en la
 *    primera columna y con la fila al doble de alto — reportado con captura
 *    por el dueño el 9/9/2026, con la pantalla mergeada y en verde desde la
 *    tarea 69. Ningún test lo veía: el HTML era correcto, el desajuste
 *    estaba entre el Blade y su hoja.
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
        ->toContain('app/Dominios/Operaciones/Infraestructura/Http/Views/pages/trabajos/show.blade.php')
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

test('cada listado declara tantas columnas de grid como celdas tiene su encabezado', function () use ($raizProyecto) {
    // Columnas declaradas por hoja de página. Se saltean las plantillas
    // calculadas (`repeat()`/`minmax()`): son galerías de tarjetas que se
    // acomodan solas al ancho, no tablas con una celda por columna.
    $columnasPorPagina = [];

    foreach (glob($raizProyecto.'/resources/css/pages/*.css') ?: [] as $hoja) {
        $contenido = file_get_contents($hoja);

        if ($contenido === false || preg_match('/grid-template-columns:\s*([^;]+);/', $contenido, $coincidencia) !== 1) {
            continue;
        }

        $declaracion = trim($coincidencia[1]);

        if (str_contains($declaracion, 'repeat') || str_contains($declaracion, 'minmax')) {
            continue;
        }

        $columnasPorPagina[basename($hoja, '.css')] = [count(preg_split('/\s+/', $declaracion) ?: []), $declaracion];
    }

    $desajustes = [];

    foreach (pantallasBladeDelPanel($raizProyecto) as $relativa) {
        $contenido = file_get_contents($raizProyecto.'/'.$relativa);

        if ($contenido === false) {
            continue;
        }

        preg_match_all('~class="[^"]*ag-([a-z0-9-]+)__head"[^>]*>(.*?)</div>~s', $contenido, $encabezados, PREG_SET_ORDER);

        foreach ($encabezados as $encabezado) {
            [$clave, $cuerpo] = [$encabezado[1], $encabezado[2]];

            if (! isset($columnasPorPagina[$clave])) {
                continue;
            }

            [$columnas, $declaracion] = $columnasPorPagina[$clave];
            $celdas = substr_count($cuerpo, 'role="columnheader"');

            if ($celdas !== $columnas) {
                $desajustes[] = sprintf(
                    '%s: el encabezado tiene %d celdas y pages/%s.css declara %d columnas (%s)',
                    $relativa,
                    $celdas,
                    $clave,
                    $columnas,
                    $declaracion,
                );
            }
        }
    }

    expect($desajustes)->toBe([], "Un listado con menos columnas que celdas no falla: CSS Grid abre una fila implícita y las últimas celdas —casi siempre las ACCIONES— caen debajo de la primera columna.\n".implode("\n", $desajustes));
});

test('ninguna página redeclara su propio grid de campos de formulario', function () use ($raizProyecto) {
    // El grid de dos columnas de un formulario vive en UN solo lugar:
    // `.ag-form-section__body` (components/form-section.css), con el techo
    // en 50% que lo cap a dos columnas aun en pantallas anchas
    // (docs/diseno/guia_pantalla_panel.md §6.3, regla 2). Hasta el
    // 14/9/2026, cuatro páginas (`clientes`, `contratos`, `siembra`,
    // `campos`) redeclaraban ese mismo grid a mano para su fila repetible
    // (contactos, ventanas, lotes) — cada una citando a la anterior como
    // molde ("mismo patrón que clientes/_contacto-fila.blade.php") en vez de
    // compartir la clase, así que cuando el componente ganó el techo (PR
    // #180) las cuatro copias quedaron atrás. La corrección no es que cada
    // copia declare el techo también: es que la fila comparta la clase
    // `ag-form-section__body` por composición (ver el test siguiente) y la
    // página deje de declarar el grid. Por eso esta regla es más estricta
    // que "le falta el techo": ninguna página declara su propio
    // `auto-fit`+`minmax(` para un selector con "form" en el nombre, tenga
    // o no el techo — la única declaración legítima es la del componente.
    $duplicados = [];

    foreach (glob($raizProyecto.'/resources/css/pages/*.css') ?: [] as $hoja) {
        $relativa = substr($hoja, strlen($raizProyecto) + 1);
        $contenido = file_get_contents($hoja);

        if ($contenido === false) {
            continue;
        }

        // Solo selectores con "form" en el nombre: una galería de tarjetas o
        // un grid de estadísticas (`totales-grid`, `plan-grid`) puede querer
        // tantas columnas como entren a propósito — esta regla es de campos
        // de formulario, no de todo `auto-fit`.
        preg_match_all('~\.([a-zA-Z0-9_-]*form[a-zA-Z0-9_-]*)\s*\{([^}]*)\}~', $contenido, $reglas, PREG_SET_ORDER);

        foreach ($reglas as $regla) {
            [$selector, $cuerpo] = [$regla[1], $regla[2]];

            if (str_contains($cuerpo, 'auto-fit') && str_contains($cuerpo, 'minmax(')) {
                $duplicados[] = "{$relativa}: .{$selector}";
            }
        }
    }

    expect($duplicados)->toBe([], "Estas páginas redeclaran su propio grid de auto-fit/minmax para un elemento de formulario en vez de compartir la clase `ag-form-section__body` (components/form-section.css) — el sitio único de esa decisión.\n".implode("\n", $duplicados));
});

test('toda fila repetible de un formulario comparte la clase ag-form-section__body', function () use ($raizProyecto) {
    // Contraparte del test anterior: cómo SÍ se logra el grid de dos
    // columnas en una fila repetible (contactos de cliente, ventanas de
    // contrato, lotes de campo/siembra) sin redeclararlo — por composición,
    // agregando la clase del componente al elemento raíz de la fila además
    // de su propia clase BEM. La convención de nombre del partial ya existe
    // en el árbol (`_contacto-fila.blade.php`, `_ventana-fila.blade.php`,
    // `_siembra-fila.blade.php`, `_lote-fila.blade.php`), así que el
    // descubrimiento no depende de una lista a mano.
    $sinClase = [];

    /** @var iterable<SplFileInfo> $iterador */
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raizProyecto.'/app/Dominios', FilesystemIterator::SKIP_DOTS));

    foreach ($iterador as $archivo) {
        $relativa = substr($archivo->getPathname(), strlen($raizProyecto) + 1);

        if (! $archivo->isFile() || ! preg_match('~/pages/.*/_[a-z-]+-fila\.blade\.php$~', $relativa)) {
            continue;
        }

        $contenido = file_get_contents($raizProyecto.'/'.$relativa);

        if ($contenido === false || preg_match('~<div\s+class="([^"]*)"~', $contenido, $raiz) !== 1) {
            continue;
        }

        if (! in_array('ag-form-section__body', explode(' ', $raiz[1]), true)) {
            $sinClase[] = $relativa;
        }
    }

    expect($sinClase)->toBe([], "Estas filas repetibles no llevan la clase ag-form-section__body en su elemento raíz, así que no comparten el grid de dos columnas del componente (van a necesitar su propio grid duplicado, que es justo lo que esta convención evita).\n".implode("\n", $sinClase));
});

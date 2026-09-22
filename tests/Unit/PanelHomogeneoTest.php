<?php

/*
 * Compuerta de la homogeneización del panel (tarea 111; alcance y receta en
 * docs/gestion/plan_homogeneizacion_panel.md).
 *
 * Convierte «quedó igual que Comercial» en un comando con exit code: recorre
 * los listados (`index`) y los formularios (`_formulario`, o `create`/`edit`
 * cuando no hay partial) de cada módulo y exige el patrón de las pantallas de
 * referencia. Lee archivos; no toca HTTP ni base (CLAUDE.md §Testing).
 *
 * Dos listas con distinto dueño:
 *
 * - Las EXCLUSIONES permanentes viven acá (panelHomogeneoExcluida): son las del
 *   plan §1.1 y no cambian entre tareas.
 * - Los PENDIENTES viven en docs/diseno/panel_homogeneo_pendientes.txt: las
 *   pantallas que todavía no llegaron al patrón. El test las perdona, pero no
 *   deja que la lista mienta: falla si una ruta no existe o si esa pantalla ya
 *   cumple todo (hay que sacarla). Así cada tarea solo puede achicar la lista,
 *   y achicarla vuelve al test más exigente, nunca menos.
 *
 * Las reglas se evalúan sobre la vista con sus parciales `@include` expandidos
 * (las acciones de una fila viven a menudo en `_orden-acciones`) y sin
 * comentarios: una `{{-- … --}}` que explica por qué ya no se usa `confirm()`
 * no es una violación.
 */

$raizProyecto = dirname(__DIR__, 2);

/**
 * Todo `.blade.php` bajo `app/Dominios/<Módulo>/Infraestructura/Http/Views/pages`.
 * `ruta` es la forma en que se nombra en la lista de pendientes:
 * `<Módulo>/<carpeta>/<archivo>` (p. ej. `Finanzas/gastos/index.blade.php`).
 *
 * @return list<array{ruta: string, modulo: string, directorio: string, archivo: string, absoluta: string}>
 */
function panelHomogeneoVistas(string $raiz): array
{
    $vistas = [];

    foreach (glob($raiz.'/app/Dominios/*/Infraestructura/Http/Views/pages') ?: [] as $paginas) {
        $modulo = basename(dirname($paginas, 4));

        /** @var iterable<SplFileInfo> $iterador */
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($paginas, FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $archivo) {
            if (! $archivo->isFile() || ! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }

            $enPaginas = substr($archivo->getPathname(), strlen($paginas) + 1);
            $carpeta = dirname($enPaginas);

            $vistas[] = [
                'ruta' => $modulo.'/'.$enPaginas,
                'modulo' => $modulo,
                'directorio' => $carpeta === '.' ? '' : $carpeta,
                'archivo' => $archivo->getFilename(),
                'absoluta' => $archivo->getPathname(),
            ];
        }
    }

    usort($vistas, fn (array $a, array $b): int => strcmp($a['ruta'], $b['ruta']));

    return $vistas;
}

/**
 * Exclusiones permanentes (plan §1.1): las cuatro pantallas que el dueño dejó
 * afuera por nombre, lo que no es listado ni formulario de un objeto, y el
 * portal del cliente (otro público, otro layout, el scoping de la invariante
 * 5). Las páginas de detalle (`show`) NO están acá desde el 22/9/2026 (tarea
 * 124): el arquetipo Detalle se adopta en todo el sistema — ver
 * `panelHomogeneoPantallas()` y `panelHomogeneoViolacionesFicha()`.
 *
 * @param  array{ruta: string, modulo: string, directorio: string, archivo: string, absoluta: string}  $vista
 */
function panelHomogeneoExcluida(array $vista): bool
{
    if ($vista['modulo'] === 'Portal') {
        return true;
    }

    if ($vista['modulo'] !== 'Seguridad') {
        return false;
    }

    $segmento = $vista['directorio'] === '' ? $vista['archivo'] : explode('/', $vista['directorio'])[0];

    return str_starts_with($segmento, 'dashboard')
        || in_array($segmento, ['roles', 'organizacion', 'bitacora', 'perfil', 'configuracion', 'busqueda'], true);
}

/**
 * Fichas que no se llaman `show.blade.php` (plan §8): hoy solo
 * `Personal/personas/desempeno.blade.php`, la ficha de desempeño de una
 * persona — mismo papel que un `show`, nombre de ruta propio
 * (`panel.personas.desempenio`). Una lista explícita, no una adivinanza por
 * patrón de nombre: agregar acá una pantalla nueva es una decisión, no un
 * efecto colateral de cómo se llame el archivo.
 *
 * @return list<string>
 */
function panelHomogeneoFichasSinNombreShow(): array
{
    return ['Personal/personas/desempeno.blade.php'];
}

/**
 * Las pantallas que este test evalúa: cada `index` es un listado; el formulario
 * de un directorio es su `_formulario`, o —si no hay partial— su `create` y su
 * `edit` por separado; cada `show` (o lo que liste
 * `panelHomogeneoFichasSinNombreShow()`) es una ficha del arquetipo Detalle.
 *
 * @return list<array{ruta: string, modulo: string, directorio: string, archivo: string, absoluta: string, tipo: 'listado'|'formulario'|'ficha'}>
 */
function panelHomogeneoPantallas(string $raiz): array
{
    static $cache = [];

    if (isset($cache[$raiz])) {
        return $cache[$raiz];
    }

    $vistas = array_values(array_filter(panelHomogeneoVistas($raiz), fn (array $vista): bool => ! panelHomogeneoExcluida($vista)));

    $conPartial = [];
    foreach ($vistas as $vista) {
        if ($vista['archivo'] === '_formulario.blade.php') {
            $conPartial[$vista['modulo'].'/'.$vista['directorio']] = true;
        }
    }

    $fichasSinNombreShow = panelHomogeneoFichasSinNombreShow();

    $pantallas = [];
    foreach ($vistas as $vista) {
        $sinPartial = ! isset($conPartial[$vista['modulo'].'/'.$vista['directorio']]);

        $tipo = match (true) {
            $vista['archivo'] === 'index.blade.php' => 'listado',
            $vista['archivo'] === '_formulario.blade.php' => 'formulario',
            $sinPartial && in_array($vista['archivo'], ['create.blade.php', 'edit.blade.php'], true) => 'formulario',
            $vista['archivo'] === 'show.blade.php' => 'ficha',
            in_array($vista['ruta'], $fichasSinNombreShow, true) => 'ficha',
            default => null,
        };

        if ($tipo !== null) {
            $pantallas[] = $vista + ['tipo' => $tipo];
        }
    }

    return $cache[$raiz] = $pantallas;
}

/**
 * Máquinas de estado (`Dominio/**\/Transiciones*.php`) cuyo objeto tiene ficha
 * de edición: la ficha muestra los pasos con `step-arrow`. Tabla explícita
 * clase → `<Módulo>/<carpeta>` de sus vistas, no adivinada por nombres.
 *
 * @return array<string, string>
 */
function panelHomogeneoMaquinasConFicha(): array
{
    return [
        'TransicionesCampania' => 'Campania/campanias',
        'TransicionesContrato' => 'Comercial/contratos',
        'TransicionesOrden' => 'Operaciones/ordenes',
        'TransicionesEstadia' => 'Operaciones/estadias',
        'TransicionesEquipoTrabajo' => 'Personal/cuadrillas',
        'TransicionesOrdenMantenimiento' => 'Mantenimiento/ordenes',
        'TransicionesTrabajo' => 'Operaciones/trabajos',
    ];
}

/**
 * Máquinas sin ficha de edición: no llevan pasos en un formulario (plan §2.3).
 *
 * @return array<string, string>
 */
function panelHomogeneoMaquinasSinFicha(): array
{
    return [
        'TransicionesPlanilla' => 'listado + detalle; solo badge y acciones de fila con el tono del estado',
        'TransicionesRendicion' => 'listado + detalle; solo badge y acciones de fila con el tono del estado',
        'TransicionesVersionApk' => 'solo listado; badge y acciones de fila con el tono del estado',
        'TransicionesActa' => 'se valida desde pantallas propias',
        'TransicionesSesion' => 'se valida desde pantallas propias',
    ];
}

/**
 * Las `Transiciones*.php` que hay hoy en `app/Dominios/<Módulo>/Dominio/`.
 *
 * @return array<string, string> clase → módulo
 */
function panelHomogeneoMaquinasExistentes(string $raiz): array
{
    $maquinas = [];

    /** @var iterable<SplFileInfo> $iterador */
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.'/app/Dominios', FilesystemIterator::SKIP_DOTS));

    foreach ($iterador as $archivo) {
        $ruta = $archivo->getPathname();

        if (preg_match('~/app/Dominios/([^/]+)/Dominio/(?:.*/)?(Transiciones[A-Za-z]+)\.php$~', $ruta, $coincidencia) === 1) {
            $maquinas[$coincidencia[2]] = $coincidencia[1];
        }
    }

    ksort($maquinas);

    return $maquinas;
}

/**
 * Directorios cuyo LISTADO y/o FORMULARIO es la pantalla de referencia (plan
 * §1). Un directorio puede ser referencia de esto sin serlo de su ficha
 * (arquetipo Detalle) — `Personal/cuadrillas` es la referencia de "tabla de
 * detalle con paginación dentro del formulario", pero su `show.blade.php`
 * todavía no sigue el arquetipo Detalle (tarea 125): ver
 * `panelHomogeneoReferenciasFicha()`, que es la lista aparte para eso.
 *
 * @return list<string>
 */
function panelHomogeneoReferencias(): array
{
    return [
        'Campania/campanias',
        'Comercial/clientes',
        'Comercial/propiedades',
        'Comercial/lotes',
        'Comercial/contratos',
        'Operaciones/ordenes',
        'Operaciones/ordenes-trabajo',
        'Operaciones/estadias',
        'Personal/cuadrillas',
    ];
}

/**
 * Directorios cuya FICHA (arquetipo Detalle, plan §8) es la referencia: hoy
 * las dos que ya lo seguían antes de la decisión del 22/9/2026 — la orden de
 * aplicación (17/9/2026) y la orden de trabajo (21/9/2026). Ninguna ficha de
 * estos directorios puede esconderse en la lista de pendientes.
 *
 * @return list<string>
 */
function panelHomogeneoReferenciasFicha(): array
{
    return [
        'Operaciones/ordenes',
        'Operaciones/ordenes-trabajo',
    ];
}

/**
 * Rutas de la lista de pendientes, una por línea; `#` abre un comentario.
 *
 * @return list<string>
 */
function panelHomogeneoPendientes(string $raiz): array
{
    $contenido = file_get_contents($raiz.'/docs/diseno/panel_homogeneo_pendientes.txt');

    if ($contenido === false) {
        return [];
    }

    $rutas = [];

    foreach (explode("\n", $contenido) as $linea) {
        $ruta = trim(explode('#', $linea, 2)[0]);

        if ($ruta !== '') {
            $rutas[] = $ruta;
        }
    }

    return $rutas;
}

/**
 * Reemplaza cada comentario por sus saltos de línea, para que el número de
 * línea de lo que queda siga siendo el del archivo original.
 */
function panelHomogeneoSinComentarios(string $contenido, bool $js = false): string
{
    $patrones = $js
        ? ['~/\*.*?\*/~s', '~(?<![:\w"\'])//[^\n]*~']
        : ['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s'];

    foreach ($patrones as $patron) {
        $contenido = preg_replace_callback($patron, fn (array $m): string => str_repeat("\n", substr_count($m[0], "\n")), $contenido) ?? $contenido;
    }

    return $contenido;
}

/**
 * Índice del `)` que cierra el `(` en `$abre`, saltando el contenido entre
 * comillas.
 */
function panelHomogeneoCierraParentesis(string $texto, int $abre): int
{
    $profundidad = 0;
    $comilla = null;
    $largo = strlen($texto);

    for ($i = $abre; $i < $largo; $i++) {
        $caracter = $texto[$i];

        if ($comilla !== null) {
            if ($caracter === '\\') {
                $i++;
            } elseif ($caracter === $comilla) {
                $comilla = null;
            }

            continue;
        }

        if ($caracter === '"' || $caracter === "'") {
            $comilla = $caracter;
        } elseif ($caracter === '(') {
            $profundidad++;
        } elseif ($caracter === ')' && --$profundidad === 0) {
            return $i;
        }
    }

    return $largo - 1;
}

/**
 * La vista con sus parciales `@include('<módulo>::pages.…')` expandidos en el
 * lugar de la directiva, y la lista de archivos que la componen.
 *
 * @return array{contenido: string, archivos: array<string, string>}
 */
function panelHomogeneoVista(string $raiz, string $absoluta, int $profundidad = 0): array
{
    $relativa = substr($absoluta, strlen($raiz) + 1);
    $crudo = file_get_contents($absoluta);
    $blade = panelHomogeneoSinComentarios($crudo === false ? '' : $crudo);
    $archivos = [$relativa => $blade];

    if ($profundidad >= 4) {
        return ['contenido' => $blade, 'archivos' => $archivos];
    }

    $contenido = '';
    $cursor = 0;

    while (preg_match('/@include\(\s*([\'"])([\w-]+)::pages\.([\w.-]+)\1/', $blade, $coincidencia, PREG_OFFSET_CAPTURE, $cursor) === 1) {
        $inicio = $coincidencia[0][1];
        $fin = panelHomogeneoCierraParentesis($blade, $inicio + strlen('@include'));
        $parcial = $raiz.'/app/Dominios/'.ucfirst($coincidencia[2][0]).'/Infraestructura/Http/Views/pages/'
            .str_replace('.', '/', $coincidencia[3][0]).'.blade.php';

        $contenido .= substr($blade, $cursor, $inicio - $cursor);

        if (is_file($parcial)) {
            $anidado = panelHomogeneoVista($raiz, $parcial, $profundidad + 1);
            $contenido .= "\n".$anidado['contenido']."\n";
            $archivos += $anidado['archivos'];
        }

        $cursor = $fin + 1;
    }

    return ['contenido' => $contenido.substr($blade, $cursor), 'archivos' => $archivos];
}

/**
 * Bloques `<componente …>…</componente>` (o autocerrados) de un Blade. La
 * etiqueta de apertura se lee respetando comillas y sin cerrarse en un `->` o
 * un `=>`: los atributos ligados llevan PHP (`:options="collect($x)->map(…)"`).
 * El cierre es el primer `</componente>` que sigue: los componentes que se
 * buscan acá no se anidan consigo mismos.
 *
 * @return list<array{atributos: string, cuerpo: string|null, inicio: int, fin: int}>
 */
function panelHomogeneoBloques(string $blade, string $componente): array
{
    $bloques = [];
    $apertura = '<'.$componente;
    $largo = strlen($blade);
    $desde = 0;

    while (($inicio = strpos($blade, $apertura, $desde)) !== false) {
        $cursor = $inicio + strlen($apertura);
        $desde = $cursor;

        // Otro componente que empieza igual (`x-atoms.button-group`) no cuenta.
        if (! in_array($blade[$cursor] ?? '', [' ', "\n", "\r", "\t", '>', '/'], true)) {
            continue;
        }

        $comilla = null;
        $finApertura = null;

        for ($i = $cursor; $i < $largo; $i++) {
            $caracter = $blade[$i];

            if ($comilla !== null) {
                if ($caracter === $comilla) {
                    $comilla = null;
                }
            } elseif ($caracter === '"' || $caracter === "'") {
                $comilla = $caracter;
            } elseif ($caracter === '>' && ! in_array($blade[$i - 1], ['-', '='], true)) {
                $finApertura = $i;
                break;
            }
        }

        if ($finApertura === null) {
            continue;
        }

        $autocerrado = $blade[$finApertura - 1] === '/';
        $atributos = substr($blade, $cursor, $finApertura - $cursor - ($autocerrado ? 1 : 0));

        if ($autocerrado) {
            $bloques[] = ['atributos' => $atributos, 'cuerpo' => null, 'inicio' => $inicio, 'fin' => $finApertura + 1];
            $desde = $finApertura + 1;

            continue;
        }

        $cierre = strpos($blade, '</'.$componente.'>', $finApertura);
        $finCuerpo = $cierre === false ? $largo : $cierre;
        $fin = $cierre === false ? $largo : $cierre + strlen('</'.$componente.'>');

        $bloques[] = [
            'atributos' => $atributos,
            'cuerpo' => substr($blade, $finApertura + 1, $finCuerpo - $finApertura - 1),
            'inicio' => $inicio,
            'fin' => $fin,
        ];
        $desde = $fin;
    }

    return $bloques;
}

/** El `$blade` sin los bloques de `$componente`. */
function panelHomogeneoSinBloques(string $blade, string $componente): string
{
    $resto = '';
    $cursor = 0;

    foreach (panelHomogeneoBloques($blade, $componente) as $bloque) {
        $resto .= substr($blade, $cursor, $bloque['inicio'] - $cursor);
        $cursor = $bloque['fin'];
    }

    return $resto.substr($blade, $cursor);
}

/** Si el Blade usa el componente (con o sin cuerpo). */
function panelHomogeneoUsa(string $blade, string $componente): bool
{
    return preg_match('/<'.preg_quote($componente, '/').'(?![\w.-])/', $blade) === 1;
}

/** Valor del atributo literal `nombre="…"`; `:nombre="…"` y `xxx-nombre="…"` no cuentan. */
function panelHomogeneoAtributo(string $atributos, string $nombre): ?string
{
    return preg_match('/(?<![\w:.-])'.preg_quote($nombre, '/').'="([^"]*)"/', $atributos, $m) === 1 ? $m[1] : null;
}

/** Si el atributo llega ligado (`:nombre="…"`), es decir, calculado en tiempo de render. */
function panelHomogeneoAtributoLigado(string $atributos, string $nombre): bool
{
    return preg_match('/(?<![\w-]):'.preg_quote($nombre, '/').'=/', $atributos) === 1;
}

/** Si el listado pinta una tabla, con o sin el componente del catálogo. */
function panelHomogeneoPintaTabla(string $blade): bool
{
    return preg_match('/<x-molecules\.index-table(?![\w.-])|role="table"|<table\b|role="columnheader"/', $blade) === 1;
}

/**
 * `confirm()` nativo del navegador en un Blade o en un JS ya sin comentarios.
 *
 * @return list<int> líneas
 */
function panelHomogeneoConfirmNativo(string $contenido): array
{
    $lineas = [];

    foreach (explode("\n", $contenido) as $numero => $linea) {
        if (preg_match('/(?<![\w.>-])(?:window\.)?confirm\s*\(/', $linea) === 1) {
            $lineas[] = $numero + 1;
        }
    }

    return $lineas;
}

/**
 * Violaciones por `confirm()` nativo: en cada archivo de la vista y en el JS
 * de página que la carga (`resources/js/pages/<carpeta>.js`, o el que el Blade
 * enlaza por `js/pages/…`).
 *
 * @param  array{ruta: string, modulo: string, directorio: string, archivo: string, absoluta: string}  $pantalla
 * @param  array<string, string>  $archivos
 * @return list<string>
 */
function panelHomogeneoViolacionesConfirm(string $raiz, array $pantalla, array $archivos, bool $incluirJs): array
{
    $violaciones = [];

    foreach ($archivos as $relativa => $blade) {
        foreach (panelHomogeneoConfirmNativo($blade) as $linea) {
            $violaciones[] = sprintf('[confirm-nativo] %s:%d usa confirm() del navegador: reemplázalo por <x-molecules.confirm-button> (o info-modal si no confirma nada).', $relativa, $linea);
        }
    }

    if (! $incluirJs) {
        return $violaciones;
    }

    $scripts = ['resources/js/pages/'.basename($pantalla['directorio']).'.js'];

    if (preg_match_all('~js/pages/([\w-]+)\.js~', implode("\n", $archivos), $coincidencias) > 0) {
        foreach ($coincidencias[1] as $nombre) {
            $scripts[] = 'resources/js/pages/'.$nombre.'.js';
        }
    }

    foreach (array_unique($scripts) as $script) {
        if (! is_file($raiz.'/'.$script)) {
            continue;
        }

        $js = panelHomogeneoSinComentarios((string) file_get_contents($raiz.'/'.$script), true);

        foreach (panelHomogeneoConfirmNativo($js) as $linea) {
            $violaciones[] = sprintf('[confirm-nativo] %s:%d usa confirm() del navegador: reemplázalo por <x-molecules.confirm-button>.', $script, $linea);
        }
    }

    return $violaciones;
}

/**
 * Reglas de un listado (plan §3 y §4.2).
 *
 * @param  array{ruta: string, modulo: string, directorio: string, archivo: string, absoluta: string}  $pantalla
 * @return list<string>
 */
function panelHomogeneoViolacionesListado(string $raiz, array $pantalla): array
{
    $vista = panelHomogeneoVista($raiz, $pantalla['absoluta']);
    $blade = $vista['contenido'];
    $violaciones = [];

    $tablas = panelHomogeneoBloques($blade, 'x-molecules.index-table');

    if ($tablas === []) {
        $violaciones[] = '[index-table] pinta la tabla a mano: usa <x-molecules.index-table>.';
    }

    if (preg_match('/class="[^"]*\bag-filtros(?![\w-])/', $blade) === 1) {
        $violaciones[] = '[filtros-viejos] queda el <form class="ag-filtros"> anterior: usa <x-organisms.filter-panel> + <x-molecules.table-search>.';
    }

    $violaciones = [...$violaciones, ...panelHomogeneoViolacionesConfirm($raiz, $pantalla, $vista['archivos'], true)];

    // Todo botón de la fila va en row-actions. Los de un modal (el aviso que
    // enlaza a otra pantalla, el confirmar) son del modal, que vive en la celda.
    foreach ($tablas as $tabla) {
        $sinAcciones = $tabla['cuerpo'] ?? '';

        foreach (['x-organisms.row-actions', 'x-molecules.info-modal', 'x-molecules.confirm-modal'] as $componente) {
            $sinAcciones = panelHomogeneoSinBloques($sinAcciones, $componente);
        }

        $sueltos = count(panelHomogeneoBloques($sinAcciones, 'x-atoms.button'))
            + count(panelHomogeneoBloques($sinAcciones, 'x-molecules.confirm-button'))
            + preg_match_all('/<button\b/', $sinAcciones);

        if ($sueltos > 0) {
            $violaciones[] = sprintf('[row-actions] %d botón(es) de la fila fuera de <x-organisms.row-actions>.', $sueltos);
        }
    }

    $variantePorIcono = ['edit' => 'warning-outline', 'visibility' => 'info-outline', 'delete' => 'danger-outline'];

    foreach (panelHomogeneoBloques($blade, 'x-organisms.row-actions') as $acciones) {
        $cuerpo = $acciones['cuerpo'] ?? '';

        if (preg_match('/<form\b/', $cuerpo) === 1) {
            $violaciones[] = '[row-actions] hay un <form> dentro de <x-organisms.row-actions>: el organism repite su slot y el id se duplica; el form va fuera, en la celda.';
        }

        if (str_contains($cuerpo, 'x-molecules.confirm-modal')) {
            $violaciones[] = '[row-actions] hay un <x-molecules.confirm-modal> dentro de <x-organisms.row-actions>: el modal va fuera, en la celda.';
        }

        foreach (['x-atoms.button' => 'primary', 'x-molecules.confirm-button' => 'outline'] as $componente => $variantePorOmision) {
            foreach (panelHomogeneoBloques($cuerpo, $componente) as $boton) {
                $icono = panelHomogeneoAtributo($boton['atributos'], 'icon');
                $variante = panelHomogeneoAtributo($boton['atributos'], 'variant');
                $esperada = $variantePorIcono[$icono] ?? null;

                if ($esperada !== null && $variante !== $esperada) {
                    $violaciones[] = sprintf(
                        '[color-accion] el botón icon="%s" de la fila es variant="%s" (tiene %s): Ver = info-outline, Editar = warning-outline, Eliminar = danger-outline.',
                        $icono,
                        $esperada,
                        $variante === null ? (panelHomogeneoAtributoLigado($boton['atributos'], 'variant') ? 'variant ligado' : $variantePorOmision.' por omisión') : 'variant="'.$variante.'"',
                    );
                }

                // Un `:variant` ligado (p. ej. un ternario editar/ver) no se puede
                // leer acá: solo se juzga el literal o la omisión.
                $variantePropia = $variante ?? (panelHomogeneoAtributoLigado($boton['atributos'], 'variant') ? null : $variantePorOmision);

                if (panelHomogeneoAtributo($boton['atributos'], 'size') === 'sm' && $variantePropia === 'primary') {
                    $violaciones[] = '[color-accion] un botón size="sm" de la fila usa variant="primary": una acción de fila es <tono>-outline.';
                }
            }
        }
    }

    $vacios = panelHomogeneoBloques($blade, 'x-molecules.empty-state');

    if ($vacios === []) {
        $violaciones[] = '[empty-state] falta <x-molecules.empty-state> para el listado vacío y el filtro sin resultados.';
    }

    foreach ($vacios as $vacio) {
        if (str_contains($vacio['cuerpo'] ?? '', '<x-slot:action')) {
            $violaciones[] = '[empty-state] el empty-state de un index lleva <x-slot:action>: el vacío solo explica, el alta ya está en la cabecera.';
        }
    }

    return $violaciones;
}

/**
 * Reglas de un formulario (plan §3.3 a §3.5 y §4.3).
 *
 * @param  array{ruta: string, modulo: string, directorio: string, archivo: string, absoluta: string}  $pantalla
 * @return list<string>
 */
function panelHomogeneoViolacionesFormulario(string $raiz, array $pantalla): array
{
    $vista = panelHomogeneoVista($raiz, $pantalla['absoluta']);
    $blade = $vista['contenido'];
    $violaciones = [];

    foreach ([
        'form-layout' => 'x-molecules.form-layout',
        'form-section' => 'x-molecules.form-section',
        'form-actions-bar' => 'x-organisms.form-actions-bar',
        'page-header' => 'x-organisms.page-header',
    ] as $regla => $componente) {
        if (! panelHomogeneoUsa($blade, $componente)) {
            $violaciones[] = sprintf('[%s] falta <%s>.', $regla, $componente);
        }
    }

    $pintaEstado = false;

    foreach (panelHomogeneoBloques($blade, 'x-molecules.alert-strip') as $aviso) {
        if (preg_match('/session\(\s*[\'"]estado[\'"]\s*\)/', $aviso['atributos'].($aviso['cuerpo'] ?? '')) === 1) {
            $pintaEstado = true;
        }
    }

    if (! $pintaEstado) {
        $violaciones[] = "[flash] el formulario no pinta session('estado') con <x-molecules.alert-strip>.";
    }

    $violaciones = [...$violaciones, ...panelHomogeneoViolacionesConfirm($raiz, $pantalla, $vista['archivos'], false)];

    // La ficha de edición es el partial compartido o, sin partial, `edit`.
    $carpeta = dirname($pantalla['absoluta']);
    $esFichaDeEdicion = in_array($pantalla['archivo'], ['_formulario.blade.php', 'edit.blade.php'], true);
    $tieneEdicion = is_file($carpeta.'/edit.blade.php');

    if ($esFichaDeEdicion && $tieneEdicion && preg_match('/<x-slot:aside(?![\w-])/', $blade) !== 1) {
        $violaciones[] = '[aside] el formulario tiene ficha de edición y no declara <x-slot:aside> con el resumen relacionado.';
    }

    if ($esFichaDeEdicion && $tieneEdicion) {
        $clave = $pantalla['modulo'].'/'.$pantalla['directorio'];
        $maquina = array_search($clave, panelHomogeneoMaquinasConFicha(), true);

        if ($maquina !== false && ! panelHomogeneoFichaUsaPasos($raiz, $carpeta)) {
            $violaciones[] = sprintf('[step-arrow] %s tiene máquina de estados y ficha de edición: la cabecera de la ficha muestra los pasos con <x-molecules.step-arrow>.', $maquina);
        }
    }

    return $violaciones;
}

/**
 * Reglas de una ficha (arquetipo Detalle, plan §8, guía §6.4): solo lo
 * verificable por texto del patrón, sin exigirle a una ficha nada propio de
 * un listado o de un formulario — `index-table` puede aparecer (una ficha
 * suele traer una sub-lista) pero no es obligatorio, y tampoco se exige
 * `form-section`/`form-actions-bar`/flash, que dependen de cada ficha.
 *
 * @param  array{ruta: string, modulo: string, directorio: string, archivo: string, absoluta: string}  $pantalla
 * @return list<string>
 */
function panelHomogeneoViolacionesFicha(string $raiz, array $pantalla): array
{
    $vista = panelHomogeneoVista($raiz, $pantalla['absoluta']);
    $blade = $vista['contenido'];
    $violaciones = [];

    foreach ([
        'page-header' => 'x-organisms.page-header',
        'boton-volver' => 'x-molecules.boton-volver',
        'form-layout' => 'x-molecules.form-layout',
        'stat-card' => 'x-molecules.stat-card',
    ] as $regla => $componente) {
        if (! panelHomogeneoUsa($blade, $componente)) {
            $violaciones[] = sprintf('[%s] falta <%s>.', $regla, $componente);
        }
    }

    if (preg_match('/class="[^"]*\bag-filtros(?![\w-])/', $blade) === 1) {
        $violaciones[] = '[filtros-viejos] queda el <form class="ag-filtros"> anterior: una ficha no filtra.';
    }

    if (preg_match('/style="[^"]*(?:color|background)\s*:/i', $blade) === 1 || preg_match('/#[0-9a-fA-F]{3,8}\b/', $blade) === 1) {
        $violaciones[] = '[color-literal] la ficha tiene un color literal (style="…color…" o un hex): usa una clase de página con un token --ag-color-*.';
    }

    return [...$violaciones, ...panelHomogeneoViolacionesConfirm($raiz, $pantalla, $vista['archivos'], false)];
}

/** Si alguna vista de la ficha de edición de la carpeta (edit, partial y parciales) usa `step-arrow`. */
function panelHomogeneoFichaUsaPasos(string $raiz, string $carpeta): bool
{
    foreach (['edit.blade.php', '_formulario.blade.php'] as $archivo) {
        if (is_file($carpeta.'/'.$archivo) && panelHomogeneoUsa(panelHomogeneoVista($raiz, $carpeta.'/'.$archivo)['contenido'], 'x-molecules.step-arrow')) {
            return true;
        }
    }

    return false;
}

/**
 * @param  array{ruta: string, modulo: string, directorio: string, archivo: string, absoluta: string, tipo: 'listado'|'formulario'|'ficha'}  $pantalla
 * @return list<string>
 */
function panelHomogeneoViolaciones(string $raiz, array $pantalla): array
{
    return match ($pantalla['tipo']) {
        'listado' => panelHomogeneoViolacionesListado($raiz, $pantalla),
        'formulario' => panelHomogeneoViolacionesFormulario($raiz, $pantalla),
        'ficha' => panelHomogeneoViolacionesFicha($raiz, $pantalla),
    };
}

// Red de seguridad del descubrimiento: si esto falla, las reglas de abajo no
// protegen nada (p. ej. porque se movió una carpeta de vistas).
test('el descubrimiento encuentra las pantallas de referencia y deja afuera las excluidas', function () use ($raizProyecto) {
    $rutas = array_column(panelHomogeneoPantallas($raizProyecto), 'ruta');

    expect($rutas)
        ->toContain('Comercial/clientes/index.blade.php')
        ->toContain('Comercial/clientes/_formulario.blade.php')
        ->toContain('Operaciones/ordenes-trabajo/create.blade.php')
        ->toContain('Mantenimiento/ordenes/_formulario.blade.php')
        ->toContain('Operaciones/trabajos/edit.blade.php')
        ->toContain('Operaciones/ordenes/show.blade.php')
        ->toContain('Personal/personas/desempeno.blade.php')
        ->not->toContain('Seguridad/roles/index.blade.php')
        ->not->toContain('Seguridad/usuarios/create.blade.php')
        ->not->toContain('Portal/actas/index.blade.php')
        ->not->toContain('Operaciones/ordenes/create.blade.php');
});

test('todo index evaluado pinta una tabla de objetos', function () use ($raizProyecto) {
    $sinTabla = [];

    foreach (panelHomogeneoPantallas($raizProyecto) as $pantalla) {
        if ($pantalla['tipo'] === 'listado' && ! panelHomogeneoPintaTabla(panelHomogeneoVista($raizProyecto, $pantalla['absoluta'])['contenido'])) {
            $sinTabla[] = $pantalla['ruta'];
        }
    }

    expect($sinTabla)->toBe([], "Estos index no pintan una tabla de objetos, así que las reglas de listado no aplican. Si no son un listado, súmalos a las exclusiones de panelHomogeneoExcluida().\n".implode("\n", $sinTabla));
});

test('cada máquina de estados está clasificada: con ficha de edición o sin ella', function () use ($raizProyecto) {
    $conFicha = panelHomogeneoMaquinasConFicha();
    $sinFicha = panelHomogeneoMaquinasSinFicha();
    $encontradas = panelHomogeneoMaquinasExistentes($raizProyecto);
    $problemas = [];

    foreach ($encontradas as $clase => $modulo) {
        if (! isset($conFicha[$clase]) && ! isset($sinFicha[$clase])) {
            $problemas[] = "$clase: máquina sin clasificar. Decide si su objeto tiene ficha de edición (panelHomogeneoMaquinasConFicha) o no (panelHomogeneoMaquinasSinFicha).";
        }
    }

    foreach ($conFicha as $clase => $carpeta) {
        if (! isset($encontradas[$clase])) {
            $problemas[] = "$clase: figura con ficha de edición pero la máquina ya no existe.";
        } elseif (! str_starts_with($carpeta, $encontradas[$clase].'/')) {
            $problemas[] = "$clase: la máquina es del módulo {$encontradas[$clase]} y la tabla apunta a $carpeta.";
        } elseif (! is_file($raizProyecto.'/app/Dominios/'.$encontradas[$clase].'/Infraestructura/Http/Views/pages/'.substr($carpeta, strlen($encontradas[$clase]) + 1).'/edit.blade.php')) {
            $problemas[] = "$clase: $carpeta no tiene edit.blade.php; si el objeto no tiene ficha de edición va en panelHomogeneoMaquinasSinFicha.";
        }
    }

    foreach (array_keys($sinFicha) as $clase) {
        if (! isset($encontradas[$clase])) {
            $problemas[] = "$clase: figura sin ficha de edición pero la máquina ya no existe.";
        }
    }

    expect($problemas)->toBe([], implode("\n", $problemas));
});

test('las pantallas que no están en pendientes siguen el patrón del panel', function () use ($raizProyecto) {
    $pendientes = panelHomogeneoPendientes($raizProyecto);
    $fallas = [];

    foreach (panelHomogeneoPantallas($raizProyecto) as $pantalla) {
        if (in_array($pantalla['ruta'], $pendientes, true)) {
            continue;
        }

        $violaciones = panelHomogeneoViolaciones($raizProyecto, $pantalla);

        if ($violaciones !== []) {
            $fallas[] = $pantalla['ruta']."\n    ".implode("\n    ", $violaciones);
        }
    }

    expect($fallas)->toBe([], "Estas pantallas no siguen el patrón del panel (plan docs/gestion/plan_homogeneizacion_panel.md, receta docs/diseno/guia_pantalla_panel.md) y no figuran en docs/diseno/panel_homogeneo_pendientes.txt. Corrígelas; no las agregues a la lista.\n\n".implode("\n\n", $fallas));
});

test('la lista de pendientes no miente', function () use ($raizProyecto) {
    $pendientes = panelHomogeneoPendientes($raizProyecto);
    $evaluadas = [];

    foreach (panelHomogeneoPantallas($raizProyecto) as $pantalla) {
        $evaluadas[$pantalla['ruta']] = $pantalla;
    }

    $existentes = array_column(panelHomogeneoVistas($raizProyecto), 'ruta');
    $mentiras = [];

    foreach (array_count_values($pendientes) as $ruta => $veces) {
        if ($veces > 1) {
            $mentiras[] = "$ruta: figura $veces veces.";
        }
    }

    foreach (array_unique($pendientes) as $ruta) {
        if (! in_array($ruta, $existentes, true)) {
            $mentiras[] = "$ruta: no existe; sácala de la lista.";
        } elseif (! isset($evaluadas[$ruta])) {
            $mentiras[] = "$ruta: existe pero este test no la evalúa (está excluida, o no es un listado ni un formulario); sácala de la lista.";
        } elseif (panelHomogeneoViolaciones($raizProyecto, $evaluadas[$ruta]) === []) {
            $mentiras[] = "$ruta: ya cumple todas las reglas; sácala de la lista.";
        }
    }

    expect($mentiras)->toBe([], "docs/diseno/panel_homogeneo_pendientes.txt no puede mentir: solo lleva pantallas que existen y que todavía incumplen alguna regla.\n".implode("\n", $mentiras));
});

test('ninguna pantalla de referencia se esconde en la lista de pendientes', function () use ($raizProyecto) {
    $pendientes = panelHomogeneoPendientes($raizProyecto);
    $escondidas = [];

    // Cada entrada es una referencia PARA CIERTOS TIPOS de pantalla: un
    // directorio puede ser la referencia de su listado/formulario sin serlo
    // (todavía) de su ficha, y viceversa — un mismo directorio puede aparecer
    // en las dos listas (Operaciones/ordenes y ordenes-trabajo lo son de
    // las tres cosas).
    $referencias = [
        ...array_map(fn (string $carpeta): array => ['carpeta' => $carpeta, 'tipos' => ['listado', 'formulario']], panelHomogeneoReferencias()),
        ...array_map(fn (string $carpeta): array => ['carpeta' => $carpeta, 'tipos' => ['ficha']], panelHomogeneoReferenciasFicha()),
    ];

    foreach ($referencias as $referencia) {
        $delDirectorio = array_filter(
            panelHomogeneoPantallas($raizProyecto),
            fn (array $pantalla): bool => $referencia['carpeta'] === $pantalla['modulo'].'/'.$pantalla['directorio']
                && in_array($pantalla['tipo'], $referencia['tipos'], true),
        );

        expect($delDirectorio)->not->toBe([], sprintf('La referencia %s no tiene pantallas evaluadas de tipo %s: ¿se movió la carpeta?', $referencia['carpeta'], implode('/', $referencia['tipos'])));

        foreach ($delDirectorio as $pantalla) {
            if (in_array($pantalla['ruta'], $pendientes, true)) {
                $escondidas[] = $pantalla['ruta'];
            }
        }
    }

    expect($escondidas)->toBe([], "Las referencias del plan (§1 y §8) tienen que pasar las reglas sin perdón. Si una regla les falla, corrige la pantalla o —si la regla está mal escrita— la regla.\n".implode("\n", $escondidas));
});

/**
 * Árbol temporal de vistas sintéticas (`ruta relativa a la raíz => contenido`).
 *
 * @param  array<string, string>  $archivos
 */
function panelHomogeneoTemporal(array $archivos): string
{
    $raiz = sys_get_temp_dir().'/panel-homogeneo-'.bin2hex(random_bytes(4));

    foreach ($archivos as $ruta => $contenido) {
        if (! is_dir(dirname($raiz.'/'.$ruta))) {
            mkdir(dirname($raiz.'/'.$ruta), 0777, true);
        }

        file_put_contents($raiz.'/'.$ruta, $contenido);
    }

    return $raiz;
}

function panelHomogeneoBorrar(string $raiz): void
{
    /** @var iterable<SplFileInfo> $iterador */
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($iterador as $elemento) {
        $elemento->isDir() ? rmdir($elemento->getPathname()) : unlink($elemento->getPathname());
    }

    rmdir($raiz);
}

/**
 * Los identificadores `[regla]` que dispara una vista sintética. `$principal`
 * es el archivo evaluado (`index` o `_formulario`); `$extras`, sus hermanos
 * y parciales, ya con su ruta bajo `pages/<carpeta>/`.
 *
 * @param  'listado'|'formulario'|'ficha'  $tipo
 * @param  array<string, string>  $extras
 * @return list<string>
 */
function panelHomogeneoReglasSinteticas(string $tipo, string $principal, array $extras = [], string $modulo = 'Prueba', string $carpeta = 'cosas'): array
{
    $archivo = match ($tipo) {
        'listado' => 'index.blade.php',
        'formulario' => '_formulario.blade.php',
        'ficha' => 'show.blade.php',
    };
    $paginas = "app/Dominios/$modulo/Infraestructura/Http/Views/pages/$carpeta";
    $arbol = [$paginas.'/'.$archivo => $principal];

    foreach ($extras as $nombre => $contenido) {
        $arbol[$paginas.'/'.$nombre] = $contenido;
    }

    $raiz = panelHomogeneoTemporal($arbol);

    try {
        $violaciones = panelHomogeneoViolaciones($raiz, [
            'ruta' => "$modulo/$carpeta/$archivo",
            'modulo' => $modulo,
            'directorio' => $carpeta,
            'archivo' => $archivo,
            'absoluta' => $raiz.'/'.$paginas.'/'.$archivo,
            'tipo' => $tipo,
        ]);
    } finally {
        panelHomogeneoBorrar($raiz);
    }

    $reglas = array_map(fn (string $violacion): string => preg_match('/^\[([\w-]+)\]/', $violacion, $m) === 1 ? $m[1] : $violacion, $violaciones);
    $reglas = array_values(array_unique($reglas));
    sort($reglas);

    return $reglas;
}

const PANEL_HOMOGENEO_LISTADO_CONFORME = <<<'BLADE'
{{-- El `confirm()` de antes se cambió por un modal; ver confirm-button. --}}
<x-organisms.page-header :title="__('x.titulo')" />
@if ($cosas->isEmpty())
    <x-molecules.empty-state icon="search_off" :title="__('x.a')" :detail="__('x.b')" />
@else
    <x-molecules.index-table columns="3rem var(--ag-row-actions-width)">
        <div class="ag-index-table__row" role="row">
            <span role="cell" class="ag-index-table__acciones">
                <form id="cosa-eliminar" method="POST" action="{{ route('x.destroy') }}"></form>
                <x-organisms.row-actions>
                    <x-atoms.button :href="route('x.show')" variant="info-outline" size="sm" icon="visibility">Ver</x-atoms.button>
                    <x-atoms.button :href="route('x.edit')" variant="warning-outline" size="sm" icon="edit">Editar</x-atoms.button>
                    <x-molecules.confirm-button :form-id="'cosa-eliminar'" :title="__('x.t')" :message="__('x.m')" :confirm-label="__('x.c')" variant="danger-outline" size="sm" icon="delete">Eliminar</x-molecules.confirm-button>
                </x-organisms.row-actions>
                <x-molecules.info-modal id="aviso" :title="__('x.t')">
                    <x-atoms.button :href="route('x.otra')" variant="outline">Ir a otra pantalla</x-atoms.button>
                </x-molecules.info-modal>
            </span>
        </div>
    </x-molecules.index-table>
@endif
BLADE;

test('las reglas de listado dejan pasar un listado conforme', function () {
    expect(panelHomogeneoReglasSinteticas('listado', PANEL_HOMOGENEO_LISTADO_CONFORME))->toBe([]);
});

test('las reglas de listado detectan cada incumplimiento', function () {
    $defectuoso = <<<'BLADE'
<form class="ag-filtros" method="GET" onsubmit="return confirm('¿Seguro?')"></form>
<x-molecules.empty-state icon="a" :title="__('x.a')" :detail="__('x.b')">
    <x-slot:action><x-atoms.button :href="route('x.create')">Crear</x-atoms.button></x-slot:action>
</x-molecules.empty-state>
<div role="table">
    <div role="row">
        <x-atoms.button :href="route('x.edit')" variant="outline" size="sm" icon="edit">Editar</x-atoms.button>
    </div>
</div>
<x-molecules.index-table columns="3rem">
    <div role="row">
        <x-atoms.button :href="route('x.suelto')" variant="outline" size="sm">Suelto</x-atoms.button>
        <x-organisms.row-actions>
            <form id="dentro" method="POST"></form>
            <x-molecules.confirm-modal id="m" form-id="dentro" :title="__('x.t')" :message="__('x.m')" :confirm-label="__('x.c')" />
            <x-atoms.button :href="route('x.edit')" variant="outline" size="sm" icon="edit">Editar</x-atoms.button>
            <x-atoms.button :href="route('x.show')" variant="text" size="sm" icon="visibility">Ver</x-atoms.button>
            <x-atoms.button type="submit" variant="primary" size="sm" icon="delete">Eliminar</x-atoms.button>
            <x-atoms.button :href="route('x.otra')" size="sm" icon="bolt">Otra</x-atoms.button>
        </x-organisms.row-actions>
    </div>
</x-molecules.index-table>
BLADE;

    expect(panelHomogeneoReglasSinteticas('listado', $defectuoso))
        ->toBe(['color-accion', 'confirm-nativo', 'empty-state', 'filtros-viejos', 'row-actions']);

    $sinTabla = '<div role="table"><div role="row"></div></div>';

    expect(panelHomogeneoReglasSinteticas('listado', $sinTabla))->toContain('index-table')->toContain('empty-state');
});

test('un botón con :variant ligado no se juzga como primary', function () {
    $ligado = str_replace(
        '<x-atoms.button :href="route(\'x.edit\')" variant="warning-outline" size="sm" icon="edit">Editar</x-atoms.button>',
        '<x-atoms.button :href="route(\'x.edit\')" :variant="$enCurso ? \'warning-outline\' : \'info-outline\'" size="sm" :icon="$enCurso ? \'edit\' : \'visibility\'">Editar</x-atoms.button>',
        PANEL_HOMOGENEO_LISTADO_CONFORME,
    );

    expect($ligado)->not->toBe(PANEL_HOMOGENEO_LISTADO_CONFORME)
        ->and(panelHomogeneoReglasSinteticas('listado', $ligado))->toBe([]);
});

test('las reglas de listado siguen los parciales que se incluyen', function () {
    $index = <<<'BLADE'
<x-molecules.empty-state icon="a" :title="__('x.a')" :detail="__('x.b')" />
<x-molecules.index-table columns="3rem">
    <div role="row">
        <span role="cell">@include('prueba::pages.cosas._acciones', ['cosa' => $cosa])</span>
    </div>
</x-molecules.index-table>
BLADE;

    $acciones = <<<'BLADE'
<x-organisms.row-actions>
    <x-atoms.button :href="route('x.edit')" variant="outline" size="sm" icon="edit">Editar</x-atoms.button>
</x-organisms.row-actions>
BLADE;

    expect(panelHomogeneoReglasSinteticas('listado', $index, ['_acciones.blade.php' => $acciones]))->toBe(['color-accion']);
});

test('el confirm() nativo de un JS de página también cuenta, pero no el comentado', function () {
    expect(panelHomogeneoConfirmNativo(panelHomogeneoSinComentarios("// if (confirm('x')) {}\nconst ok = window.confirm('¿Seguro?');\nfoo.confirm('no');", true)))
        ->toBe([2]);
});

const PANEL_HOMOGENEO_FORMULARIO_CONFORME = <<<'BLADE'
<form method="POST" action="{{ $accion }}">
    <x-organisms.page-header :title="__('x.titulo')" />
    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">{{ session('estado') }}</x-molecules.alert-strip>
    @endif
    <x-molecules.form-layout>
        @if ($esEdicion)
            <x-slot:aside><x-molecules.empty-state icon="a" :title="__('x.a')" :detail="__('x.b')" /></x-slot:aside>
        @endif
        <x-molecules.form-section :title="__('x.s')"></x-molecules.form-section>
        <x-organisms.form-actions-bar :status="__('x.e')"></x-organisms.form-actions-bar>
    </x-molecules.form-layout>
</form>
BLADE;

test('las reglas de formulario dejan pasar un formulario conforme', function () {
    expect(panelHomogeneoReglasSinteticas('formulario', PANEL_HOMOGENEO_FORMULARIO_CONFORME, ['edit.blade.php' => '']))->toBe([]);
});

test('las reglas de formulario detectan cada incumplimiento', function () {
    $vacio = '<form onsubmit="return confirm(\'¿Seguro?\')"></form>';

    expect(panelHomogeneoReglasSinteticas('formulario', $vacio, ['edit.blade.php' => '']))
        ->toBe(['aside', 'confirm-nativo', 'flash', 'form-actions-bar', 'form-layout', 'form-section', 'page-header']);

    // Sin ficha de edición (solo alta) el aside no se exige.
    expect(panelHomogeneoReglasSinteticas('formulario', $vacio))->not->toContain('aside');

    // Un flash que no pasa por alert-strip no cuenta.
    $flashSuelto = str_replace('x-molecules.alert-strip', 'div', PANEL_HOMOGENEO_FORMULARIO_CONFORME);

    expect(panelHomogeneoReglasSinteticas('formulario', $flashSuelto, ['edit.blade.php' => '']))->toBe(['flash']);
});

test('un objeto con máquina de estados y ficha de edición debe mostrar los pasos', function () {
    // `Comercial/contratos` está en la tabla de máquinas con ficha de edición.
    expect(panelHomogeneoReglasSinteticas('formulario', PANEL_HOMOGENEO_FORMULARIO_CONFORME, ['edit.blade.php' => ''], 'Comercial', 'contratos'))
        ->toBe(['step-arrow']);

    $conPasos = str_replace('</x-organisms.page-header>', '', PANEL_HOMOGENEO_FORMULARIO_CONFORME);
    $conPasos = str_replace('<x-molecules.form-layout>', "<x-molecules.step-arrow :steps=\"\$pasos\" />\n<x-molecules.form-layout>", $conPasos);

    expect(panelHomogeneoReglasSinteticas('formulario', $conPasos, ['edit.blade.php' => ''], 'Comercial', 'contratos'))->toBe([]);

    // Los pasos pueden venir de un parcial que el formulario incluye.
    $conParcial = str_replace('<x-molecules.form-layout>', "@include('comercial::pages.contratos._pasos')\n<x-molecules.form-layout>", PANEL_HOMOGENEO_FORMULARIO_CONFORME);

    expect(panelHomogeneoReglasSinteticas('formulario', $conParcial, ['edit.blade.php' => '', '_pasos.blade.php' => '<x-molecules.step-arrow :steps="$pasos" />'], 'Comercial', 'contratos'))->toBe([]);
});

const PANEL_HOMOGENEO_FICHA_CONFORME = <<<'BLADE'
<x-molecules.boton-volver :href="route('x.index')" :label="__('x.volver')" />
<x-organisms.page-header :title="__('x.titulo')" />
<div class="ag-x-detalle__kpis">
    <x-molecules.stat-card :label="__('x.a')" :value="3" />
</div>
<x-molecules.form-layout>
    <x-molecules.form-section :title="__('x.s')"></x-molecules.form-section>
</x-molecules.form-layout>
BLADE;

test('las reglas de ficha dejan pasar una ficha conforme', function () {
    expect(panelHomogeneoReglasSinteticas('ficha', PANEL_HOMOGENEO_FICHA_CONFORME))->toBe([]);
});

test('las reglas de ficha detectan cada incumplimiento', function () {
    $defectuosa = <<<'BLADE'
<form class="ag-filtros" method="GET" onsubmit="return confirm('¿Seguro?')"></form>
<small style="color: var(--ag-color-text-muted)">x</small>
BLADE;

    expect(panelHomogeneoReglasSinteticas('ficha', $defectuosa))
        ->toBe(['boton-volver', 'color-literal', 'confirm-nativo', 'filtros-viejos', 'form-layout', 'page-header', 'stat-card']);

    // Un hex literal (sin `style=`) también cuenta.
    expect(panelHomogeneoReglasSinteticas('ficha', str_replace('style="color: var(--ag-color-text-muted)"', '', $defectuosa).' <span>#3fae2c</span>'))
        ->toContain('color-literal');
});

test('una ficha no le pide reglas de listado ni de formulario', function () {
    // `index-table` puede faltar (no toda ficha trae una sub-lista) y no hace
    // falta `form-section`/`form-actions-bar`/flash: eso es del arquetipo
    // Formulario, no del Detalle.
    $reglas = panelHomogeneoReglasSinteticas('ficha', PANEL_HOMOGENEO_FICHA_CONFORME);

    expect($reglas)->not->toContain('index-table')
        ->not->toContain('form-section')
        ->not->toContain('form-actions-bar')
        ->not->toContain('flash')
        ->not->toContain('aside')
        ->not->toContain('empty-state');
});

test('el lector de etiquetas no se corta en un -> ni confunde componentes con el mismo prefijo', function () {
    $blade = '<x-atoms.button :variant="$a->b ? \'x\' : \'y\'" :datos="[\'k\' => 1]" icon="edit">Editar</x-atoms.button>'
        .'<x-atoms.button-group>no</x-atoms.button-group>'
        .'<x-atoms.button :href="route(\'x\')" variant="outline" />';

    $bloques = panelHomogeneoBloques($blade, 'x-atoms.button');

    expect($bloques)->toHaveCount(2)
        ->and($bloques[0]['cuerpo'])->toBe('Editar')
        ->and(panelHomogeneoAtributo($bloques[0]['atributos'], 'icon'))->toBe('edit')
        ->and(panelHomogeneoAtributoLigado($bloques[0]['atributos'], 'variant'))->toBeTrue()
        ->and(panelHomogeneoAtributo($bloques[0]['atributos'], 'variant'))->toBeNull()
        ->and($bloques[1]['cuerpo'])->toBeNull()
        ->and(panelHomogeneoAtributo($bloques[1]['atributos'], 'variant'))->toBe('outline')
        ->and(panelHomogeneoAtributo('modal-icon="x" :icon="y"', 'icon'))->toBeNull();
});

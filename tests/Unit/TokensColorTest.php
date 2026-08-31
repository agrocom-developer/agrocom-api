<?php

/*
 * Invariante 11 de CLAUDE.md ("ningún color hardcodeado en el panel: todo
 * color se referencia por token CSS") con compuerta automática, no por
 * revisión — es lo que permite el theming por usuario (ADR 0002). Un literal
 * de color en un componente no reasigna al cambiar [data-bs-theme]: pasa la
 * cascada entera y rompe el tema oscuro en silencio, que es justo lo que el
 * skill `verificacion` señala como no cubierto por la suite.
 *
 * Dónde SÍ pueden vivir los literales: `resources/css/tokens/` — primitives/
 * declara la paleta cruda derivada de los logos y semantic/ la reasigna por
 * tema. Ese es el único lugar donde un hex es la definición y no una fuga.
 */

$raizProyecto = dirname(__DIR__, 2);

/** Literal de color CSS: hex de 3/4/6/8 dígitos, o una función rgb()/hsl(). */
const PATRON_COLOR_LITERAL = '/#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![0-9a-fA-F])|\b(?:rgba?|hsla?)\s*\(/';

/**
 * Los comentarios se descartan antes de buscar: los archivos de este repo
 * documentan decisiones de color citando el hex del que salen ("green-700 =
 * #1e7a34"), y esas citas son parte del valor del comentario — no fugas.
 */
function tokensSinComentarios(string $contenido): string
{
    $sinComentarios = preg_replace('~/\*.*?\*/~s', '', $contenido) ?? $contenido;
    $sinComentarios = preg_replace('~\{\{--.*?--\}\}~s', '', $sinComentarios) ?? $sinComentarios;
    $sinComentarios = preg_replace('~<!--.*?-->~s', '', $sinComentarios) ?? $sinComentarios;

    return preg_replace('~^\s*//.*$~m', '', $sinComentarios) ?? $sinComentarios;
}

/**
 * CSS, JS y Blade de todo el panel — incluidas las páginas que viven bajo
 * `Infraestructura/Http/Views/` de cada módulo (ADR 0008), no solo las de
 * `resources/`. Descubierto recorriendo el árbol: una carpeta de vistas nueva
 * queda protegida sin editar este archivo.
 *
 * @return list<string>
 */
function tokensArchivosDelPanel(string $raizProyecto): array
{
    $archivos = [];

    foreach (['resources/css', 'resources/js', 'resources/views', 'app/Dominios'] as $raiz) {
        $rutaRaiz = $raizProyecto.'/'.$raiz;

        if (! is_dir($rutaRaiz)) {
            continue;
        }

        /** @var iterable<SplFileInfo> $iterador */
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rutaRaiz, FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $archivo) {
            $ruta = $archivo->getPathname();
            $relativa = substr($ruta, strlen($raizProyecto) + 1);

            if (! $archivo->isFile() || preg_match('/\.(css|js|blade\.php)$/', $ruta) !== 1) {
                continue;
            }

            // Única excepción: la definición de la paleta misma.
            if (str_starts_with($relativa, 'resources/css/tokens/')) {
                continue;
            }

            $archivos[] = $relativa;
        }
    }

    sort($archivos);

    return $archivos;
}

// Red de seguridad del descubrimiento: si esto falla, la regla de abajo no
// protege nada (p. ej. porque se movió resources/css).
test('el descubrimiento encuentra los archivos de estilo del panel', function () use ($raizProyecto) {
    expect(tokensArchivosDelPanel($raizProyecto))
        ->toContain('resources/css/components/role-card.css')
        ->toContain('app/Dominios/Seguridad/Infraestructura/Http/Views/pages/dashboard.blade.php');
});

test('ningún color hardcodeado fuera de resources/css/tokens/ (CLAUDE.md invariante 11)', function () use ($raizProyecto) {
    $fugas = [];

    foreach (tokensArchivosDelPanel($raizProyecto) as $relativa) {
        $contenido = file_get_contents($raizProyecto.'/'.$relativa);

        if ($contenido === false) {
            continue;
        }

        foreach (explode("\n", tokensSinComentarios($contenido)) as $numero => $linea) {
            if (preg_match(PATRON_COLOR_LITERAL, $linea) === 1) {
                $fugas[] = sprintf('%s:%d: %s', $relativa, $numero + 1, trim($linea));
            }
        }
    }

    expect($fugas)->toBe([], "Usá un token CSS (var(--ag-…)); si el color no existe todavía, se declara en resources/css/tokens/.\n".implode("\n", $fugas));
});

test('el token de la sombra de selección está declarado en los dos temas', function () use ($raizProyecto) {
    // La regla de arriba solo prohíbe el literal; sin esto, moverlo a un token
    // que existe en un solo tema pasaría igual y rompería el otro en silencio.
    foreach (['theme-light', 'theme-dark'] as $tema) {
        expect(file_get_contents($raizProyecto."/resources/css/tokens/semantic/{$tema}.css"))
            ->toContain('--ag-shadow-selected:');
    }
});

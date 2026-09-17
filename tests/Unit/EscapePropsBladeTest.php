<?php

/*
 * Compuerta contra el doble escape de HTML al pasarle un texto a un
 * componente Blade.
 *
 * `<x-atoms.input error="{{ $mensaje }}" />` parece inocente, pero el `{{ }}`
 * escapa el valor AL ENTRAR a la prop y el componente lo vuelve a escapar al
 * pintarlo con su propio `{{ $error }}`. El usuario termina leyendo
 * `Ya existe un cliente activo con el NIT &#039;1415018&#039;.`, y con
 * `value=` o `href=` es peor que feo: un `&` vuelve al servidor como `&amp;`
 * y se guarda corrupto, o rompe el query string de una URL.
 *
 * La forma correcta es enlazar la expresión: `:error="$mensaje"` — un solo
 * escape, el del componente. Esta prueba falla si una PROP declarada
 * (`@props`) de un componente recibe un valor que es, entero, un `{{ }}`.
 *
 * Qué NO mira, a propósito:
 * - Atributos que no son props (van al `$attributes` bag, que no re-escapa).
 * - Valores mezclados (`name="lotes[{{ $i }}][codigo]"`, `id="x-{{ $id }}"`):
 *   llevan índices e identificadores, no texto libre, y no tienen una forma
 *   enlazada más legible.
 * - Tags HTML planos (`<input value="{{ $x }}">`): ahí un escape es lo justo.
 */

$raizProyecto = dirname(__DIR__, 2);

/**
 * Props declaradas de un componente anónimo, leídas de su `@props([...])`.
 * `null` si el componente no existe como archivo (no es anónimo o es de un
 * paquete): no se lo puede juzgar.
 *
 * @return list<string>|null
 */
function escapePropsDe(string $raizProyecto, string $componente): ?array
{
    static $cache = [];

    if (array_key_exists($componente, $cache)) {
        return $cache[$componente];
    }

    $base = $raizProyecto.'/resources/views/components/'.str_replace('.', '/', $componente);
    $ruta = is_file($base.'.blade.php') ? $base.'.blade.php' : $base.'/index.blade.php';

    if (! is_file($ruta)) {
        return $cache[$componente] = null;
    }

    $props = [];

    if (preg_match('/@props\(\s*\[(.*?)\]\s*\)/s', (string) file_get_contents($ruta), $bloque) === 1) {
        preg_match_all("/^\s*'(\w+)'\s*(?:=>|,|$)/m", $bloque[1], $claves);
        $props = $claves[1];
    }

    return $cache[$componente] = $props;
}

/** Índice del `>` que cierra el tag de apertura que empieza en `$desde`, sin contar los `>` dentro de comillas (`$errors->first()`). */
function escapeFinDeTag(string $contenido, int $desde): int
{
    $comilla = null;
    $largo = strlen($contenido);

    for ($i = $desde; $i < $largo; $i++) {
        $caracter = $contenido[$i];

        if ($comilla !== null) {
            if ($caracter === $comilla) {
                $comilla = null;
            }
        } elseif ($caracter === '"' || $caracter === "'") {
            $comilla = $caracter;
        } elseif ($caracter === '>') {
            return $i;
        }
    }

    return -1;
}

/**
 * @return list<string> una línea legible por cada prop mal pasada
 */
function escapeFugasEn(string $raizProyecto, string $relativa): array
{
    $contenido = (string) file_get_contents($raizProyecto.'/'.$relativa);
    $contenido = preg_replace('~\{\{--.*?--\}\}~s', '', $contenido) ?? $contenido;

    $fugas = [];

    preg_match_all('/<x-([\w.\-]+)/', $contenido, $tags, PREG_OFFSET_CAPTURE);

    foreach ($tags[1] as [$componente, $posicion]) {
        $props = escapePropsDe($raizProyecto, $componente);
        $fin = escapeFinDeTag($contenido, $posicion);

        if ($props === null || $props === [] || $fin < 0) {
            continue;
        }

        $tag = substr($contenido, $posicion, $fin - $posicion + 1);

        preg_match_all('/\s([a-zA-Z][\w\-]*)="\{\{\s*[^"]*?\s*\}\}"/', $tag, $atributos, PREG_SET_ORDER);

        foreach ($atributos as [$completo, $nombre]) {
            // Un único echo: si adentro hay otro `{{`, es un valor mezclado.
            if (substr_count($completo, '{{') !== 1) {
                continue;
            }

            if (in_array(lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $nombre)))), $props, true)) {
                $fugas[] = sprintf('%s → <x-%s> %s', $relativa, $componente, trim($completo));
            }
        }
    }

    return $fugas;
}

it('ninguna prop de componente recibe su valor con {{ }} (doble escape de HTML)', function () use ($raizProyecto) {
    $fugas = [];

    foreach (['resources/views', 'app/Dominios'] as $raiz) {
        /** @var iterable<SplFileInfo> $iterador */
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raizProyecto.'/'.$raiz, FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && str_ends_with($archivo->getPathname(), '.blade.php')) {
                $relativa = substr($archivo->getPathname(), strlen($raizProyecto) + 1);
                array_push($fugas, ...escapeFugasEn($raizProyecto, $relativa));
            }
        }
    }

    expect($fugas)->toBe(
        [],
        "Pasa la expresión enlazada —:prop=\"\$valor\"— en vez de prop=\"{{ \$valor }}\":\n  ".implode("\n  ", $fugas),
    );
});

<?php

/*
 * Compuerta de existencia de claves de idioma (ADR 0013). Una clave que el
 * código pide y el catálogo no tiene NO da error: Laravel devuelve la clave
 * tal cual y el usuario lee `validation.required` o `comercial.errores.x` en
 * la pantalla. Es un fallo silencioso, así que se atrapa acá.
 *
 * Recorre PHP, Blade y JS buscando `__('a.b.c')`, `trans()`, `trans_choice()`,
 * `@lang()`, `Lang::get()/has()` y `Texto::de()` con la clave escrita como
 * literal, y exige que exista en `lang/es/`. Las claves armadas en tiempo de
 * ejecución (`__("menu.{$clave}.label")`, `__($item['label'])`) no se pueden
 * resolver leyendo el código y quedan afuera — esas las cubre su propio
 * origen (p. ej. `sec_menu` guarda claves que siembra `SecMenuSeeder`).
 */

$raizProyecto = dirname(__DIR__, 2);

/**
 * @param  array<array-key, mixed>  $catalogo
 * @return array<string, true>
 */
function clavesAplanar(array $catalogo, string $prefijo): array
{
    $plano = [$prefijo => true];

    foreach ($catalogo as $clave => $valor) {
        $ruta = "{$prefijo}.{$clave}";
        $plano[$ruta] = true;

        if (is_array($valor)) {
            $plano += clavesAplanar($valor, $ruta);
        }
    }

    return $plano;
}

/**
 * @return array<string, true> toda clave resoluble de `lang/es/`, incluidos los nodos intermedios
 */
function clavesDelCatalogo(string $raizProyecto): array
{
    $claves = [];

    foreach (glob($raizProyecto.'/lang/es/*.php') ?: [] as $archivo) {
        /** @var array<array-key, mixed> $catalogo */
        $catalogo = require $archivo;
        $claves += clavesAplanar($catalogo, basename($archivo, '.php'));
    }

    return $claves;
}

/**
 * @return list<string>
 */
function clavesArchivosDeCodigo(string $raizProyecto): array
{
    $archivos = [];

    foreach (['app', 'resources/views', 'resources/js', 'routes', 'database/seeders'] as $raiz) {
        /** @var iterable<SplFileInfo> $iterador */
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raizProyecto.'/'.$raiz, FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && preg_match('/\.(php|js)$/', $archivo->getPathname()) === 1) {
                $archivos[] = substr($archivo->getPathname(), strlen($raizProyecto) + 1);
            }
        }
    }

    sort($archivos);

    return $archivos;
}

it('toda clave de idioma escrita en el código existe en lang/es', function () use ($raizProyecto) {
    $catalogo = clavesDelCatalogo($raizProyecto);
    $faltantes = [];

    // Llamada + primer argumento literal, SIN interpolación ni concatenación
    // (la comilla de cierre tiene que ir seguida de `,` o `)`).
    $patron = '/(?:\b__|\btrans|\btrans_choice|@lang|Lang::get|Lang::has|Texto::de)\(\s*([\'"])([a-z][a-z0-9_]*(?:\.[A-Za-z0-9_\-]+)+)\1\s*[,)]/';

    foreach (clavesArchivosDeCodigo($raizProyecto) as $relativa) {
        $contenido = (string) file_get_contents($raizProyecto.'/'.$relativa);

        if (preg_match_all($patron, $contenido, $coincidencias, PREG_OFFSET_CAPTURE) === 0) {
            continue;
        }

        foreach ($coincidencias[2] as [$clave, $posicion]) {
            if (! isset($catalogo[$clave])) {
                $linea = substr_count($contenido, "\n", 0, $posicion) + 1;
                $faltantes[] = "{$relativa}:{$linea} → {$clave}";
            }
        }
    }

    expect($faltantes)->toBe(
        [],
        "Claves que el código pide y lang/es no tiene (se verían crudas en pantalla):\n  ".implode("\n  ", $faltantes),
    );
});

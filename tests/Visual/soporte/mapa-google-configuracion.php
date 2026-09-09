<?php

/**
 * Soporte de `lote-mapa-editor.spec.ts` (tarea 79) para el test de caída del
 * SDK de Google Maps: crea o borra la fila de `mapas.google_maps_api_key`.
 *
 * A propósito FUERA de `tests/Visual/fixtures/`: ese directorio lo corre
 * ENTERO `global-setup.ts` antes de toda la suite, y esta llave cambiaría el
 * proveedor de mapa de `campos.spec.ts`/`lotes.spec.ts` sin que lo pidan —
 * sus capturas de referencia son con Leaflet. Este script lo invoca a mano
 * el propio spec, en `beforeAll`/`afterAll`, y borra la fila al terminar.
 *
 * Uso: `php mapa-google-configuracion.php [LLAVE]` — sin argumento, borra.
 */
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$llave = $argv[1] ?? null;

if ($llave === null) {
    Configuracion::query()->where('clave', 'mapas.google_maps_api_key')->delete();
    echo "borrada\n";

    return;
}

Configuracion::query()->updateOrCreate(
    ['clave' => 'mapas.google_maps_api_key'],
    ['valor' => $llave, 'grupo' => 'mapas', 'es_secreto' => true],
);
echo "configurada\n";

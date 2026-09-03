<?php

/**
 * Fixture de datos para tests/Visual/combustible.spec.ts (HU-35, tarea 49).
 * `combustible/index` necesita al menos dos filas para capturar la tabla
 * con contenido real: una de destino generador y otra de destino vehículo,
 * para que ambos valores de la columna "Destino" queden en el snapshot.
 * Base sembrada con `firstOrCreate` (mismo criterio que `PerBase` en
 * `anticipos-demo.php`/`rendiciones-demo.php`): no asume que ya exista una
 * base en la base del compose.
 *
 * Bootea Laravel manualmente, mismo patrón que `gastos-demo.php` — ver su
 * docblock para el porqué (Psy Shell no ejecuta este bloque de forma
 * confiable). Se invoca desde `combustible.spec.ts` vía `docker compose
 * exec app php tests/Visual/fixtures/combustible-demo.php`.
 *
 * Idempotente: compara por `fecha` + `destino` antes de crear, sin clave
 * natural obvia en `Combustible`, mismo criterio que `gastos-demo.php`.
 */
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

const FECHA_DEMO = '2026-09-18';

$base = PerBase::firstOrCreate(['nombre' => 'Base Combustible Demo']);

$yaHayCombustibleDemo = Combustible::where('fecha', FECHA_DEMO)->where('destino', 'generador')->exists();

if (! $yaHayCombustibleDemo) {
    Combustible::create([
        'fecha' => FECHA_DEMO,
        'base_id' => $base->id,
        'destino' => 'generador',
        'litros' => '80.00',
        'monto' => '560.00',
    ]);

    Combustible::create([
        'fecha' => FECHA_DEMO,
        'base_id' => $base->id,
        'destino' => 'vehiculo',
        'litros' => '35.50',
        'monto' => '248.50',
    ]);
}

echo "combustible-demo: OK\n";

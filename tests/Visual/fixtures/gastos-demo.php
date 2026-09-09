<?php

/**
 * Fixture de datos para tests/Visual/gastos.spec.ts (HU-33, tarea 47).
 * `gastos/index` necesita al menos dos filas para capturar la tabla con
 * contenido real: una sin comprobante y otra con, para que ambos estados de
 * la columna "Comprobante" queden en el snapshot. Rubro sembrado con
 * `firstOrCreate` (mismo criterio que `PerPersona` en `anticipos-demo.php`):
 * no asume que `FinanzasRubrosSeeder` ya corrió en la base del compose.
 *
 * Bootea Laravel manualmente, mismo patrón que `anticipos-demo.php` — ver su
 * docblock para el porqué (Psy Shell no ejecuta este bloque de forma
 * confiable). Se invoca desde `gastos.spec.ts` vía `docker compose exec app
 * php tests/Visual/fixtures/gastos-demo.php`.
 *
 * Ambos gastos son "generales" (sin `trabajo_id` ni `base_id`): evita crear
 * entidades nuevas en `ope_trabajos`/`per_bases` que puedan romper el
 * snapshot de otra pantalla — mismo criterio de no tocar datos ajenos que
 * `anticipos-demo.php`. Idempotente: sin `firstOrCreate` porque `Gasto` no
 * tiene una clave natural obvia, así que compara por `fecha` antes de crear.
 */
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rubro;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

const FECHA_DEMO = '2026-09-15';

$rubro = Rubro::firstOrCreate(
    ['nombre' => 'Combustible'],
    ['presupuesto_bs_ha' => '15.00'],
);

$yaHayGastosDemo = Gasto::where('fecha', FECHA_DEMO)->where('cantidad', '20.00')->exists();

if (! $yaHayGastosDemo) {
    Gasto::create([
        'fecha' => FECHA_DEMO,
        'rubro_id' => $rubro->id,
        'cantidad' => '20.00',
        'precio_unitario' => '12.50',
        'monto' => '250.00',
    ]);

    $conComprobante = Gasto::create([
        'fecha' => FECHA_DEMO,
        'rubro_id' => $rubro->id,
        'cantidad' => '5.00',
        'precio_unitario' => '30.00',
        'monto' => '150.00',
    ]);
    $conComprobante->update([
        'comprobante_url' => 'gastos/2026/09/'.$conComprobante->id.'.jpg',
        'comprobante_hash' => hash('sha256', 'comprobante-demo-visual'),
    ]);
}

echo "gastos-demo: OK\n";

<?php

/**
 * Fixture de datos para tests/Visual/rendiciones.spec.ts (HU-34, tarea 48).
 * Carga una rendición de demostración con gastos asociados, en estado
 * `presentada` (el más interesante visualmente: muestra el chip de estado,
 * la tabla de gastos asociados y el botón "Aprobar"). Reutiliza personas del
 * demo existente. `per_bases` no tenía ninguna fila todavía en el seed demo
 * (`fin_rendiciones.base_id` no es nullable, así que hace falta al menos
 * una) — se crea con `firstOrCreate` bajo un nombre neutral coherente con el
 * resto del demo (no atado a "rendiciones"), porque va a aparecer también en
 * `panel.bases.index`, una pantalla ajena a esta HU.
 *
 * Bootea Laravel manualmente, mismo patrón que `anticipos-demo.php` — ver su
 * docblock para el porqué. Se invoca desde `rendiciones.spec.ts` vía
 * `docker compose exec app php tests/Visual/fixtures/rendiciones-demo.php`.
 *
 * Idempotente: verifica si la rendición demo ya existe antes de crearla.
 */
use App\Dominios\Finanzas\Aplicacion\AsociarGastoARendicion;
use App\Dominios\Finanzas\Aplicacion\CrearRendicion;
use App\Dominios\Finanzas\Aplicacion\PresentarRendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rubro;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

const FECHA_DEMO = '2026-09-15';
const DESCRIPCION_DEMO = 'Rendición de demostración visual';

// Primera base del seed demo (todavía no había ninguna) — nombre neutral,
// coherente con los campos ya sembrados ("San Jorge — Cuatro Cañadas"), no
// específico de rendiciones: esta fila también la va a mostrar `panel.bases.index`.
$base = PerBase::query()->first() ?? PerBase::create([
    'nombre' => 'Base Cuatro Cañadas',
]);

// Obtener un jefe de campo del demo (cualquiera)
$jefe = PerPersona::where('rol', 'jefe_campo')->orderBy('id')->firstOrFail();

// Crear rubros si no existen
$rubro1 = Rubro::firstOrCreate(
    ['nombre' => 'Combustible'],
    ['presupuesto_bs_ha' => '15.00'],
);
$rubro2 = Rubro::firstOrCreate(
    ['nombre' => 'Herramientas'],
    ['presupuesto_bs_ha' => '10.00'],
);

// Verificar si la rendición demo ya existe
$yaHayRendicionDemo = Rendicion::where('base_id', $base->id)
    ->where('jefe_campo_id', $jefe->id)
    ->where('descripcion', DESCRIPCION_DEMO)
    ->exists();

if (! $yaHayRendicionDemo) {
    // Crear gastos de demostración
    $gasto1 = Gasto::query()->create([
        'fecha' => FECHA_DEMO,
        'rubro_id' => $rubro1->id,
        'cantidad' => '20.00',
        'precio_unitario' => '12.50',
        'monto' => '250.00',
        'base_id' => $base->id,
    ]);

    $gasto2 = Gasto::query()->create([
        'fecha' => FECHA_DEMO,
        'rubro_id' => $rubro2->id,
        'cantidad' => '3.00',
        'precio_unitario' => '50.00',
        'monto' => '150.00',
        'base_id' => $base->id,
    ]);

    // Crear rendición
    $creador = app(CrearRendicion::class);
    $rendicion = $creador->ejecutar(
        $base->id,
        $jefe->id,
        FECHA_DEMO,
        DESCRIPCION_DEMO,
    );

    // Asociar gastos a la rendición
    $asociador = app(AsociarGastoARendicion::class);
    $asociador->ejecutar($gasto1, $rendicion);
    $asociador->ejecutar($gasto2, $rendicion);

    // Presentar la rendición (cambiar estado a "presentada")
    $presentador = app(PresentarRendicion::class);
    $presentador->ejecutar($rendicion);

    echo "rendiciones-demo: OK (rendición #{$rendicion->id} creada y presentada)\n";
} else {
    echo "rendiciones-demo: SKIP (rendición demo ya existe)\n";
}

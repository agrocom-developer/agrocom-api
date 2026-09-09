<?php

/**
 * Fixture de datos para tests/Visual/combustible.spec.ts (HU-35, tarea 49;
 * reescrito por la tarea 73, HU-50). `combustible/index` necesita al menos
 * dos filas para capturar la tabla con contenido real: una de recurso
 * generador y otra de recurso vehículo, para que ambos valores de la
 * columna "Recurso" queden en el snapshot. Base/equipo/recursos sembrados
 * con `firstOrCreate` (mismo criterio que `PerBase` en
 * `anticipos-demo.php`/`rendiciones-demo.php`): no asume que ya existan en
 * la base del compose — a diferencia de la demo completa
 * (`EquiposTrabajoDemoSeeder`), este fixture es autocontenido porque
 * `combustible.spec.ts` puede correr solo.
 *
 * Bootea Laravel manualmente, mismo patrón que `gastos-demo.php` — ver su
 * docblock para el porqué (Psy Shell no ejecuta este bloque de forma
 * confiable). Se invoca desde `combustible.spec.ts` vía `docker compose
 * exec app php tests/Visual/fixtures/combustible-demo.php`.
 *
 * Idempotente: compara por `fecha` + `recurso_tipo` antes de crear, sin
 * clave natural obvia en `Combustible`, mismo criterio que `gastos-demo.php`.
 */
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

const FECHA_DEMO = '2026-09-18';
const VIGENCIA_DESDE = '2026-01-01';

$base = PerBase::firstOrCreate(['nombre' => 'Base Combustible Demo']);

$equipo = EquipoTrabajo::firstOrCreate(
    ['codigo' => 'EQ-COMB-DEMO'],
    ['base_id' => $base->id, 'estado' => 'activo', 'desde' => VIGENCIA_DESDE],
);

$generador = Generador::firstOrCreate(
    ['identificador' => 'GEN-COMB-DEMO'],
    ['base_id' => $base->id, 'estado' => 'activo'],
);

$vehiculo = Vehiculo::firstOrCreate(
    ['identificador' => 'VEH-COMB-DEMO'],
    ['base_id' => $base->id, 'estado' => 'activo'],
);

EquipoRecurso::firstOrCreate(
    ['equipo_trabajo_id' => $equipo->id, 'recurso_tipo' => 'generador', 'recurso_id' => $generador->id],
    ['desde' => VIGENCIA_DESDE],
);

EquipoRecurso::firstOrCreate(
    ['equipo_trabajo_id' => $equipo->id, 'recurso_tipo' => 'vehiculo', 'recurso_id' => $vehiculo->id],
    ['desde' => VIGENCIA_DESDE],
);

$yaHayCombustibleDemo = Combustible::where('fecha', FECHA_DEMO)->where('recurso_tipo', 'generador')->exists();

if (! $yaHayCombustibleDemo) {
    Combustible::create([
        'fecha' => FECHA_DEMO,
        'base_id' => $base->id,
        'equipo_trabajo_id' => $equipo->id,
        'recurso_tipo' => 'generador',
        'recurso_id' => $generador->id,
        'litros' => '80.00',
        'monto' => '560.00',
    ]);

    Combustible::create([
        'fecha' => FECHA_DEMO,
        'base_id' => $base->id,
        'equipo_trabajo_id' => $equipo->id,
        'recurso_tipo' => 'vehiculo',
        'recurso_id' => $vehiculo->id,
        'litros' => '35.50',
        'monto' => '248.50',
    ]);
}

echo "combustible-demo: OK\n";

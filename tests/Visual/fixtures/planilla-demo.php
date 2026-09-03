<?php

/**
 * Fixture de datos para tests/Visual/planilla.spec.ts (HU-30, tarea 44).
 * `planillas/index` y `planillas/show` necesitan al menos una planilla
 * APROBADA (con recibo en PDF) para capturar la tabla y el detalle con
 * contenido real, no solo el estado vacío — mismo criterio que
 * `anticipos-demo.php`/`devengos-demo.php`, reusa la MISMA persona/devengo
 * de esas fixtures (`Camila Rojas`, `Jefe Demo Devengos`) en vez de crear
 * entidades nuevas: cualquier fila nueva en `per_personas` sin cuenta
 * rompería el snapshot de `personas.spec.ts`. Las tres fixtures son
 * idempotentes por separado, da igual en qué orden corran o si alguna corre
 * sin las otras — por eso este archivo repite (no importa) el mismo bloque
 * "generar el devengo del mes si no existe" que ya tienen esas dos.
 *
 * Se aprueba con `camila.rojas` mismo: `PanelDemoSeeder` ya le asigna el rol
 * `dueno` entre sus tres roles demo, así que no hace falta un segundo
 * usuario solo para aprobar.
 *
 * Bootea Laravel manualmente, mismo patrón que `devengos-demo.php`/
 * `anticipos-demo.php` — ver su docblock para el porqué (Psy Shell no
 * ejecuta este bloque de forma confiable). Se invoca desde `planilla.spec.ts`
 * vía `docker compose exec app php tests/Visual/fixtures/planilla-demo.php`.
 */
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Finanzas\Aplicacion\AprobarPlanilla;
use App\Dominios\Finanzas\Aplicacion\GenerarPlanilla;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

const NIT_DEMO = '1023456022';

$cliente = Cliente::where('nit', NIT_DEMO)->firstOrFail();
$lote = Lote::whereHas('campo', fn ($consulta) => $consulta->where('cliente_id', $cliente->id))
    ->where('codigo', 'L-01')
    ->firstOrFail();
$orden = OrdenAplicacion::where('lote_id', $lote->id)->where('nro_aplicacion', 1)->firstOrFail();

$piloto = PerPersona::firstOrCreate(
    ['nombre' => 'Camila Rojas'],
    ['rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '150.00', 'activo' => true],
);
$jefe = PerPersona::firstOrCreate(
    ['nombre' => 'Jefe Demo Devengos'],
    ['rol' => RolOperativoPersona::JefeCampo, 'activo' => true],
);

$usuario = SecUser::where('username', 'camila.rojas')->firstOrFail();
if ($usuario->persona_id !== $piloto->id) {
    $usuario->persona_id = $piloto->id;
    $usuario->save();
}

$yaHayDevengoEsteMes = DevengoPersonal::where('persona_id', $piloto->id)
    ->whereBetween('fecha', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
    ->exists();

if (! $yaHayDevengoEsteMes) {
    $trabajo = Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-demo-pla-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => now(),
    ]);
    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-demo-pla-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.50',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => now(),
        'fin' => now(),
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-demo-pla-'.uniqid(),
    ]);
    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);
}

$periodo = now()->format('Y-m');
$planilla = $app->make(GenerarPlanilla::class)->ejecutar($periodo);

if ($planilla->estado->value !== 'aprobada') {
    $app->make(AprobarPlanilla::class)->ejecutar($planilla, $usuario->id);
}

// El id se imprime en su propia línea al final: `planilla.spec.ts` lo lee
// del stdout del proceso para navegar directo a `/panel/planillas/{id}` en
// vez de un `page.click()` sobre el listado (evita depender de un elemento
// de la fila anterior, mismo criterio "navegar por URL conocida" que el
// resto de las specs visuales).
echo "planilla-demo: OK\n";
echo "PLANILLA_ID={$planilla->id}\n";

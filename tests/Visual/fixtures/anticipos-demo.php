<?php

/**
 * Fixture de datos para tests/Visual/anticipos.spec.ts (HU-29, tarea 41).
 * `anticipos/index` necesita al menos una fila para capturar la tabla con
 * contenido (no solo el estado vacío) — igual que `devengos-demo.php`
 * (tarea 40), reusa la MISMA persona/devengo de esa fixture (`Camila Rojas`,
 * `Jefe Demo Devengos`, mismo cliente/lote/orden de `NucleoComercialSeeder`)
 * en vez de crear entidades nuevas: cualquier fila nueva en `per_personas`
 * sin cuenta rompería el snapshot de `personas.spec.ts`, y una nueva fila en
 * `com_clientes`/`com_campos`/`com_contratos`/`ope_ordenes_aplicacion`
 * rompería `clientes`/`campos`/`contratos`/`ordenes.spec.ts`. Las dos
 * fixtures son idempotentes por separado (`firstOrCreate`), así que da igual
 * en qué orden corran o si una corre sin la otra.
 *
 * Bootea Laravel manualmente, mismo patrón que `devengos-demo.php` — ver su
 * docblock para el porqué (Psy Shell no ejecuta este bloque de forma
 * confiable). Se invoca desde `anticipos.spec.ts` vía `docker compose exec
 * app php tests/Visual/fixtures/anticipos-demo.php`.
 *
 * El anticipo registrado (Bs 400.00) queda muy por debajo del disponible del
 * mes de Camila (devengado 1.875,00 con hectareas=12.50 × tarifa_ha=150.00;
 * 70% = 1.312,50; tope = min(3.000, 1.312,50) = 1.312,50) — a propósito, para
 * no chocar con el tope si la fixture corre más de un mes seguido sin
 * limpiar (los anticipos no se borran, ver skill `verificacion`).
 */
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Finanzas\Aplicacion\RegistrarAnticipo;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
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
        'uuid_cliente' => 'uuid-trabajo-demo-ant-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => now(),
    ]);
    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-demo-ant-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.50',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => now(),
        'fin' => now(),
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-demo-ant-'.uniqid(),
    ]);
    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);
}

$yaHayAnticipoEsteMes = Anticipo::where('persona_id', $piloto->id)
    ->whereBetween('fecha', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
    ->exists();

if (! $yaHayAnticipoEsteMes) {
    $app->make(RegistrarAnticipo::class)->ejecutar(
        $piloto->id,
        '400.00',
        now()->toDateString(),
        'Adelanto de sueldo — demo visual',
    );
}

echo "anticipos-demo: OK\n";

<?php

/**
 * Fixture de datos para tests/Visual/devengos.spec.ts (HU-28, tarea 40).
 * `devengos/show` es la primera pantalla del panel scoped por PERSONA, no
 * por rol/permiso — a diferencia de todas las demás specs visuales (que
 * entran con "Dueño", ver `elegirRolDueno` en ../helpers.ts), acá hace falta
 * un usuario de panel con `persona_id` real y, para capturar la tabla con
 * contenido (no solo el estado vacío), un devengo generado por el flujo real
 * (`ValidarSesion`, nunca un `DevengoPersonal::create()` directo — es lo
 * único que genera un devengo en la app real).
 *
 * Reusa `carlos.ferrufino` (rol `piloto` ya asignado por `PersonalDemoSeeder`) en
 * vez de crear un `SecUser` nuevo, y reusa el cliente/campo/lote/contrato/
 * orden de `NucleoComercialSeeder` (Agropecuaria San Jorge, lote L-01) en
 * vez de crear entidades comerciales nuevas: cualquier fila nueva en esas
 * tablas aparece en los listados SIN scoping de `clientes`/`campos`/
 * `contratos`/`ordenes`/`usuarios` y rompe sus snapshots de referencia. Solo
 * `per_personas` gana dos filas nuevas (la persona de Camila como piloto, y
 * un jefe para validar) — inevitable: no hay ninguna persona operativa
 * sembrada por los seeders demo existentes, y hace falta una para asociar a
 * `carlos.ferrufino` y otra, distinta, para validar su sesión (invariante 4 de
 * CLAUDE.md: validador ≠ piloto de esa sesión). `personas.spec.ts` actualizó
 * sus snapshots para reflejarlas.
 *
 * Bootea Laravel manualmente (mismo patrón que `public/index.php`/`artisan`)
 * en vez de pasar por `php artisan tinker`: Psy Shell, vía stdin o
 * `--execute`, no ejecuta este bloque de forma confiable (línea a línea, sin
 * agrupar el `if`). Un script PHP plano no tiene ese problema.
 *
 * Idempotente: `firstOrCreate`/`firstOrFail` para cliente/persona/rol, y un
 * chequeo previo de "ya hay un devengo este mes" antes de generar uno
 * nuevo — corridas repetidas de la suite no acumulan filas ni cambian el
 * total capturado. Se invoca desde `devengos.spec.ts` vía `docker compose
 * exec app php tests/Visual/fixtures/devengos-demo.php` (mismo patrón de
 * `docker compose exec` que ya usa `global-setup.ts`).
 */
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
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

$usuario = SecUser::where('username', 'carlos.ferrufino')->firstOrFail();
if ($usuario->persona_id !== $piloto->id) {
    $usuario->persona_id = $piloto->id;
    $usuario->save();
}

$yaHayDevengoEsteMes = DevengoPersonal::where('persona_id', $piloto->id)
    ->whereBetween('fecha', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
    ->exists();

if (! $yaHayDevengoEsteMes) {
    $trabajo = Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-demo-dev-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => now(),
    ]);
    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-demo-dev-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.50',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => now(),
        'fin' => now(),
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-demo-dev-'.uniqid(),
    ]);
    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);
}

echo "devengos-demo: OK\n";

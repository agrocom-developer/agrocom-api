<?php

/**
 * Fixture de datos para tests/Visual/pausas.spec.ts (HU-44, tarea 58).
 * `pausas/index` necesita al menos dos pausas de causas distintas para
 * capturar tanto la tabla de detalle como el tablero agregado por causa con
 * contenido real. Crea su propia cadena Cliente→Campo→Lote→Contrato→Orden→
 * Trabajo→Sesión con nombres/códigos propios de "demo pausas" — no toca
 * entidades de otra pantalla (mismo criterio que `gastos-demo.php`).
 *
 * Bootea Laravel manualmente, mismo patrón que `gastos-demo.php` — ver su
 * docblock para el porqué. Se invoca desde `pausas.spec.ts` vía
 * `docker compose exec app php tests/Visual/fixtures/pausas-demo.php`.
 *
 * Idempotente: la sesión se busca por su `uuid_cliente` fijo antes de crear
 * toda la cadena de nuevo.
 */
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Aplicacion\RegistrarPausa;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

const UUID_SESION_DEMO = 'uuid-sesion-demo-pausas-visual';

$sesion = Sesion::where('uuid_cliente', UUID_SESION_DEMO)->first();

if ($sesion === null) {
    $cliente = Cliente::firstOrCreate(['razon_social' => 'Cliente demo pausas']);
    $campo = Campo::firstOrCreate(['cliente_id' => $cliente->id, 'nombre' => 'Campo demo pausas']);
    $lote = Lote::firstOrCreate(['campo_id' => $campo->id, 'codigo' => 'L-DEMO-PAUSAS'], ['hectareas' => '80.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '80.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '800.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $trabajo = Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-demo-pausas-visual',
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T08:00:00-04:00',
    ]);
    $piloto = PerPersona::firstOrCreate(['nombre' => 'Piloto demo pausas'], ['rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $sesion = Sesion::create([
        'uuid_cliente' => UUID_SESION_DEMO,
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '15.00',
        'estado' => EstadoSesion::Abierto,
        'inicio' => '2026-09-01T08:05:00-04:00',
    ]);
}

if (! Pausa::where('sesion_id', $sesion->id)->exists()) {
    $registrarPausa = new RegistrarPausa;

    $registrarPausa->ejecutar($sesion->id, CausaPausa::ImprevistoDelCliente, '2026-09-15T08:00:00-04:00', '2026-09-15T12:20:00-04:00');
    $registrarPausa->ejecutar($sesion->id, CausaPausa::Clima, '2026-09-16T09:00:00-04:00', '2026-09-16T11:40:00-04:00');
    $registrarPausa->ejecutar($sesion->id, CausaPausa::FallaEquipo, '2026-09-17T07:00:00-04:00', '2026-09-17T08:10:00-04:00');
}

echo "pausas-demo: OK\n";

<?php

/**
 * Fixture de datos para tests/Visual/reporte-avance-comercial.spec.ts (HU-32,
 * tarea 46). Reusa el cliente/lote/orden/contrato de `NucleoComercialSeeder`
 * (Agropecuaria San Jorge, lote L-01, 4000.00 ha contratadas, Bs 65.00/ha) en
 * vez de crear un cliente/contrato nuevo, mismo criterio que
 * `facturas-demo.php`/`devengos-demo.php`: una fila nueva en
 * `com_clientes`/`com_contratos` aparecería SIN scoping en los listados de
 * `clientes`/`contratos` y rompería esos snapshots de referencia.
 *
 * Por la MISMA razón, tampoco crea una `PerPersona` ni una `Factura`
 * propias: `personas/index` y `facturas/index` también listan TODAS las
 * filas de su tabla sin scoping — ver `personas.spec.ts`/`facturas.spec.ts`.
 * En vez de eso:
 * - Reusa el piloto que ya crea `facturas-demo.php` ("Piloto Demo
 *   Facturas") vía `firstOrCreate` con el mismo nombre — converge a la
 *   MISMA fila sin importar cuál de los dos fixtures corra primero.
 * - Genera y firma (API real, mismo criterio que `facturas-demo.php`) un
 *   acta PROPIA de esta pantalla (`Trabajo`/`Sesion`/`Acta`/`Evidencia` no
 *   tienen spec visual propio, así que sumar filas ahí es seguro) para que
 *   "hectáreas aplicadas" no dependa por completo de otro fixture — pero
 *   NUNCA emite su propia factura: "hectáreas facturadas"/"monto
 *   facturado" en la captura salen enteros de la factura que
 *   `facturas-demo.php` ya emite sobre este mismo contrato compartido.
 *   Determinista porque `playwright.config.ts` corre con `workers: 1`/
 *   `fullyParallel: false` y los archivos de spec se ejecutan en orden
 *   alfabético — `facturas.spec.ts` corre antes que
 *   `reporte-avance-comercial.spec.ts`.
 *
 * Bootea Laravel manualmente, mismo patrón que `devengos-demo.php`/
 * `facturas-demo.php` (Psy Shell no ejecuta este bloque de forma confiable).
 * Se invoca desde `reporte-avance-comercial.spec.ts` vía `docker compose exec
 * app php tests/Visual/fixtures/reporte-avance-comercial-demo.php`.
 *
 * Idempotente: el acta nace con un `uuid_cliente` fijo; si ya existe, se
 * reusa en vez de generar de nuevo.
 */
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Aplicacion\FirmarActa;
use App\Dominios\Operaciones\Aplicacion\GenerarActaTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

Storage::fake('r2');

const NIT_DEMO = '1023456022';

$cliente = Cliente::where('nit', NIT_DEMO)->firstOrFail();
$lote = Lote::whereHas('campo', fn ($consulta) => $consulta->where('cliente_id', $cliente->id))
    ->where('codigo', 'L-01')
    ->firstOrFail();
$orden = OrdenAplicacion::where('lote_id', $lote->id)->where('nro_aplicacion', 1)->firstOrFail();

// Mismo piloto que ya crea `facturas-demo.php` — converge a la misma fila
// vía `firstOrCreate`, nunca una `PerPersona` nueva (rompería `personas/index`).
$piloto = PerPersona::firstOrCreate(
    ['nombre' => 'Piloto Demo Facturas'],
    ['rol' => RolOperativoPersona::Piloto, 'activo' => true],
);

$uuidTrabajo = 'uuid-trabajo-demo-avance';
$trabajo = Trabajo::where('uuid_cliente', $uuidTrabajo)->first();

if ($trabajo === null) {
    $trabajo = Trabajo::create([
        'uuid_cliente' => $uuidTrabajo,
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '18.00',
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => now(),
        'fin' => now(),
    ]);

    Sesion::create([
        'uuid_cliente' => 'uuid-sesion-demo-avance',
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '18.00',
        'estado' => EstadoSesion::Validado,
        'inicio' => now(),
        'fin' => now(),
        'motivo_cierre' => 'completado',
    ]);
}

$acta = Acta::where('trabajo_id', $trabajo->id)->first();

if ($acta === null) {
    $acta = app(GenerarActaTrabajo::class)->ejecutar($trabajo, 'uuid-acta-demo-avance');
}

if ($acta->estado->value !== 'firmada') {
    $uuidEvidencia = 'uuid-firma-demo-avance';

    $evidencia = Evidencia::where('uuid_cliente', $uuidEvidencia)->first();
    if ($evidencia === null) {
        $evidencia = Evidencia::create([
            'uuid_cliente' => $uuidEvidencia,
            'tipo' => TipoEvidencia::FirmaActa,
            'archivo_url' => "evidencias/firma_acta/2026/09/{$uuidEvidencia}.jpg",
            'hash' => hash('sha256', $uuidEvidencia),
            'fecha' => now(),
        ]);
    }

    $acta = app(FirmarActa::class)->ejecutar($acta, $uuidEvidencia, 'Ing. Agrónoma Demo', now()->toIso8601String());
}

echo "reporte-avance-comercial-demo: OK\n";

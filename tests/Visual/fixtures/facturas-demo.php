<?php

/**
 * Fixture de datos para tests/Visual/facturas.spec.ts (HU-31, tarea 45).
 * `facturas/index` necesita al menos una factura emitida (no solo el estado
 * vacío) y `facturas/create` necesita al menos un acta firmada SIN facturar
 * todavía (para que el `<select>` no aparezca vacío) — por eso este fixture
 * arma DOS actas firmadas: una se factura acá mismo (para `index`), la otra
 * queda disponible (para `create`).
 *
 * Reusa el cliente/lote/orden de `NucleoComercialSeeder` (Agropecuaria San
 * Jorge, lote L-01), mismo criterio que `anticipos-demo.php`/`devengos-demo.php`:
 * evita filas nuevas en `com_clientes`/`com_campos`/`com_contratos`/
 * `ope_ordenes_aplicacion` que romperían los snapshots de esas pantallas.
 * Cada `Trabajo` nuevo apunta a esa misma orden con su propio `uuid_cliente`
 * — no hay restricción de unicidad que lo impida (varios trabajos pueden
 * compartir una orden).
 *
 * `Storage::fake('r2')` (mismo motivo que `Api/ActaConformidadTest.php`):
 * `GenerarActaTrabajo` escribe el PDF del acta en el disco `r2` (S3), que en
 * este entorno local no tiene credenciales reales (`R2_*` vacías en
 * `.env.example`) — fake evita depender de un bucket real solo para tener
 * datos de demo.
 *
 * `GenerarActaTrabajo`/`FirmarActa`/`EmitirFactura` se invocan por el
 * contenedor (`$app->make(...)`), nunca por HTTP: es el mismo criterio que
 * `RegistrarAnticipo` en `anticipos-demo.php` — el flujo de dominio real,
 * sin la capa de transporte.
 *
 * Bootea Laravel manualmente, mismo patrón que `devengos-demo.php` — ver su
 * docblock para el porqué (Psy Shell no ejecuta este bloque de forma
 * confiable). Se invoca desde `facturas.spec.ts` vía `docker compose exec
 * app php tests/Visual/fixtures/facturas-demo.php`.
 *
 * Idempotente: cada acta nace con un `uuid_cliente` fijo; si ya existe, se
 * reusa en vez de generar de nuevo. La factura de la primera acta también se
 * chequea antes de emitir.
 */
use App\Dominios\Comercial\Aplicacion\EmitirFactura;
use App\Dominios\Comercial\Dominio\Excepciones\ActaNoFacturable;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Factura;
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

$jefe = PerPersona::firstOrCreate(
    ['nombre' => 'Jefe Demo Facturas'],
    ['rol' => RolOperativoPersona::JefeCampo, 'activo' => true],
);
$piloto = PerPersona::firstOrCreate(
    ['nombre' => 'Piloto Demo Facturas'],
    ['rol' => RolOperativoPersona::Piloto, 'activo' => true],
);

/** Trabajo cerrado, con su sesión vigente validada, listo para generar/firmar el acta. */
function trabajoFirmableParaFacturasDemo(OrdenAplicacion $orden, PerPersona $piloto, PerPersona $jefe, string $sufijo, string $hectareas): Trabajo
{
    $uuidTrabajo = "uuid-trabajo-demo-facturas-{$sufijo}";
    $existente = Trabajo::where('uuid_cliente', $uuidTrabajo)->first();

    if ($existente !== null) {
        return $existente;
    }

    $trabajo = Trabajo::create([
        'uuid_cliente' => $uuidTrabajo,
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => now(),
        'fin' => now(),
    ]);

    Sesion::create([
        'uuid_cliente' => "uuid-sesion-demo-facturas-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoSesion::Validado,
        'inicio' => now(),
        'fin' => now(),
        'motivo_cierre' => 'completado',
    ]);

    return $trabajo;
}

function actaFirmadaParaFacturasDemo(OrdenAplicacion $orden, PerPersona $piloto, PerPersona $jefe, string $sufijo, string $hectareas): Acta
{
    $trabajo = trabajoFirmableParaFacturasDemo($orden, $piloto, $jefe, $sufijo, $hectareas);

    $acta = Acta::where('trabajo_id', $trabajo->id)->first();

    if ($acta === null) {
        $acta = app(GenerarActaTrabajo::class)->ejecutar($trabajo, "uuid-acta-demo-facturas-{$sufijo}");
    }

    if ($acta->estado->value === 'firmada') {
        return $acta;
    }

    $uuidEvidencia = "uuid-firma-demo-facturas-{$sufijo}";

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

    return app(FirmarActa::class)->ejecutar($acta, $uuidEvidencia, 'Ing. Agrónoma Demo', now()->toIso8601String());
}

$actaParaIndice = actaFirmadaParaFacturasDemo($orden, $piloto, $jefe, 'indice', '15.00');
$actaParaSelector = actaFirmadaParaFacturasDemo($orden, $piloto, $jefe, 'selector', '9.75');

$yaFacturada = Factura::where('acta_id', $actaParaIndice->id)->exists();
if (! $yaFacturada) {
    try {
        app(EmitirFactura::class)->ejecutar($actaParaIndice->id);
    } catch (ActaNoFacturable) {
        // Ya facturada por una corrida anterior que no se detectó arriba
        // (carrera improbable en un script de demo) — no hay nada más que
        // hacer, la factura ya existe.
    }
}

echo "facturas-demo: OK\n";

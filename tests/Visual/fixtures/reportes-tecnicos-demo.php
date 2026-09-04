<?php

/**
 * Fixture de datos para tests/Visual/reportes-tecnicos.spec.ts (HU-43, tarea
 * 57). `reportes-tecnicos/index` necesita al menos un reporte técnico ya
 * generado para capturar la tabla con contenido real (lote, cliente, fecha
 * de generación, botón de descarga).
 *
 * Reusa el cliente/lote/orden de `NucleoComercialSeeder` (Agropecuaria San
 * Jorge, lote L-01) — mismo criterio que `evidencias-trabajo-demo.php`/
 * `reporte-avance-comercial-demo.php`: evita una fila nueva en
 * `com_clientes`/`com_campos`/`com_contratos`/`ope_ordenes_aplicacion` que
 * rompería snapshots de otras pantallas (`clientes`, `campos`, `contratos`,
 * `ordenes`).
 *
 * `ReporteTecnico` se crea directo por Eloquent, sin pasar por
 * `FirmarActa`/`GenerarReporteTecnico`: esta pantalla solo lista filas ya
 * persistidas, no ejercita la generación automática (mismo criterio que
 * `tests/Feature/Operaciones/ReportesTecnicosPanelTest.php`).
 *
 * `Storage::fake('r2')` (mismo motivo que `evidencias-trabajo-demo.php`): el
 * disco `r2` no tiene credenciales reales en este entorno local, así que el
 * link "Descargar PDF" no resuelve contenido real — no afecta esta captura,
 * que no lo hace click, solo verifica que el botón esté presente.
 *
 * Bootea Laravel manualmente, mismo patrón que el resto de `fixtures/`. Se
 * invoca desde `reportes-tecnicos.spec.ts` vía `docker compose exec app php
 * tests/Visual/fixtures/reportes-tecnicos-demo.php`.
 *
 * Idempotente: el trabajo y el reporte nacen con un `uuid_cliente`/
 * `trabajo_id` fijo; si ya existen, se reusan en vez de reconstruir la
 * cadena.
 */
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\ReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

Storage::fake('r2');

const NIT_DEMO = '1023456022';
const UUID_TRABAJO = 'uuid-trabajo-demo-reportes-tecnicos';

$cliente = Cliente::where('nit', NIT_DEMO)->firstOrFail();
$lote = Lote::whereHas('campo', fn ($consulta) => $consulta->where('cliente_id', $cliente->id))
    ->where('codigo', 'L-01')
    ->firstOrFail();
$orden = OrdenAplicacion::where('lote_id', $lote->id)->where('nro_aplicacion', 1)->firstOrFail();

$trabajo = Trabajo::where('uuid_cliente', UUID_TRABAJO)->first();

if ($trabajo === null) {
    $trabajo = Trabajo::create([
        'uuid_cliente' => UUID_TRABAJO,
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '15.00',
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => now(),
        'fin' => now(),
    ]);
}

$reporte = ReporteTecnico::where('trabajo_id', $trabajo->id)->first();

if ($reporte === null) {
    $pdfPath = 'reportes_tecnicos/2026/09/uuid-reporte-demo-reportes-tecnicos.pdf';
    Storage::disk('r2')->put($pdfPath, 'contenido-demo-reportes-tecnicos');

    $reporte = ReporteTecnico::create([
        'trabajo_id' => $trabajo->id,
        'hora_inicio' => now(),
        'hora_fin' => now(),
        'pdf_path' => $pdfPath,
        'generado_en' => now(),
    ]);
}

echo "reportes-tecnicos-demo: OK\n";

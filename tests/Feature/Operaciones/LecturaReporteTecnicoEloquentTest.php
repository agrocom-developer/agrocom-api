<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Contratos\LecturaReporteTecnico;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\ReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Contrato de lectura `Operaciones\Contratos\LecturaReporteTecnico` (HU-43,
 * tarea 57): resuelve `contrato_id` subiendo `ReporteTecnico.trabajo_id →
 * Trabajo.orden_id → OrdenAplicacion.contrato_id`, mismo criterio que
 * `LecturaActaConformadaEloquent`. Test "unitario" en el sentido de probar
 * la clase directo (sin HTTP) — vive en Feature porque tests/Unit de este
 * repo es PHPUnit puro, sin Eloquent (ver tests/Pest.php).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function ordenParaLecturaReporte(string $sufijo): OrdenAplicacion
{
    $cliente = Cliente::create(['razon_social' => "Cliente lectura reporte {$sufijo}", 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => "Campo lectura reporte {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-LEC-{$sufijo}", 'hectareas' => '20.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '20.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '200.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '20.00']);

    return $orden;
}

function trabajoConReporte(string $sufijo, string $generadoEn): ReporteTecnico
{
    $orden = ordenParaLecturaReporte($sufijo);

    $trabajo = Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-lectura-reporte-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => (int) $orden->ordenLotes()->value('lote_id'),
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '20.00',
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-09-01T08:00:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
    ]);

    return ReporteTecnico::create([
        'trabajo_id' => $trabajo->id,
        'hora_inicio' => '2026-09-01T08:05:00-04:00',
        'hora_fin' => '2026-09-01T11:00:00-04:00',
        'pdf_path' => "reportes_tecnicos/2026/09/uuid-lectura-reporte-{$sufijo}.pdf",
        'generado_en' => $generadoEn,
    ]);
}

it('resuelve el contrato_id de cada reporte subiendo la cadena trabajo → orden', function () {
    $reporte = trabajoConReporte('resolucion', '2026-09-02T10:00:00-04:00');
    $orden = OrdenAplicacion::query()->findOrFail(Trabajo::query()->findOrFail($reporte->trabajo_id)->orden_id);

    $datos = app(LecturaReporteTecnico::class)->listarTodos();
    $fila = collect($datos)->firstWhere('reporteId', $reporte->id);

    expect($fila)->not->toBeNull()
        ->and($fila->trabajoId)->toBe($reporte->trabajo_id)
        ->and($fila->contratoId)->toBe($orden->contrato_id)
        ->and($fila->pdfPath)->toBe($reporte->pdf_path);
});

it('lista todos los reportes existentes, ordenados por generado_en descendente', function () {
    $viejo = trabajoConReporte('orden-viejo', '2026-09-01T09:00:00-04:00');
    $nuevo = trabajoConReporte('orden-nuevo', '2026-09-03T09:00:00-04:00');

    $ids = collect(app(LecturaReporteTecnico::class)->listarTodos())->pluck('reporteId')->all();

    expect($ids)->toBe([$nuevo->id, $viejo->id]);
});

it('sin reportes generados, devuelve una lista vacía', function () {
    expect(app(LecturaReporteTecnico::class)->listarTodos())->toBe([]);
});

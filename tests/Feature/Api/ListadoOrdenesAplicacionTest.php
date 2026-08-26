<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * GET /api/ordenes (espec §8) — listado con filtros para la app piloto.
 * La demo siembra exactamente una orden vigente (nro_aplicacion 1, lote L-01);
 * cada test agrega lo que necesita encima de ese punto de partida.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
});

/**
 * Crea una orden adicional sobre el contrato y el lote L-02 de la demo.
 * Ojo con el índice parcial "una sola vigente por lote": las órdenes vigentes
 * extra deben ir en lotes distintos de L-01.
 *
 * @param  array<string, mixed>  $atributos
 */
function crearOrdenDemo(array $atributos = []): OrdenAplicacion
{
    return OrdenAplicacion::query()->create([
        'contrato_id' => Contrato::query()->value('id'),
        'lote_id' => Lote::query()->where('codigo', 'L-02')->value('id'),
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-08-26',
        'estado' => EstadoOrdenAplicacion::Emitida,
        ...$atributos,
    ]);
}

it('lista las órdenes paginadas con los campos del esquema', function () {
    $this->getJson('/api/ordenes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure([
            'data' => [[
                'id',
                'contrato_id',
                'lote_id',
                'nro_aplicacion',
                'litros_ha',
                'humedad_min_pct',
                'viento_max_kmh',
                'temperatura_max_c',
                'humedad_max_pct',
                'velocidad_max_kmh',
                'altura_vuelo_m',
                'velocidad_vuelo_kmh',
                'ancho_pasada_m',
                'observaciones',
                'emitida_por_contacto_id',
                'fecha_emision',
                'estado',
                'created_at',
                'updated_at',
            ]],
            'links',
            'meta' => ['current_page', 'per_page', 'total'],
        ])
        // Los DECIMAL viajan como string, nunca float (invariante 6).
        ->assertJsonPath('data.0.litros_ha', '10.00')
        ->assertJsonPath('data.0.estado', 'vigente');
});

it('filtra por estado', function () {
    crearOrdenDemo(['estado' => EstadoOrdenAplicacion::Consumida]);

    $this->getJson('/api/ordenes?estado=consumida')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.estado', 'consumida');
});

it('filtra por lote_id', function () {
    $orden = crearOrdenDemo();

    $this->getJson('/api/ordenes?lote_id='.$orden->lote_id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.lote_id', $orden->lote_id)
        ->assertJsonPath('data.0.id', $orden->id);
});

it('filtra por contrato_id', function () {
    $cliente = Cliente::query()->firstOrFail();

    $otroContrato = Contrato::query()->create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '65.00',
        'monto_total' => '6500.00',
        'fecha_inicio' => '2026-08-01',
        'estado' => 'borrador',
    ]);

    $orden = crearOrdenDemo(['contrato_id' => $otroContrato->id]);

    $this->getJson('/api/ordenes?contrato_id='.$otroContrato->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.contrato_id', $otroContrato->id)
        ->assertJsonPath('data.0.id', $orden->id);
});

it('filtra por nro_aplicacion', function () {
    $orden = crearOrdenDemo(['nro_aplicacion' => 2]);

    $this->getJson('/api/ordenes?nro_aplicacion=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nro_aplicacion', 2)
        ->assertJsonPath('data.0.id', $orden->id);
});

it('con vigentes=1 devuelve solo las órdenes vigentes', function () {
    crearOrdenDemo(['estado' => EstadoOrdenAplicacion::Emitida]);
    crearOrdenDemo([
        'estado' => EstadoOrdenAplicacion::Vencida,
        'lote_id' => Lote::query()->where('codigo', 'L-03')->value('id'),
    ]);

    $respuesta = $this->getJson('/api/ordenes?vigentes=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.estado', 'vigente');

    expect(collect($respuesta->json('data'))->pluck('estado')->unique()->all())
        ->toBe(['vigente']);
});

it('pagina el listado respetando per_page y page', function () {
    foreach ([2, 3, 4, 5] as $nro) {
        crearOrdenDemo(['nro_aplicacion' => $nro]);
    }

    $this->getJson('/api/ordenes?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5);
});

it('rechaza un estado desconocido con 422', function () {
    $this->getJson('/api/ordenes?estado=firmada')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('estado');
});

it('rechaza un per_page fuera de rango con 422', function () {
    $this->getJson('/api/ordenes?per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('excluye del listado las órdenes borradas lógicamente', function () {
    OrdenAplicacion::query()->firstOrFail()->delete();

    $this->getJson('/api/ordenes')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

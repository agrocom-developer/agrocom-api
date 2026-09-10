<?php

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Aplicacion\GuardarSiembraCampania;
use App\Dominios\Comercial\Contratos\LecturaCultivoLote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Contrato de lectura `Comercial\Contratos\LecturaCultivoLote` (HU-48, tarea
 * 71, etapa 2): frontera de lectura para que el informe de avance por
 * cultivo (tarea 75) agrupe sin importar los modelos Eloquent de este
 * módulo. Test "unitario" en el sentido de probar la clase directo (sin
 * HTTP) — mismo criterio que LecturaContratoEloquentTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

it('devuelve vacío para una campaña sin ninguna siembra cargada', function () {
    $cliente = Cliente::query()->create(['razon_social' => 'Cliente sin siembra', 'tipo_persona' => 'juridica']);
    $campania = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);

    expect(app(LecturaCultivoLote::class)->porCampania($campania->id))->toBe([]);
});

it('resuelve el cultivo y las hectáreas de cada lote sembrado en una campaña', function () {
    $cliente = Cliente::query()->create(['razon_social' => 'Cliente con siembra', 'tipo_persona' => 'juridica']);
    $campania = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo con siembra']);
    $campo->lotes()->create(['codigo' => 'L-01', 'hectareas' => '20.00']);
    $campo->lotes()->create(['codigo' => 'L-02', 'hectareas' => '30.00']);
    $campo->refresh()->load('lotes');

    $soyaId = (int) Cultivo::query()->where('nombre', 'Soya')->value('id');
    $maizId = (int) Cultivo::query()->where('nombre', 'Maíz')->value('id');

    app(GuardarSiembraCampania::class)->ejecutar($campo, $campania->id, [
        ['lote_id' => $campo->lotes[0]->id, 'cultivo_id' => $soyaId, 'hectareas_sembradas' => '18.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
        ['lote_id' => $campo->lotes[1]->id, 'cultivo_id' => $maizId, 'hectareas_sembradas' => '25.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);

    $siembras = collect(app(LecturaCultivoLote::class)->porCampania($campania->id))->keyBy('loteCodigo');

    expect($siembras)->toHaveCount(2)
        ->and($siembras['L-01']->cultivoNombre)->toBe('Soya')
        ->and($siembras['L-01']->hectareasSembradas)->toBe('18.00')
        ->and($siembras['L-02']->cultivoNombre)->toBe('Maíz')
        ->and($siembras['L-02']->hectareasSembradas)->toBe('25.00');
});

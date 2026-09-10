<?php

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Aplicacion\GuardarSiembraCampania;
use App\Dominios\Comercial\Contratos\LecturaCultivoLote;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaDeOtroCliente;
use App\Dominios\Comercial\Dominio\Excepciones\HectareasSembradasSuperanLote;
use App\Dominios\Comercial\Dominio\Excepciones\SiembraDuplicada;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * `Comercial\Aplicacion\GuardarSiembraCampania` (HU-48, tarea 71, etapa 2):
 * qué se sembró en cada lote, en cada campaña. Test "unitario" en el
 * sentido de probar la clase directo (sin HTTP) — mismo criterio que
 * tests/Feature/Comercial/LecturaContratoEloquentTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function clienteParaSiembra(string $razonSocial = 'Agropecuaria Siembra S.R.L.'): Cliente
{
    return Cliente::query()->create(['razon_social' => $razonSocial, 'tipo_persona' => 'juridica']);
}

function campaniaParaSiembra(int $clienteId, string $codigo = '2025-2026'): Campania
{
    return Campania::query()->create([
        'cliente_id' => $clienteId,
        'codigo' => $codigo,
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);
}

function campoConLoteParaSiembra(int $clienteId, string $hectareasLote = '20.00'): Campo
{
    $propiedad = Propiedad::create(['cliente_id' => $clienteId, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo de prueba siembra']);
    $campo->lotes()->create(['codigo' => 'L-01', 'hectareas' => $hectareasLote]);

    return $campo->refresh()->load('lotes');
}

function cultivoIdParaSiembra(string $nombre): int
{
    return (int) Cultivo::query()->where('nombre', $nombre)->value('id');
}

it('siembra un lote con un cultivo dentro de una campaña', function () {
    $cliente = clienteParaSiembra();
    $campania = campaniaParaSiembra($cliente->id);
    $campo = campoConLoteParaSiembra($cliente->id);
    $lote = $campo->lotes->first();

    app(GuardarSiembraCampania::class)->ejecutar($campo, $campania->id, [
        [
            'lote_id' => $lote->id,
            'cultivo_id' => cultivoIdParaSiembra('Soya'),
            'hectareas_sembradas' => '15.00',
            'fecha_siembra' => '2025-11-01',
            'fecha_cosecha_estimada' => '2026-03-01',
        ],
    ]);

    $siembra = LoteCampania::query()->where('lote_id', $lote->id)->where('campania_id', $campania->id)->sole();

    expect((string) $siembra->hectareas_sembradas)->toBe('15.00')
        ->and($siembra->cultivo_id)->toBe(cultivoIdParaSiembra('Soya'));
});

it('reejecutar sobre la misma campaña actualiza la siembra existente, sin duplicarla', function () {
    $cliente = clienteParaSiembra();
    $campania = campaniaParaSiembra($cliente->id);
    $campo = campoConLoteParaSiembra($cliente->id);
    $lote = $campo->lotes->first();

    $guardar = app(GuardarSiembraCampania::class);

    $guardar->ejecutar($campo, $campania->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembra('Soya'), 'hectareas_sembradas' => '10.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);
    $guardar->ejecutar($campo, $campania->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembra('Maíz'), 'hectareas_sembradas' => '18.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);

    $siembra = LoteCampania::query()->where('lote_id', $lote->id)->where('campania_id', $campania->id)->sole();

    expect($siembra->cultivo_id)->toBe(cultivoIdParaSiembra('Maíz'))
        ->and((string) $siembra->hectareas_sembradas)->toBe('18.00');
});

it('el mismo lote con soya en la campaña A y maíz en la campaña B convive sin conflicto', function () {
    $cliente = clienteParaSiembra();
    $campaniaA = campaniaParaSiembra($cliente->id, '2025-2026');
    $campaniaB = campaniaParaSiembra($cliente->id, '2026-2027');
    $campo = campoConLoteParaSiembra($cliente->id);
    $lote = $campo->lotes->first();

    $guardar = app(GuardarSiembraCampania::class);

    $guardar->ejecutar($campo, $campaniaA->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembra('Soya'), 'hectareas_sembradas' => '12.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);
    $guardar->ejecutar($campo, $campaniaB->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembra('Maíz'), 'hectareas_sembradas' => '9.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);

    $siembraA = LoteCampania::query()->where('lote_id', $lote->id)->where('campania_id', $campaniaA->id)->sole();
    $siembraB = LoteCampania::query()->where('lote_id', $lote->id)->where('campania_id', $campaniaB->id)->sole();

    expect($siembraA->cultivo_id)->toBe(cultivoIdParaSiembra('Soya'))
        ->and($siembraB->cultivo_id)->toBe(cultivoIdParaSiembra('Maíz'));

    // Consultar la campaña A no devuelve el cultivo de la B.
    $lecturaA = app(LecturaCultivoLote::class)->porCampania($campaniaA->id);
    expect($lecturaA)->toHaveCount(1)
        ->and($lecturaA[0]->cultivoNombre)->toBe('Soya');
});

it('rechaza hectáreas sembradas por encima de las hectáreas del lote', function () {
    $cliente = clienteParaSiembra();
    $campania = campaniaParaSiembra($cliente->id);
    $campo = campoConLoteParaSiembra($cliente->id, hectareasLote: '10.00');
    $lote = $campo->lotes->first();

    app(GuardarSiembraCampania::class)->ejecutar($campo, $campania->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembra('Soya'), 'hectareas_sembradas' => '10.01', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);
})->throws(HectareasSembradasSuperanLote::class);

it('rechaza sembrar el mismo lote dos veces en la misma campaña, con un error traducido y no un QueryException', function () {
    $cliente = clienteParaSiembra();
    $campania = campaniaParaSiembra($cliente->id);
    $campo = campoConLoteParaSiembra($cliente->id);
    $lote = $campo->lotes->first();

    // Dos filas para el MISMO lote en una sola llamada: el índice único
    // parcial `com_lote_campania_lote_campania_unico` rechaza la segunda, y
    // GuardarSiembra la traduce a SiembraDuplicada — nunca un 500 crudo.
    app(GuardarSiembraCampania::class)->ejecutar($campo, $campania->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembra('Soya'), 'hectareas_sembradas' => '5.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
        ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembra('Maíz'), 'hectareas_sembradas' => '5.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);
})->throws(SiembraDuplicada::class);

it('no relanza como SiembraDuplicada una QueryException que no corresponde al índice único', function () {
    $cliente = clienteParaSiembra();
    $campania = campaniaParaSiembra($cliente->id);
    $campo = campoConLoteParaSiembra($cliente->id);
    $lote = $campo->lotes->first();

    // cultivo_id inexistente: viola la FK de com_lote_campania.cultivo_id,
    // una violación distinta a la del índice único — no debe traducirse.
    app(GuardarSiembraCampania::class)->ejecutar($campo, $campania->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => 999999, 'hectareas_sembradas' => '5.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);
})->throws(QueryException::class);

it('rechaza guardar contra una campaña de otro cliente', function () {
    $cliente = clienteParaSiembra('Cliente dueño del campo');
    $otroCliente = clienteParaSiembra('Otro cliente');
    $campaniaAjena = campaniaParaSiembra($otroCliente->id);
    $campo = campoConLoteParaSiembra($cliente->id);
    $lote = $campo->lotes->first();

    app(GuardarSiembraCampania::class)->ejecutar($campo, $campaniaAjena->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembra('Soya'), 'hectareas_sembradas' => '5.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);
})->throws(CampaniaDeOtroCliente::class);

it('una fila sin cultivo da de baja (soft delete) la siembra existente de ese lote', function () {
    $cliente = clienteParaSiembra();
    $campania = campaniaParaSiembra($cliente->id);
    $campo = campoConLoteParaSiembra($cliente->id);
    $lote = $campo->lotes->first();

    $guardar = app(GuardarSiembraCampania::class);

    $guardar->ejecutar($campo, $campania->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembra('Soya'), 'hectareas_sembradas' => '5.00', 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);
    $guardar->ejecutar($campo, $campania->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => null, 'hectareas_sembradas' => null, 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);

    expect(LoteCampania::query()->where('lote_id', $lote->id)->where('campania_id', $campania->id)->exists())->toBeFalse()
        ->and(LoteCampania::withTrashed()->where('lote_id', $lote->id)->where('campania_id', $campania->id)->exists())->toBeTrue();
});

<?php

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Aplicacion\CrearCampo;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaDeOtroCliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * `Comercial\Aplicacion\CrearCampo` con `cultivo_id`/`campania_id` (HU-72,
 * tarea 88): el generador de alta masiva del formulario solo rellena el
 * array `lotes[]` que `CrearCampo` ya aceptaba (tarea 35) — la parte nueva es
 * que, con un cultivo por defecto, cada lote recién creado se siembra en la
 * campaña elegida vía `GuardarSiembraCampania` (tarea 71), reusada tal cual.
 * Test "unitario" en el sentido de probar el caso de uso directo, sin HTTP —
 * mismo criterio que `GuardarSiembraCampaniaTest.php`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function clienteParaGenerador(string $razonSocial = 'Agropecuaria del Generador S.R.L.'): Cliente
{
    return Cliente::query()->create(['razon_social' => $razonSocial, 'tipo_persona' => 'juridica']);
}

function propiedadParaGenerador(int $clienteId, string $nombre = 'Propiedad del generador'): Propiedad
{
    return Propiedad::query()->create(['cliente_id' => $clienteId, 'nombre' => $nombre]);
}

function campaniaParaGenerador(int $clienteId, string $codigo = '2025-2026'): Campania
{
    return Campania::query()->create([
        'cliente_id' => $clienteId,
        'codigo' => $codigo,
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);
}

function cultivoIdParaGenerador(string $nombre = 'Soya'): int
{
    return (int) Cultivo::query()->where('nombre', $nombre)->value('id');
}

/** @return list<array{codigo: string, hectareas: string, geometria: null, restricciones: null}> */
function lotesGeneradosParaGenerador(int $cantidad, string $hectareas): array
{
    return array_map(
        fn (int $numero): array => [
            'codigo' => "Lote {$numero}",
            'hectareas' => $hectareas,
            'geometria' => null,
            'restricciones' => null,
        ],
        range(1, $cantidad),
    );
}

it('genera N lotes y siembra cada uno en la campaña elegida cuando viene un cultivo por defecto', function () {
    $cliente = clienteParaGenerador();
    $propiedad = propiedadParaGenerador($cliente->id);
    $campania = campaniaParaGenerador($cliente->id);
    $cultivoId = cultivoIdParaGenerador('Soya');

    $campo = app(CrearCampo::class)->ejecutar(
        $propiedad->id,
        'Campo Generado',
        lotesGeneradosParaGenerador(3, '10.00'),
        $cultivoId,
        $campania->id,
    );

    expect($campo->lotes()->count())->toBe(3);

    $siembras = LoteCampania::query()->where('campania_id', $campania->id)->get();

    expect($siembras)->toHaveCount(3);

    foreach ($campo->lotes as $lote) {
        $siembra = $siembras->firstWhere('lote_id', $lote->id);

        expect($siembra)->not->toBeNull()
            ->and($siembra->cultivo_id)->toBe($cultivoId)
            ->and((string) $siembra->hectareas_sembradas)->toBe((string) $lote->hectareas);
    }
});

it('sin cultivo por defecto no crea ninguna fila de siembra: el camino manual de siempre sigue igual', function () {
    $cliente = clienteParaGenerador();
    $propiedad = propiedadParaGenerador($cliente->id);

    $campo = app(CrearCampo::class)->ejecutar(
        $propiedad->id,
        'Campo Sin Siembra',
        lotesGeneradosParaGenerador(2, '5.00'),
    );

    expect($campo->lotes()->count())->toBe(2)
        ->and(LoteCampania::query()->count())->toBe(0);
});

it('rechaza una campaña de otro cliente y no deja ni el campo ni sus lotes a medio crear', function () {
    $cliente = clienteParaGenerador('Cliente dueño de la propiedad');
    $otroCliente = clienteParaGenerador('Otro cliente');
    $propiedad = propiedadParaGenerador($cliente->id);
    $campaniaAjena = campaniaParaGenerador($otroCliente->id);
    $cultivoId = cultivoIdParaGenerador('Soya');

    app(CrearCampo::class)->ejecutar(
        $propiedad->id,
        'Campo Rechazado',
        lotesGeneradosParaGenerador(2, '5.00'),
        $cultivoId,
        $campaniaAjena->id,
    );
})->throws(CampaniaDeOtroCliente::class);

it('la transacción completa se revierte cuando la campaña es de otro cliente', function () {
    $cliente = clienteParaGenerador('Cliente dueño de la propiedad');
    $otroCliente = clienteParaGenerador('Otro cliente');
    $propiedad = propiedadParaGenerador($cliente->id);
    $campaniaAjena = campaniaParaGenerador($otroCliente->id);
    $cultivoId = cultivoIdParaGenerador('Soya');

    try {
        app(CrearCampo::class)->ejecutar(
            $propiedad->id,
            'Campo Rechazado',
            lotesGeneradosParaGenerador(2, '5.00'),
            $cultivoId,
            $campaniaAjena->id,
        );
    } catch (CampaniaDeOtroCliente) {
        // Esperado — lo que importa acá es el estado de la base después.
    }

    expect(Campo::query()->count())->toBe(0)
        ->and(Lote::query()->count())->toBe(0);
});

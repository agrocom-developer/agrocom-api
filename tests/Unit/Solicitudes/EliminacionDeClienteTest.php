<?php

use App\Dominios\Solicitudes\Aplicacion\EliminacionDeCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * Lo que Solicitudes aporta a la eliminación de una cuenta de cliente (ADR 0047, puntos 3 y 6): un servicio adjudicado o en
 * ejecución la impide; al ejecutarla se borran la empresa, los contactos, los terrenos sin servicio y las fotos, y en los
 * servicios registrados se borran el contorno de los lotes, los accesos, las observaciones, el municipio y la comunidad.
 */

uses(TestCase::class);

const EC_USUARIO = 501;

function ecMundo(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table', 'create_sol_zonas_table', 'create_sol_clientes_table', 'create_sol_terrenos_table',
        'create_prv_proveedores_table', 'create_sol_solicitudes_servicio_table', 'create_sol_terreno_fotos_table',
        'crea_sol_cliente_contactos', 'agrega_ubicacion_a_sol_terrenos', 'agrega_ubicacion_a_sol_solicitudes_servicio',
        'agrega_negociacion_a_sol_solicitudes_servicio', 'retira_zonas',
    ]);

    foreach (['sol_solicitudes_servicio', 'sol_terreno_fotos', 'sol_cliente_contactos', 'sol_terrenos', 'sol_clientes'] as $tabla) {
        DB::table($tabla)->delete();
    }

    $ahora = now();
    DB::table('sol_clientes')->insert(['id' => 1, 'usuario_id' => EC_USUARIO, 'tipo' => 'empresa', 'razon_social' => 'Agro Antelo SRL', 'documento_fiscal' => '1020304050', 'telefono' => '+59170012345', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('sol_cliente_contactos')->insert(['id' => 1, 'cliente_id' => 1, 'nombre' => 'Marta Soliz', 'telefono' => '70099999', 'es_principal' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);

    foreach ([[1, 'Lote norte'], [2, 'Lote sur']] as [$id, $nombre]) {
        DB::table('sol_terrenos')->insert([
            'id' => $id, 'cliente_id' => 1, 'nombre' => $nombre, 'geometria' => json_encode(['type' => 'Polygon']), 'hectareas' => '10.0000',
            'referencias_acceso' => 'Por el camino del molino', 'colindantes_sensibles' => 'Apiario', 'municipio' => 'Cotoca', 'comunidad' => 'San José',
            'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
    }

    Storage::fake('local');
    Storage::disk('local')->put('terrenos/1/foto.jpg', 'x');
    DB::table('sol_terreno_fotos')->insert(['terreno_id' => 1, 'archivo' => 'terrenos/1/foto.jpg', 'hash' => str_repeat('a', 64), 'bytes' => 1, 'created_at' => $ahora, 'updated_at' => $ahora]);
}

function ecSolicitud(int $id, int $terreno, string $estado): void
{
    DB::table('sol_solicitudes_servicio')->insert([
        'id' => $id, 'uuid_cliente' => "00000000-0000-4000-8000-00000000000{$id}", 'cliente_id' => 1, 'terreno_id' => $terreno, 'tipo_aplicacion' => 'fumigacion',
        'hectareas_solicitadas' => '10.0000', 'producto_indicado' => 'Glifosato', 'ventana_desde' => '2026-12-01', 'ventana_hasta' => '2026-12-10',
        'observaciones' => 'Llamar antes de llegar', 'estado' => $estado, 'contacto_id' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('un servicio adjudicado o en ejecución impide eliminar; uno finalizado o abierto no', function (string $estado, array $esperado) {
    ecMundo();
    ecSolicitud(1, 1, $estado);

    expect(app(EliminacionDeCliente::class)->impedimentos(EC_USUARIO))->toBe($esperado);
})->with([
    'adjudicada' => ['adjudicada', ['servicio_en_curso']],
    'en ejecución' => ['en_ejecucion', ['servicio_en_curso']],
    'finalizada' => ['finalizada', []],
    'abierta' => ['abierta', []],
    'cancelada' => ['cancelada', []],
]);

it('otra cuenta no tiene impedimentos por los servicios del cliente', function () {
    ecMundo();
    ecSolicitud(1, 1, 'en_ejecucion');

    expect(app(EliminacionDeCliente::class)->impedimentos(999))->toBe([]);
});

it('al ejecutar borra la empresa, los contactos, los terrenos sin servicio y las fotos, y limpia los servicios que se conservan', function () {
    ecMundo();
    ecSolicitud(1, 1, 'finalizada');

    app(EliminacionDeCliente::class)->anonimizar(EC_USUARIO);

    $cliente = DB::table('sol_clientes')->first();
    expect($cliente->razon_social)->toBe('Cuenta eliminada')->and($cliente->documento_fiscal)->toBeNull()->and($cliente->telefono)->toBeNull();
    expect(DB::table('sol_cliente_contactos')->count())->toBe(0);
    expect(DB::table('sol_terreno_fotos')->count())->toBe(0);
    Storage::disk('local')->assertMissing('terrenos/1/foto.jpg');

    // El terreno 2 no tenía servicio: se borra. El 1 sí: se conserva sin contorno, accesos, municipio ni comunidad.
    expect(DB::table('sol_terrenos')->pluck('id')->all())->toBe([1]);
    $terreno = DB::table('sol_terrenos')->first();
    expect($terreno->geometria)->toBeNull()->and($terreno->referencias_acceso)->toBeNull()->and($terreno->colindantes_sensibles)->toBeNull()
        ->and($terreno->municipio)->toBeNull()->and($terreno->comunidad)->toBeNull()->and($terreno->nombre)->toBe('Terreno eliminado');

    // El servicio se conserva como registro del resultado, sin sus observaciones ni el contacto.
    $servicio = DB::table('sol_solicitudes_servicio')->first();
    expect($servicio->estado)->toBe('finalizada')->and($servicio->hectareas_solicitadas)->toEqual('10.0000')
        ->and($servicio->observaciones)->toBeNull()->and($servicio->contacto_id)->toBeNull();
});

it('anonimizar dos veces deja lo mismo', function () {
    ecMundo();
    ecSolicitud(1, 1, 'finalizada');

    $caso = app(EliminacionDeCliente::class);
    $caso->anonimizar(EC_USUARIO);
    $primera = [DB::table('sol_clientes')->get()->all(), DB::table('sol_terrenos')->get()->all()];
    $caso->anonimizar(EC_USUARIO);

    expect([DB::table('sol_clientes')->get()->all(), DB::table('sol_terrenos')->get()->all()])->toEqual($primera);
});

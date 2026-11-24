<?php

use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Personal\Contratos\LecturaPersonas;
use App\Dominios\Sincronizacion\Aplicacion\ObtenerCatalogoDesdeCursor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * Pull de catálogo de la app de campo (espec §2.1, punto 6; ADR 0037): los
 * «servicios asignados» —solicitudes aceptadas que el equipo del operario lleva al
 * lote— y los «retirados» —los que ya no debe llevar—, contra el ESQUEMA REAL (las
 * migraciones, en SQLite en memoria) y la lectura real de `Solicitudes`.
 *
 * Lo que se garantiza:
 *
 *   1. Solo llegan los servicios de los equipos del operario, en un estado de
 *      ejecución, con su terreno; nunca los de otro equipo ni los que aún no
 *      tienen proveedor.
 *   2. El cursor avanza: el pull siguiente no repite lo entregado y trae lo nuevo.
 *   3. Lo que dejó de estar en ejecución viaja en `servicios_retirados` y jamás en
 *      las dos secciones a la vez; con cursor vacío no hay retirados.
 *   4. Una cuenta sin equipo recibe todo vacío.
 *   5. Los límites de condiciones viajan siempre.
 */

uses(TestCase::class);

function catalogoEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
    ];

    static $instancias = [];

    foreach ($migraciones as $nombre) {
        $archivos = glob(database_path("migrations/*_{$nombre}.php")) ?: [];

        if (count($archivos) !== 1) {
            throw new RuntimeException("Se esperaba exactamente una migración {$nombre}.");
        }

        $instancias[$archivos[0]] ??= require $archivos[0];

        try {
            $instancias[$archivos[0]]->up();
        } catch (QueryException $excepcion) {
            if (! str_contains($excepcion->getMessage(), 'already exists')) {
                throw $excepcion;
            }
        }
    }
}

function catalogoTerreno(): int
{
    return DB::table('sol_terrenos')->insertGetId([
        'cliente_id' => 1,
        'nombre' => 'Lote Norte',
        'geometria' => json_encode(['type' => 'Polygon', 'coordinates' => [[[0, 0], [0, 1], [1, 1], [0, 0]]]]),
        'hectareas' => '120.5000',
        'cultivo' => 'Soya',
        'acceso_dificil' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function catalogoSolicitud(string $estado, ?int $equipoId, ?Carbon $modificada = null): int
{
    DB::table('sol_clientes')->insertOrIgnore(['id' => 1, 'usuario_id' => 55, 'tipo' => 'persona', 'razon_social' => 'Cliente Ana', 'telefono' => '70011122', 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()]);

    return DB::table('sol_solicitudes_servicio')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(),
        'cliente_id' => 1,
        'terreno_id' => catalogoTerreno(),
        'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '80.0000',
        'producto_indicado' => 'Glifosato',
        'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-01',
        'ventana_hasta' => '2026-12-10',
        'urgente' => false,
        'estado' => $estado,
        'equipo_id' => $equipoId,
        'created_at' => $modificada ?? now(),
        'updated_at' => $modificada ?? now(),
    ]);
}

/** El caso de uso con la flota y las personas acotadas a lo que importa acá. */
function catalogoPull(?string $cursor, ?int $usuarioId = 9, array $equiposVigentes = [7], array $equiposHistoricos = [7, 8]): array
{
    test()->mock(LecturaEquipos::class)
        ->shouldReceive('equiposDeCuenta')
        ->andReturnUsing(fn (int $usuario, bool $soloVigentes = true): array => $soloVigentes ? $equiposVigentes : $equiposHistoricos);
    test()->mock(LecturaPersonas::class)->shouldReceive('listarModificadosDesde')->andReturn([]);

    return app(ObtenerCatalogoDesdeCursor::class)->ejecutar($cursor, 5, $usuarioId);
}

beforeEach(function () {
    catalogoEsquema();
    DB::table('sol_solicitudes_servicio')->delete();
    DB::table('sol_terrenos')->delete();
    Carbon::setTestNow('2026-12-02 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('entrega los servicios en ejecución del equipo del operario, con su terreno', function () {
    $asignada = catalogoSolicitud('adjudicada', 7);
    catalogoSolicitud('en_ejecucion', 7);

    $catalogo = catalogoPull(null);

    expect($catalogo['servicios_asignados'])->toHaveCount(2)
        ->and($catalogo['servicios_asignados'][0]['id'])->toBe($asignada)
        ->and($catalogo['servicios_asignados'][0]['equipo_id'])->toBe(7)
        ->and($catalogo['servicios_asignados'][0]['hectareas_solicitadas'])->toBe('80.0000')
        ->and($catalogo['servicios_asignados'][0]['terreno']['nombre'])->toBe('Lote Norte')
        ->and($catalogo['servicios_asignados'][0]['terreno']['geometria']['type'])->toBe('Polygon')
        ->and($catalogo['servicios_asignados'][0]['terreno']['hectareas'])->toBe('120.5000')
        ->and($catalogo['servicios_retirados'])->toBe([]);
});

test('nunca entrega el servicio de otro equipo ni uno que todavía no tiene proveedor', function () {
    catalogoSolicitud('adjudicada', 99);
    catalogoSolicitud('publicada', null);
    catalogoSolicitud('en_asignacion', null);
    catalogoSolicitud('borrador', null);

    expect(catalogoPull(null)['servicios_asignados'])->toBe([]);
});

test('el cursor avanza: el pull siguiente no repite lo entregado y trae lo nuevo', function () {
    catalogoSolicitud('adjudicada', 7);
    $primero = catalogoPull(null);

    expect($primero['servicios_asignados'])->toHaveCount(1);

    $sinNovedad = catalogoPull($primero['cursor']);

    expect($sinNovedad['servicios_asignados'])->toBe([]);

    Carbon::setTestNow('2026-12-02 11:00:00');
    $nueva = catalogoSolicitud('en_ejecucion', 7);

    expect(array_column(catalogoPull($sinNovedad['cursor'])['servicios_asignados'], 'id'))->toBe([$nueva]);
});

test('lo que dejó de estar en ejecución viaja en retirados y no en asignados', function () {
    $cancelada = catalogoSolicitud('cancelada', 7);
    $ejecutada = catalogoSolicitud('finalizada', 7);
    $reasignada = catalogoSolicitud('abierta', 7);
    $viva = catalogoSolicitud('adjudicada', 7);

    // Un pull con cursor previo (el dispositivo ya tenía algo): trae los retirados desde el principio.
    $catalogo = catalogoPull(base64_encode((string) json_encode(['personas' => ['u' => '2026-01-01T00:00:00+00:00', 'id' => 0]])));

    $motivos = array_column($catalogo['servicios_retirados'], 'motivo', 'id');

    expect($motivos)->toBe([$cancelada => 'cancelada', $ejecutada => 'ejecutada', $reasignada => 'reasignada'])
        ->and(array_column($catalogo['servicios_asignados'], 'id'))->toBe([$viva]);
});

test('con cursor vacío no hay retirados: el dispositivo nunca tuvo nada que retirar', function () {
    catalogoSolicitud('cancelada', 7);

    expect(catalogoPull(null)['servicios_retirados'])->toBe([]);
});

test('un servicio de un equipo que el operario tuvo pero ya no integra se retira, no se entrega', function () {
    $deOtroEquipo = catalogoSolicitud('en_ejecucion', 8);

    $catalogo = catalogoPull(base64_encode((string) json_encode(['personas' => ['u' => '2026-01-01T00:00:00+00:00', 'id' => 0]])), equiposVigentes: [7], equiposHistoricos: [7, 8]);

    expect($catalogo['servicios_asignados'])->toBe([])
        ->and($catalogo['servicios_retirados'])->toBe([]);

    // Aun sin ser suyo hoy, si su estado deja de ser de ejecución se le avisa para que lo oculte.
    DB::table('sol_solicitudes_servicio')->where('id', $deOtroEquipo)->update(['estado' => 'cancelada', 'updated_at' => now()]);

    $despues = catalogoPull(base64_encode((string) json_encode(['personas' => ['u' => '2026-01-01T00:00:00+00:00', 'id' => 0]])), equiposVigentes: [7], equiposHistoricos: [7, 8]);

    expect(array_column($despues['servicios_retirados'], 'id'))->toBe([$deOtroEquipo]);
});

test('una cuenta sin equipo recibe todo vacío', function () {
    catalogoSolicitud('adjudicada', 7);

    $catalogo = catalogoPull(null, usuarioId: null);

    expect($catalogo['servicios_asignados'])->toBe([])
        ->and($catalogo['servicios_retirados'])->toBe([]);

    $sinEquipos = catalogoPull(null, 9, [], []);

    expect($sinEquipos['servicios_asignados'])->toBe([]);
});

test('los límites de condiciones viajan siempre, con decimales exactos', function () {
    expect(catalogoPull(null, usuarioId: null)['limites'])->toMatchArray([
        'viento_max_kmh' => '17.00',
        'temperatura_max_c' => '30.00',
        'humedad_max_pct' => '90.00',
    ]);
});

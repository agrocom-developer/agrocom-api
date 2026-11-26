<?php

use App\Dominios\Solicitudes\Aplicacion\GestionarTerrenos;
use App\Dominios\Solicitudes\Contratos\LecturaUbicaciones;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CrearTerrenoRequest;
use Database\Seeders\Catalogo\UbicacionesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * El catálogo oficial de departamentos y provincias de Bolivia (ADR 0045, punto 3): lo que
 * siembra el catálogo, cómo se publica sin token y cómo se valida la ubicación de un terreno.
 * La validación al publicar una solicitud vive en `SolicitudesDelClienteTest`. Contra el
 * ESQUEMA REAL (las migraciones, en SQLite en memoria).
 */

uses(TestCase::class);

function ubicacionesSembradas(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_clientes_table',
        'create_sol_zonas_table',
        'create_sol_terrenos_table',
        'create_sol_terreno_fotos_table',
        'create_sol_departamentos_table',
        'create_sol_provincias_table',
        'agrega_ubicacion_a_sol_terrenos',
    ]);

    // Otras pruebas del proceso dejan filas mínimas del catálogo: se parte del catálogo completo.
    DB::table('sol_provincias')->delete();
    DB::table('sol_departamentos')->delete();
    (new UbicacionesSeeder)->run();
}

/** @param  array<string, mixed>  $datos */
function erroresDeTerreno(array $datos): array
{
    $peticion = CrearTerrenoRequest::create('/api/terrenos', 'POST', $datos);

    return Validator::make($datos, $peticion->rules())->errors()->toArray();
}

test('el catálogo trae los 9 departamentos y las 112 provincias oficiales, cada provincia con el código de su departamento', function () {
    ubicacionesSembradas();

    expect(DB::table('sol_departamentos')->count())->toBe(9)
        ->and(DB::table('sol_provincias')->count())->toBe(112)
        ->and(DB::table('sol_provincias')->whereRaw('substr(codigo, 1, 2) != departamento_codigo')->count())->toBe(0)
        ->and(DB::table('sol_provincias')->where('departamento_codigo', '07')->count())->toBe(15)
        ->and(DB::table('sol_provincias')->where('departamento_codigo', '09')->count())->toBe(5)
        ->and(DB::table('sol_departamentos')->where('codigo', '07')->value('nombre'))->toBe('Santa Cruz')
        ->and(DB::table('sol_provincias')->where('codigo', '0702')->value('nombre'))->toBe('Warnes');
});

test('sembrar el catálogo dos o tres veces deja la base idéntica', function () {
    ubicacionesSembradas();
    $antes = [DB::table('sol_departamentos')->orderBy('codigo')->pluck('nombre', 'codigo')->all(), DB::table('sol_provincias')->orderBy('codigo')->pluck('nombre', 'codigo')->all()];

    (new UbicacionesSeeder)->run();
    (new UbicacionesSeeder)->run();

    expect(DB::table('sol_departamentos')->count())->toBe(9)
        ->and(DB::table('sol_provincias')->count())->toBe(112)
        ->and([DB::table('sol_departamentos')->orderBy('codigo')->pluck('nombre', 'codigo')->all(), DB::table('sol_provincias')->orderBy('codigo')->pluck('nombre', 'codigo')->all()])->toBe($antes);
});

test('GET /api/catalogos/ubicaciones responde sin token con los departamentos y sus provincias por código', function () {
    ubicacionesSembradas();

    $respuesta = $this->getJson('/api/catalogos/ubicaciones')->assertOk();
    $departamentos = $respuesta->json('data');

    expect($departamentos)->toHaveCount(9)
        ->and(array_column($departamentos, 'codigo'))->toBe(['01', '02', '03', '04', '05', '06', '07', '08', '09'])
        ->and(array_sum(array_map(fn (array $d): int => count($d['provincias']), $departamentos)))->toBe(112)
        ->and($departamentos[6])->toMatchArray(['codigo' => '07', 'nombre' => 'Santa Cruz'])
        ->and($departamentos[6]['provincias'][1])->toBe(['codigo' => '0702', 'nombre' => 'Warnes'])
        ->and(array_keys($departamentos[6]))->toBe(['codigo', 'nombre', 'provincias']);
});

test('el catálogo no se edita por la API: solo hay lectura', function () {
    ubicacionesSembradas();

    $this->postJson('/api/catalogos/ubicaciones', ['codigo' => '10', 'nombre' => 'Otro'])->assertStatus(405);
    $this->putJson('/api/catalogos/ubicaciones', [])->assertStatus(405);
    $this->deleteJson('/api/catalogos/ubicaciones')->assertStatus(405);
    expect(DB::table('sol_departamentos')->count())->toBe(9);
});

test('la lectura resuelve nombres y dice si una provincia es de su departamento', function () {
    ubicacionesSembradas();
    $lectura = app(LecturaUbicaciones::class);

    expect($lectura->nombreDeDepartamento('07'))->toBe('Santa Cruz')
        ->and($lectura->nombreDeDepartamento('99'))->toBeNull()
        ->and($lectura->nombreDeProvincia('0301'))->toBe('Cercado')
        ->and($lectura->provinciaEsDelDepartamento('07', '0702'))->toBeTrue()
        ->and($lectura->provinciaEsDelDepartamento('03', '0702'))->toBeFalse()
        ->and($lectura->provinciaEsDelDepartamento('07', '9999'))->toBeFalse();
});

test('un terreno acepta departamento y provincia del catálogo con municipio y comunidad como texto', function () {
    ubicacionesSembradas();
    $base = ['nombre' => 'Lote Norte', 'hectareas' => '10'];

    expect(erroresDeTerreno($base))->toBe([])
        ->and(erroresDeTerreno([...$base, 'departamento_codigo' => '07']))->toBe([])
        ->and(erroresDeTerreno([...$base, 'departamento_codigo' => '07', 'provincia_codigo' => '0702', 'municipio' => 'Warnes', 'comunidad' => 'Los Álamos']))->toBe([]);
});

test('un terreno rechaza un departamento inexistente, una provincia de otro departamento y una provincia sin departamento', function () {
    ubicacionesSembradas();
    $base = ['nombre' => 'Lote Norte', 'hectareas' => '10'];

    expect(erroresDeTerreno([...$base, 'departamento_codigo' => '10']))->toHaveKey('departamento_codigo')
        ->and(erroresDeTerreno([...$base, 'departamento_codigo' => '07', 'provincia_codigo' => '0301']))->toHaveKey('provincia_codigo')
        ->and(erroresDeTerreno([...$base, 'departamento_codigo' => '07', 'provincia_codigo' => '9999']))->toHaveKey('provincia_codigo')
        ->and(erroresDeTerreno([...$base, 'provincia_codigo' => '0702']))->toHaveKeys(['departamento_codigo', 'provincia_codigo'])
        ->and(erroresDeTerreno([...$base, 'municipio' => str_repeat('a', 121)]))->toHaveKey('municipio');
});

test('cambiar el departamento de un terreno sin elegir provincia suelta la provincia anterior', function () {
    ubicacionesSembradas();
    DB::table('sol_clientes')->insert(['id' => 1, 'usuario_id' => 301, 'tipo' => 'persona', 'razon_social' => 'Cliente', 'telefono' => '70000000', 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);
    $terrenos = app(GestionarTerrenos::class);
    $terreno = $terrenos->crear(301, ['nombre' => 'Lote', 'hectareas' => '10', 'departamento_codigo' => '07', 'provincia_codigo' => '0702', 'municipio' => 'Warnes']);

    $mismo = $terrenos->actualizar(301, $terreno->id, ['departamento_codigo' => '07', 'comunidad' => 'Los Álamos']);
    expect($mismo->provincia_codigo)->toBe('0702')->and($mismo->comunidad)->toBe('Los Álamos');

    $cambiado = $terrenos->actualizar(301, $terreno->id, ['departamento_codigo' => '03']);
    expect($cambiado->departamento_codigo)->toBe('03')->and($cambiado->provincia_codigo)->toBeNull();

    $conProvincia = $terrenos->actualizar(301, $terreno->id, ['departamento_codigo' => '07', 'provincia_codigo' => '0701']);
    expect($conProvincia->provincia_codigo)->toBe('0701');

    DB::table('sol_terrenos')->delete();
    DB::table('sol_clientes')->delete();
});

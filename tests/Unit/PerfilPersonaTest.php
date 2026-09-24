<?php

use App\Dominios\Personal\Contratos\LecturaDatosPersonales;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\PerfilController;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
 * «Mi perfil»: los datos de la persona vinculada a la cuenta, en solo lectura.
 * Contra el ESQUEMA REAL (las migraciones, en SQLite en memoria) y el contrato
 * de lectura real de Personal.
 *
 * Lo que se garantiza (categoría que CLAUDE.md pide cubrir: exposición de datos
 * entre usuarios — documento, celular y dirección son de su dueño):
 *
 *   1. La persona sale SIEMPRE de `persona_id` de la cuenta que mira. Dos cuentas,
 *      cada una ve los suyos y nunca los de la otra.
 *   2. Sin persona vinculada, o con una dada de baja, la tarjeta lo dice y no
 *      muestra nada — y son dos mensajes distintos.
 *   3. Lo que no está cargado se dice («Sin registrar»); no queda un hueco.
 *   4. Editar en Personal solo se ofrece a quien tiene `personal.persona.editar`, y
 *      siempre apunta a la persona de la propia cuenta.
 */

uses(TestCase::class);

function perfilEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'create_per_bases_table',
        'create_per_personas_table',
    ];

    // Cada archivo se incluye una sola vez por proceso: la migración es una clase anónima.
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
            // SQLite no conoce el índice parcial de Postgres de estas migraciones.
            if (! str_contains($excepcion->getMessage(), 'USING btree')) {
                throw $excepcion;
            }
        }
    }
}

/** @param  array<string, mixed>  $extra */
function perfilPersona(string $nombre, array $extra = []): int
{
    $ahora = now();

    return DB::table('per_personas')->insertGetId([
        'nombre' => $nombre,
        'rol' => 'piloto',
        'activo' => true,
        'created_at' => $ahora,
        'updated_at' => $ahora,
        ...$extra,
    ]);
}

function perfilCuenta(?int $personaId): SecUser
{
    static $secuencia = 0;
    $secuencia++;
    $ahora = now();

    $id = DB::table('sec_user')->insertGetId([
        'name' => "Cuenta {$secuencia}",
        'username' => "perfil.{$secuencia}",
        'password' => 'no-se-usa',
        'type' => TipoUsuario::Interno->value,
        'state' => true,
        'persona_id' => $personaId,
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);

    return SecUser::withTrashed()->findOrFail($id);
}

/** @return array{titulo: string, tieneDatos: bool, items: list<array<string, mixed>>, nota: string, editarHref: ?string, vacioTitulo: string, vacioDetalle: string} */
function perfilTarjeta(SecUser $cuenta, bool $puedeEditar = false): array
{
    $metodo = new ReflectionMethod(PerfilController::class, 'tarjetaPersona');

    return $metodo->invoke(new PerfilController, $cuenta, app(LecturaDatosPersonales::class), $puedeEditar);
}

/** @param  array{items: list<array<string, mixed>>}  $tarjeta */
function perfilValor(array $tarjeta, string $etiqueta): ?string
{
    foreach ($tarjeta['items'] as $item) {
        if ($item['label'] === $etiqueta) {
            return $item['value'];
        }
    }

    return null;
}

beforeEach(function () {
    perfilEsquema();
});

// ── 1. Cada cuenta ve solo lo suyo ───────────────────────────────────────────────

test('cada cuenta ve los datos de su propia persona y nunca los de otra', function () {
    $baseId = DB::table('per_bases')->insertGetId(['nombre' => 'Base Norte', 'created_at' => now(), 'updated_at' => now()]);
    $ana = perfilPersona('Ana Pérez', ['ci' => '1111111', 'celular' => '70000001', 'correo' => 'ana@demo.test', 'direccion' => 'Calle Uno 1', 'base_id' => $baseId]);
    $beto = perfilPersona('Beto Soto', ['ci' => '2222222', 'celular' => '70000002', 'correo' => 'beto@demo.test', 'direccion' => 'Calle Dos 2']);

    $deAna = perfilTarjeta(perfilCuenta($ana));
    $deBeto = perfilTarjeta(perfilCuenta($beto));

    expect(perfilValor($deAna, 'Nombre'))->toBe('Ana Pérez')
        ->and(perfilValor($deAna, 'Documento (CI)'))->toBe('1111111')
        ->and(perfilValor($deAna, 'Base'))->toBe('Base Norte')
        ->and(perfilValor($deBeto, 'Nombre'))->toBe('Beto Soto')
        ->and(perfilValor($deBeto, 'Documento (CI)'))->toBe('2222222')
        ->and(json_encode($deAna))->not->toContain('Beto')->not->toContain('2222222')->not->toContain('70000002')
        ->and(json_encode($deBeto))->not->toContain('Ana')->not->toContain('1111111')->not->toContain('70000001');
});

test('la lectura de Personal da solo la persona pedida y nada de otras', function () {
    $ana = perfilPersona('Ana Pérez', ['ci' => '1111111']);
    perfilPersona('Beto Soto', ['ci' => '2222222']);

    $datos = app(LecturaDatosPersonales::class)->dePersona($ana);

    expect($datos->id)->toBe($ana)
        ->and($datos->nombre)->toBe('Ana Pérez')
        ->and($datos->ci)->toBe('1111111')
        ->and(app(LecturaDatosPersonales::class)->dePersona(999))->toBeNull();
});

// ── 2. Sin persona, o dada de baja ─────────────────────────────────────────────

test('sin persona vinculada la tarjeta lo dice y no muestra datos', function () {
    $tarjeta = perfilTarjeta(perfilCuenta(null));

    expect($tarjeta['tieneDatos'])->toBeFalse()
        ->and($tarjeta['items'])->toBe([])
        ->and($tarjeta['vacioTitulo'])->toBe('Tu cuenta no está vinculada a una persona')
        ->and($tarjeta['editarHref'])->toBeNull();
});

test('con la persona dada de baja no muestra sus datos y lo dice distinto', function () {
    $id = perfilPersona('Ana Pérez', ['ci' => '1111111', 'deleted_at' => now()]);

    $tarjeta = perfilTarjeta(perfilCuenta($id), puedeEditar: true);

    expect($tarjeta['tieneDatos'])->toBeFalse()
        ->and($tarjeta['items'])->toBe([])
        ->and($tarjeta['vacioTitulo'])->toBe('La persona vinculada a tu cuenta ya no está activa')
        ->and($tarjeta['editarHref'])->toBeNull()
        ->and(json_encode($tarjeta))->not->toContain('1111111');
});

// ── 3. Lo que no está cargado ──────────────────────────────────────────────────

test('lo que no está cargado se dice, en vez de dejar un hueco', function () {
    $id = perfilPersona('Ana Pérez', ['ci' => '  ', 'celular' => null, 'correo' => '', 'direccion' => null]);

    $tarjeta = perfilTarjeta(perfilCuenta($id));

    expect(perfilValor($tarjeta, 'Documento (CI)'))->toBe('Sin registrar')
        ->and(perfilValor($tarjeta, 'Celular'))->toBe('Sin registrar')
        ->and(perfilValor($tarjeta, 'Correo'))->toBe('Sin registrar')
        ->and(perfilValor($tarjeta, 'Dirección'))->toBe('Sin registrar')
        ->and(perfilValor($tarjeta, 'Base'))->toBe('Sin base asignada')
        ->and(perfilValor($tarjeta, 'Cargo'))->toBe('Piloto');
});

// ── 4. Editar en Personal ──────────────────────────────────────────────────────

test('solo quien puede editar personas ve el acceso a Personal, y lleva a su propia persona', function () {
    $ana = perfilPersona('Ana Pérez');
    perfilPersona('Beto Soto');
    $cuenta = perfilCuenta($ana);

    $conPermiso = perfilTarjeta($cuenta, puedeEditar: true);
    $sinPermiso = perfilTarjeta($cuenta, puedeEditar: false);

    expect($conPermiso['editarHref'])->toEndWith("/panel/personas/{$ana}/editar")
        ->and($conPermiso['nota'])->toBe('Solo lectura en esta pantalla. Puedes corregirlos desde Personal.')
        ->and($sinPermiso['editarHref'])->toBeNull()
        ->and($sinPermiso['nota'])->toBe('Solo lectura. Si algo no está correcto, pídele a un administrador que lo corrija.');
});

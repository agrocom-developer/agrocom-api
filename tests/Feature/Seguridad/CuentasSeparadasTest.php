<?php

use App\Dominios\Seguridad\Aplicacion\EmitirTokenDispositivo;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioApp;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\Soporte\Esquema;

/*
 * Cuentas separadas por app (ADR 0046, fase A2): `sec_user.tipo_cuenta` vale `panel`,
 * `cliente` o `proveedor`. El panel autentica solo cuentas `panel`; el token de
 * dispositivo se emite solo a cuentas `cliente` o `proveedor`, y el login declara desde
 * qué app llega. Contra el esquema real (SQLite en memoria) y las rutas reales.
 */

beforeEach(function () {
    RateLimiter::clear('login-campo');
    Cache::flush();

    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'agrega_foto_perfil_a_sec_user',
        'agrega_tipo_cuenta_a_sec_user',
        'crea_sec_personas_y_telefono_de_cuenta',
        'create_sec_role_table',
        'create_sec_permission_table',
        'create_sec_role_permission_table',
        'create_sec_user_role_table',
        'create_sec_token_dispositivo_table',
    ]);

    foreach (['sec_token_dispositivo', 'sec_user_role', 'sec_role_permission', 'sec_permission', 'sec_role', 'sec_user'] as $tabla) {
        DB::table($tabla)->delete();
    }

    $ahora = now();

    foreach (['cliente', 'proveedor', 'piloto', 'administrador'] as $rol) {
        DB::table('sec_role')->insert(['name' => $rol, 'description' => $rol, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
    }
});

/** @param  list<string>  $roles */
function cuentaDeTipo(string $username, string $tipoCuenta, array $roles): SecUser
{
    $ahora = now();
    $id = DB::table('sec_user')->insertGetId([
        'name' => ucfirst($username), 'username' => $username, 'password' => bcrypt('Clave1234'), 'type' => 'interno',
        'tipo_cuenta' => $tipoCuenta, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    foreach ($roles as $rol) {
        DB::table('sec_user_role')->insert([
            'id_user' => $id, 'id_role' => DB::table('sec_role')->where('name', $rol)->value('id'), 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
    }

    return SecUser::query()->findOrFail($id);
}

function loginDeApp(string $username, ?string $app, string $password = 'Clave1234'): TestResponse
{
    return test()->postJson('/api/auth/token', array_filter([
        'username' => $username,
        'password' => $password,
        'uuid_dispositivo' => '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31',
        'app' => $app,
    ], fn ($valor) => $valor !== null));
}

// --- Los modelos de cada guard ---------------------------------------------------------

it('el guard del panel solo carga cuentas de panel y el de las apps solo cuentas de app', function () {
    cuentaDeTipo('ana.panel', 'panel', ['administrador']);
    cuentaDeTipo('beto.cliente', 'cliente', ['cliente']);
    cuentaDeTipo('carla.proveedor', 'proveedor', ['proveedor']);

    expect(SecUsuarioInterno::query()->pluck('username')->all())->toBe(['ana.panel'])
        ->and(SecUsuarioApp::query()->orderBy('username')->pluck('username')->all())->toBe(['beto.cliente', 'carla.proveedor']);
});

it('una cuenta creada sin tipo no recibe token de dispositivo: nace de panel', function () {
    $id = DB::table('sec_user')->insertGetId([
        'name' => 'Sin tipo', 'username' => 'sin.tipo', 'password' => bcrypt('Clave1234'), 'type' => 'interno',
        'state' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(SecUser::query()->findOrFail($id)->tipo_cuenta)->toBe(TipoCuenta::Panel);
    loginDeApp('sin.tipo', null)->assertStatus(422);
});

// --- Login de las apps ---------------------------------------------------------------

it('cada cuenta de app entra por su app y recibe su token', function (string $tipo, string $rol, string $app) {
    $cuenta = cuentaDeTipo('usuaria', $tipo, [$rol]);

    $respuesta = loginDeApp($cuenta->username, $app)->assertCreated();

    // El rol sigue viajando como alias hasta la fase A5.
    expect($respuesta->json('rol.name'))->toBe($rol)
        ->and(SecTokenDispositivo::query()->where('user_id', $cuenta->id)->count())->toBe(1);
})->with([
    'cliente en la app cliente' => ['cliente', 'cliente', 'cliente'],
    'proveedor en la app proveedor' => ['proveedor', 'proveedor', 'proveedor'],
    'proveedor en el flavor piloto' => ['proveedor', 'proveedor', 'piloto'],
]);

it('mientras la app no manda `app`, el tipo se deduce de la cuenta y el login no falla', function (string $tipo, string $rol) {
    $cuenta = cuentaDeTipo('usuaria', $tipo, [$rol]);

    loginDeApp($cuenta->username, null)->assertCreated();
})->with([
    'cliente' => ['cliente', 'cliente'],
    'proveedor' => ['proveedor', 'proveedor'],
]);

it('una cuenta en la app equivocada recibe 403 tipo_de_cuenta_incorrecto, con su tipo y sin token', function (string $tipo, string $rol, string $app, string $deQueApp) {
    $cuenta = cuentaDeTipo('usuaria', $tipo, [$rol]);

    $respuesta = loginDeApp($cuenta->username, $app)->assertForbidden();

    expect($respuesta->json('codigo'))->toBe('tipo_de_cuenta_incorrecto')
        ->and($respuesta->json('tipo_cuenta'))->toBe($tipo)
        ->and($respuesta->json('message'))->toContain($deQueApp)
        ->and($respuesta->json('token'))->toBeNull()
        ->and(SecTokenDispositivo::withTrashed()->count())->toBe(0);
})->with([
    'cliente en la app del piloto' => ['cliente', 'cliente', 'proveedor', 'Agrocom.'],
    'cliente en el flavor piloto' => ['cliente', 'cliente', 'piloto', 'Agrocom.'],
    'proveedor en la app del cliente' => ['proveedor', 'proveedor', 'cliente', 'Agrocom Piloto'],
]);

it('la contraseña se verifica antes de decir de qué tipo es la cuenta', function () {
    cuentaDeTipo('usuaria', 'cliente', ['cliente']);

    $respuesta = loginDeApp('usuaria', 'proveedor', 'incorrecta')->assertStatus(422);

    expect($respuesta->json('codigo'))->toBeNull()
        ->and($respuesta->json('tipo_cuenta'))->toBeNull();
});

it('una cuenta de panel recibe en las apps el mismo rechazo que una credencial inválida', function (?string $app) {
    cuentaDeTipo('ana.panel', 'panel', ['administrador']);
    cuentaDeTipo('beto.cliente', 'cliente', ['cliente']);

    $delPanel = loginDeApp('ana.panel', $app)->assertStatus(422);
    $inexistente = loginDeApp('nadie', $app)->assertStatus(422);
    $claveMala = loginDeApp('beto.cliente', $app, 'incorrecta')->assertStatus(422);

    expect($delPanel->json())->toBe($inexistente->json())
        ->and($delPanel->json('errors.username'))->toBe($claveMala->json('errors.username'))
        ->and(SecTokenDispositivo::withTrashed()->count())->toBe(0);
})->with([null, 'cliente', 'proveedor']);

it('el token de una cuenta de panel no autentica en la API, ni siquiera si alguien se lo emitiera', function () {
    $panel = cuentaDeTipo('ana.panel', 'panel', ['administrador']);
    $rolId = (int) DB::table('sec_role')->where('name', 'administrador')->value('id');

    $emitido = app(EmitirTokenDispositivo::class)->ejecutar($panel, '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31', null, $rolId);

    comoDispositivo((string) $emitido->tokenPlano)->getJson('/api/auth/sesion')->assertUnauthorized();
});

it('el token de una cuenta de app sí autentica y la sesión devuelve su rol', function () {
    cuentaDeTipo('usuaria', 'cliente', ['cliente']);

    $token = loginDeApp('usuaria', 'cliente')->assertCreated()->json('token');

    comoDispositivo($token)->getJson('/api/auth/sesion')->assertOk()->assertJsonPath('rol.name', 'cliente');
});

// --- Panel -------------------------------------------------------------------------------

it('el panel no deja entrar a una cuenta de app: mismo rechazo que una credencial inválida', function (string $tipo, string $rol) {
    cuentaDeTipo('usuaria', $tipo, [$rol]);

    $deApp = test()->postJson('/login', ['username' => 'usuaria', 'password' => 'Clave1234'])->assertStatus(422);
    $claveMala = test()->postJson('/login', ['username' => 'usuaria', 'password' => 'incorrecta'])->assertStatus(422);

    expect($deApp->json('errors.username'))->toBe($claveMala->json('errors.username'));
})->with([
    'cliente' => ['cliente', 'cliente'],
    'proveedor' => ['proveedor', 'proveedor'],
]);

// --- Registro de cuentas ------------------------------------------------------------------

it('registrar una cuenta fija su tipo, y el panel no se crea por el registro de apps', function () {
    $cuentas = app(RegistroCuentas::class);

    $cliente = $cuentas->crear('Rosa', 'rosa', null, 'Clave1234', TipoCuenta::Cliente, ['cliente']);
    $proveedor = $cuentas->crear('Luis', 'luis', null, 'Clave1234', TipoCuenta::Proveedor, ['proveedor', 'piloto']);

    expect(SecUser::query()->findOrFail($cliente)->tipo_cuenta)->toBe(TipoCuenta::Cliente)
        ->and(SecUser::query()->findOrFail($proveedor)->tipo_cuenta)->toBe(TipoCuenta::Proveedor)
        ->and(fn () => $cuentas->crear('Ana', 'ana', null, 'Clave1234', TipoCuenta::Panel, ['administrador']))
        ->toThrow(InvalidArgumentException::class);
});

it('el tipo de app se normaliza: piloto es proveedor y lo desconocido no es nada', function () {
    expect(TipoCuenta::dePedidoDeApp('piloto'))->toBe(TipoCuenta::Proveedor)
        ->and(TipoCuenta::dePedidoDeApp(' Proveedor '))->toBe(TipoCuenta::Proveedor)
        ->and(TipoCuenta::dePedidoDeApp('cliente'))->toBe(TipoCuenta::Cliente)
        ->and(TipoCuenta::dePedidoDeApp('panel'))->toBeNull()
        ->and(TipoCuenta::dePedidoDeApp('otra'))->toBeNull();
});

// --- La migración clasifica lo que ya existe -------------------------------------------------

it('la migración clasifica las cuentas existentes por lo que ya son', function () {
    // Sus índices dependen de `tipo_cuenta`: se retiran primero, como lo haría `migrate:rollback`.
    (require database_path('migrations/2026_12_12_100000_crea_sec_personas_y_telefono_de_cuenta.php'))->down();

    $migracion = require database_path('migrations/2026_12_11_100000_agrega_tipo_cuenta_a_sec_user.php');
    $migracion->down();

    $ahora = now();
    $crear = function (string $username, string $type, array $roles) use ($ahora): int {
        $id = DB::table('sec_user')->insertGetId(['name' => $username, 'username' => $username, 'password' => 'x', 'type' => $type, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);

        foreach ($roles as $rol) {
            DB::table('sec_user_role')->insert(['id_user' => $id, 'id_role' => DB::table('sec_role')->where('name', $rol)->value('id'), 'created_at' => $ahora, 'updated_at' => $ahora]);
        }

        return $id;
    };

    $ids = [
        'panel' => $crear('admin', 'interno', ['administrador']),
        'cliente' => $crear('agro', 'interno', ['cliente']),
        'proveedor' => $crear('carlos', 'interno', ['piloto', 'proveedor']),
        'piloto' => $crear('rodrigo', 'interno', ['piloto']),
        'portal' => $crear('portal', 'cliente', []),
        'mixta' => $crear('mixta', 'interno', ['administrador', 'piloto']),
        'sinrol' => $crear('sinrol', 'interno', []),
    ];

    $migracion->up();

    $tipo = fn (string $clave): string => (string) DB::table('sec_user')->where('id', $ids[$clave])->value('tipo_cuenta');

    expect($tipo('panel'))->toBe('panel')
        ->and($tipo('cliente'))->toBe('cliente')
        ->and($tipo('proveedor'))->toBe('proveedor')
        ->and($tipo('piloto'))->toBe('proveedor')
        ->and($tipo('portal'))->toBe('cliente')
        ->and($tipo('mixta'))->toBe('panel')
        ->and($tipo('sinrol'))->toBe('panel');
});

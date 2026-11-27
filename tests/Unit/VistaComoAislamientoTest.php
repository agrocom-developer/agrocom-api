<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Dominio\Excepciones\EscrituraEnModoSoloLectura;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModoSoloLectura;
use App\Dominios\Seguridad\Aplicacion\IniciarVistaComo;
use App\Dominios\Seguridad\Aplicacion\TerminarVistaComo;
use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\VistaComoNoPermitida;
use App\Dominios\Seguridad\Dominio\MotivoFinVistaComo;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Dominio\VistaComoActiva;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecVistaComo;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\AplicarVistaComo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as Rutas;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/*
 * Tarea 140 — "ver como otro usuario": las garantías que necesitan base de
 * datos o el enrutador real, contra el ESQUEMA REAL (las migraciones de
 * seguridad y de la bitácora, en SQLite en memoria) y las clases reales
 * (casos de uso, middleware, controlador del portal). Complementa a
 * `VistaComoTest`, que prueba las reglas puras.
 *
 *   1. La bitácora registra la entrada Y la salida de cada vista, a nombre del
 *      administrador real (invariante 9), y la salida devuelve el rol activo.
 *   2. Quién puede abrirla: el permiso se evalúa contra el ROL ACTIVO, nunca la
 *      unión de los roles (invariante 10), y ciertas cuentas no se pueden mirar.
 *   3. La vista se revalida en cada request: si deja de valer, se cierra.
 *   4. Solo lectura con el middleware real: una escritura colada en un GET falla
 *      cerrado y el estado del request se restaura.
 *   5. Una vista no deja cambiar de rol (cambiarlo es escribir).
 *   6. Toda ruta con sesión pasa por el middleware, en el orden correcto —el
 *      barrido que garantiza que ninguna ruta de escritura escape al rechazo—.
 */

uses(TestCase::class);

const VISTA_COMO_PERMISO = 'seguridad.usuario.ver_como';

/**
 * Crea, con las migraciones reales, las tablas que la vista como necesita.
 *
 * SQLite no conoce `CREATE UNIQUE INDEX ... USING btree ... WHERE`: las
 * migraciones de `sec_user`, `sec_role_permission`, `sec_user_role` y
 * `sec_user_preferencia` crean la tabla y recién después fallan en ese índice
 * parcial de Postgres. Se tolera SOLO ese error; cualquier otro (una columna
 * nueva sin default, por ejemplo) rompe la prueba, como debe.
 */
function vistaComoEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'agrega_tipo_cuenta_a_sec_user',
        'create_sec_role_table',
        'create_sec_permission_table',
        'create_sec_role_permission_table',
        'create_sec_user_role_table',
        'create_sec_user_preferencia_table',
        'create_sec_vistas_como_table',
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
            if (! str_contains($excepcion->getMessage(), 'USING btree')) {
                throw $excepcion;
            }
        }
    }
}

/** @param  list<string>  $permisos códigos de permiso que el rol tiene */
function vistaComoRol(string $nombre, array $permisos = []): int
{
    $ahora = now();
    $rolId = DB::table('sec_role')->insertGetId([
        'name' => $nombre, 'description' => $nombre, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    foreach ($permisos as $codigo) {
        $permisoId = DB::table('sec_permission')->where('code', $codigo)->value('id')
            ?? DB::table('sec_permission')->insertGetId([
                'code' => $codigo, 'description' => $codigo, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);

        DB::table('sec_role_permission')->insert([
            'id_role' => $rolId, 'id_permission' => $permisoId, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
    }

    return $rolId;
}

/**
 * @param  list<int>  $roles
 * @param  array<string, mixed>  $extra
 */
function vistaComoCuenta(TipoUsuario $tipo, array $roles = [], array $extra = []): SecUser
{
    static $secuencia = 0;
    $secuencia++;
    $ahora = now();

    $id = DB::table('sec_user')->insertGetId([
        'name' => "Cuenta {$secuencia}",
        'username' => "cuenta.{$secuencia}",
        'password' => 'no-se-usa',
        'type' => $tipo->value,
        // Cliente = cuenta de app (ADR 0046); interno = cuenta de panel.
        'tipo_cuenta' => $tipo === TipoUsuario::Cliente ? 'cliente' : 'panel',
        'state' => true,
        'created_at' => $ahora,
        'updated_at' => $ahora,
        ...$extra,
    ]);

    foreach ($roles as $rolId) {
        DB::table('sec_user_role')->insert(['id_user' => $id, 'id_role' => $rolId, 'created_at' => $ahora, 'updated_at' => $ahora]);
    }

    return SecUser::withTrashed()->findOrFail($id);
}

/** Una cuenta de panel que se puede mirar: habilitada y con un rol vivo (`operador`). */
function vistaComoObservada(array $extra = []): SecUser
{
    $rol = DB::table('sec_role')->where('name', 'operador')->value('id') ?? vistaComoRol('operador');

    return vistaComoCuenta(TipoUsuario::Interno, [(int) $rol], $extra);
}

/** El administrador: cuenta de panel con un rol que sí tiene `ver_como`. */
function vistaComoAdministrador(): array
{
    $rol = vistaComoRol('administrador', [VISTA_COMO_PERMISO]);
    $admin = vistaComoCuenta(TipoUsuario::Interno, [$rol]);

    // Como en una sesión real: el guard `interno` es el administrador y su rol activo está fijado.
    Auth::guard('interno')->setUser($admin);
    Session::put('sec_rol_activo_id', $rol);

    return [$admin, $rol];
}

function vistaComoPeticion(string $metodo, string $uri, string $nombreRuta): Request
{
    $peticion = Request::create($uri, $metodo);
    $peticion->setLaravelSession(app('session.store'));
    $ruta = (new Route([$metodo], $uri, static fn () => null))->name($nombreRuta);
    $peticion->setRouteResolver(static fn () => $ruta);
    // Lo que el framework hace con la solicitud real: `user($guard)` sale del guard.
    $peticion->setUserResolver(static fn (?string $guard = null) => Auth::guard($guard)->user());

    return $peticion;
}

/** @return list<Bitacora> las filas de bitácora de una vista, en el orden en que se escribieron */
function vistaComoBitacora(int $registroId): array
{
    return Bitacora::query()
        ->where('tabla', 'sec_vistas_como')
        ->where('registro_id', $registroId)
        ->orderBy('id')
        ->get()
        ->all();
}

beforeEach(function () {
    vistaComoEsquema();
    Session::start();
});

afterEach(function () {
    ModoSoloLectura::desactivar();
});

// --- 1. Bitácora: entrada Y salida, a nombre del administrador real ------------------------

it('la entrada y la salida de una vista quedan en la bitácora, ambas a nombre del administrador', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoObservada();

    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    expect(Bitacora::query()->where('tabla', 'sec_vistas_como')->count())->toBe(1);

    app(TerminarVistaComo::class)->ejecutar($vista, MotivoFinVistaComo::Manual);

    [$entrada, $salida] = vistaComoBitacora($vista->registroId);

    // Quién iba de verdad, a quién vio y bajo qué tipo: la entrada.
    expect($entrada->accion)->toBe(AccionBitacora::Creado)
        ->and($entrada->user_id)->toBe($admin->id)
        ->and($entrada->despues)->toMatchArray(['admin_id' => $admin->id, 'usuario_id' => $cliente->id, 'tipo' => 'interno'])
        ->and($entrada->despues['iniciada_at'] ?? null)->not->toBeNull();

    // Cuándo volvió y cómo: la salida, un cambio de nulo a fecha y motivo.
    expect($salida->accion)->toBe(AccionBitacora::Actualizado)
        ->and($salida->user_id)->toBe($admin->id)
        ->and($salida->antes)->toBe(['finalizada_at' => null, 'motivo_fin' => null])
        ->and($salida->despues['motivo_fin'] ?? null)->toBe('manual')
        ->and($salida->despues['finalizada_at'] ?? null)->not->toBeNull();

    // Y nada más: ni una fila de bitácora a nombre de la cuenta observada.
    expect(Bitacora::query()->where('user_id', $cliente->id)->count())->toBe(0);
});

it('la salida devuelve al administrador el rol activo con el que entró y borra la bandera', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $rolJefe = vistaComoRol('operador');
    $jefe = vistaComoCuenta(TipoUsuario::Interno, [$rolJefe]);

    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $jefe->id, null);
    expect($vista->rolId)->toBe($rolJefe)
        ->and(Session::has(VistaComoActiva::CLAVE_SESION))->toBeTrue();

    // Durante la vista el middleware fija el rol observado; acá se simula ese estado.
    Session::put('sec_rol_activo_id', $rolJefe);

    app(TerminarVistaComo::class)->ejecutar($vista, MotivoFinVistaComo::Manual);

    expect(Session::has(VistaComoActiva::CLAVE_SESION))->toBeFalse()
        ->and(Session::get('sec_rol_activo_id'))->toBe($rolAdmin);
});

it('una vista se cierra una sola vez: cerrarla de nuevo no escribe una segunda salida', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoObservada();

    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);
    app(TerminarVistaComo::class)->ejecutar($vista, MotivoFinVistaComo::Manual);
    app(TerminarVistaComo::class)->ejecutar($vista, MotivoFinVistaComo::Invalidada);

    $filas = vistaComoBitacora($vista->registroId);

    expect($filas)->toHaveCount(2)
        ->and(SecVistaComo::query()->findOrFail($vista->registroId)->motivo_fin)->toBe(MotivoFinVistaComo::Manual);
});

it('no se cierra una vista con el guard interno siendo otra persona: la salida quedaría firmada por quien no la hizo', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoObservada();
    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    Auth::guard('interno')->setUser(vistaComoCuenta(TipoUsuario::Interno));

    expect(fn () => app(TerminarVistaComo::class)->ejecutar($vista, MotivoFinVistaComo::Manual))
        ->toThrow(LogicException::class);

    expect(SecVistaComo::query()->findOrFail($vista->registroId)->finalizada_at)->toBeNull();
});

// --- 2. Quién puede abrirla ------------------------------------------------------------

it('sin el permiso en el rol activo no se abre nada: ni fila, ni bandera, ni bitácora', function () {
    $rolSinPermiso = vistaComoRol('operador');
    $operador = vistaComoCuenta(TipoUsuario::Interno, [$rolSinPermiso]);
    $observada = vistaComoCuenta(TipoUsuario::Interno, [vistaComoRol('un_rol')]);

    expect(fn () => (new IniciarVistaComo)->ejecutar($operador, $rolSinPermiso, $observada->id, null))
        ->toThrow(PermisoDenegado::class);

    expect(SecVistaComo::query()->count())->toBe(0)
        ->and(Session::has(VistaComoActiva::CLAVE_SESION))->toBeFalse()
        ->and(Bitacora::query()->where('tabla', 'sec_vistas_como')->count())->toBe(0);
});

it('el permiso se evalúa contra el rol activo, no contra la unión de los roles del usuario', function () {
    $rolConPermiso = vistaComoRol('administrador', [VISTA_COMO_PERMISO]);
    $rolSinPermiso = vistaComoRol('operador');
    $usuario = vistaComoCuenta(TipoUsuario::Interno, [$rolConPermiso, $rolSinPermiso]);
    $cliente = vistaComoObservada();

    // Con el rol que no lo tiene como activo: denegado, aunque el OTRO rol sí lo tenga.
    expect(fn () => (new IniciarVistaComo)->ejecutar($usuario, $rolSinPermiso, $cliente->id, null))
        ->toThrow(PermisoDenegado::class);

    // Con el rol que sí lo tiene: se abre.
    expect((new IniciarVistaComo)->ejecutar($usuario, $rolConPermiso, $cliente->id, null))->toBeInstanceOf(VistaComoActiva::class);
});

it('un rol activo que el usuario no tiene asignado no sirve, aunque ese rol tenga el permiso', function () {
    $rolAjeno = vistaComoRol('administrador', [VISTA_COMO_PERMISO]);
    $usuario = vistaComoCuenta(TipoUsuario::Interno, [vistaComoRol('piloto')]);
    $cliente = vistaComoObservada();

    expect(fn () => (new IniciarVistaComo)->ejecutar($usuario, $rolAjeno, $cliente->id, null))
        ->toThrow(PermisoDenegado::class);
});

it('una cuenta de app no puede abrir una vista aunque su id llegue al caso de uso', function () {
    $rol = vistaComoRol('administrador', [VISTA_COMO_PERMISO]);
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [$rol], []);
    $otra = vistaComoObservada();

    expect(fn () => (new IniciarVistaComo)->ejecutar($cliente, $rol, $otra->id, null))
        ->toThrow(PermisoDenegado::class);
});

it('«ver como» solo se abre sobre cuentas de panel: una de cliente o de proveedor no se puede mirar', function (string $tipoCuenta) {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $deApp = vistaComoCuenta(TipoUsuario::Interno, [vistaComoRol('piloto')], ['tipo_cuenta' => $tipoCuenta]);

    expect(fn () => (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $deApp->id, null))
        ->toThrow(VistaComoNoPermitida::class);

    expect(SecVistaComo::query()->count())->toBe(0)
        ->and(Session::has(VistaComoActiva::CLAVE_SESION))->toBeFalse();
})->with(['cliente', 'proveedor']);

it('hay cuentas que no se pueden mirar', function (string $caso, Closure $preparar) {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    [$idObservado, $idRol, $mensaje] = $preparar($admin);

    expect(fn () => (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $idObservado, $idRol))
        ->toThrow(VistaComoNoPermitida::class);

    expect(SecVistaComo::query()->count())->toBe(0);
})->with([
    'la propia' => ['propia', fn (SecUser $admin) => [$admin->id, null, null]],
    'una que no existe' => ['inexistente', fn () => [99999, null, null]],
    'una bloqueada' => ['bloqueada', fn () => [vistaComoObservada(['state' => false])->id, null, null]],
    'una dada de baja' => ['baja', fn () => [vistaComoObservada(['deleted_at' => now()])->id, null, null]],
    'una de cliente' => ['de cliente', fn () => [vistaComoCuenta(TipoUsuario::Cliente)->id, null, null]],
    'una interna sin roles' => ['sin roles', fn () => [vistaComoCuenta(TipoUsuario::Interno)->id, null, null]],
    'una interna con dos roles sin elegir uno' => ['dos roles', fn () => [vistaComoCuenta(TipoUsuario::Interno, [vistaComoRol('piloto'), vistaComoRol('proveedor')])->id, null, null]],
    'una interna bajo un rol que no tiene' => ['rol ajeno', fn () => [vistaComoCuenta(TipoUsuario::Interno, [vistaComoRol('piloto')])->id, vistaComoRol('operador'), null]],
]);

it('no se abre una segunda vista mientras hay una abierta', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $primero = vistaComoObservada();
    $segundo = vistaComoObservada();

    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $primero->id, null);

    expect(fn () => (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $segundo->id, null))
        ->toThrow(VistaComoNoPermitida::class);

    expect(SecVistaComo::query()->count())->toBe(1);
});

// --- 3. Revalidación en cada request -----------------------------------------------------

it('si la cuenta observada se bloquea a mitad de la vista, el request no sigue como ella: la vista se cierra', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoObservada();
    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    DB::table('sec_user')->where('id', $cliente->id)->update(['state' => false]);

    $llegoAlControlador = false;
    $respuesta = app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/panel/solicitudes', 'panel.solicitudes.index'),
        function () use (&$llegoAlControlador) {
            $llegoAlControlador = true;

            return response('no debería verse');
        },
    );

    expect($llegoAlControlador)->toBeFalse()
        ->and($respuesta->isRedirect())->toBeTrue()
        ->and(Session::has(VistaComoActiva::CLAVE_SESION))->toBeFalse()
        ->and(SecVistaComo::query()->findOrFail($vista->registroId)->motivo_fin)->toBe(MotivoFinVistaComo::Invalidada)
        ->and(Auth::guard('interno')->id())->toBe($admin->id);
});

it('si al administrador le quitan el permiso a mitad de la vista, se cierra igual', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoObservada();
    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    DB::table('sec_role_permission')->where('id_role', $rolAdmin)->update(['deleted_at' => now()]);

    app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/panel/solicitudes', 'panel.solicitudes.index'),
        fn () => response('no debería verse'),
    );

    expect(SecVistaComo::query()->findOrFail($vista->registroId)->motivo_fin)->toBe(MotivoFinVistaComo::Invalidada);
});

it('una bandera de otro administrador no sustituye a nadie ni escribe a su nombre', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoObservada();
    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    // La sesión autenticada pasa a ser de otra persona, con la bandera colgada.
    $intruso = vistaComoCuenta(TipoUsuario::Interno);
    Auth::guard('interno')->setUser($intruso);

    $dentro = null;
    app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/panel/solicitudes', 'panel.solicitudes.index'),
        function (Request $peticion) use (&$dentro) {
            $dentro = $peticion->user('interno');

            return response('ok');
        },
    );

    // No se sustituyó a nadie: el request sigue con la cuenta autenticada (la del intruso).
    expect($dentro?->id)->toBe($intruso->id)
        ->and(Session::has(VistaComoActiva::CLAVE_SESION))->toBeFalse()
        ->and(SecVistaComo::query()->count())->toBe(1)
        ->and(SecVistaComo::query()->first()->finalizada_at)->toBeNull();
});

// --- 4. Solo lectura con el middleware real ------------------------------------------------

it('una escritura colada en un GET falla cerrado, no queda guardada y el request se restaura', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $rolJefe = vistaComoRol('operador');
    $jefe = vistaComoCuenta(TipoUsuario::Interno, [$rolJefe]);
    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $jefe->id, null);

    $dentro = [];

    // `function`, no `fn`: el arreglo `$dentro` tiene que llegar por referencia hasta el closure interno.
    expect(function () use ($jefe, &$dentro) {
        app(AplicarVistaComo::class)->handle(
            vistaComoPeticion('GET', '/panel/algo', 'panel.algo'),
            function () use ($jefe, &$dentro) {
                $dentro = [
                    'quien' => Auth::guard('interno')->id(),
                    'rol' => Session::get('sec_rol_activo_id'),
                    'soloLectura' => ModoSoloLectura::activo(),
                ];

                // Un controlador de lectura que escribe por descuido: firmaría la cuenta observada.
                return response((string) SecVistaComo::query()->create([
                    'admin_id' => $jefe->id,
                    'usuario_id' => $jefe->id,
                    'tipo' => TipoUsuario::Interno,
                    'rol_id' => 1,
                    'iniciada_at' => now(),
                ])->id);
            },
        );
    })->toThrow(EscrituraEnModoSoloLectura::class);

    // Durante el request se evaluaba a la cuenta observada, bajo su rol y en solo lectura…
    expect($dentro)->toBe(['quien' => $jefe->id, 'rol' => $rolJefe, 'soloLectura' => true]);

    // …y no dejó rastro: ni fila nueva, ni bitácora extra, ni el modo activado, ni el guard sustituido.
    expect(SecVistaComo::query()->count())->toBe(1)
        ->and(Bitacora::query()->where('tabla', 'sec_vistas_como')->count())->toBe(1)
        ->and(ModoSoloLectura::activo())->toBeFalse()
        ->and(Auth::guard('interno')->id())->toBe($admin->id);
});

// --- 5. Una vista no deja cambiar de rol ---------------------------------------------------------

it('una vista de panel no deja usar el selector de rol y sí recorrer el resto del panel', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $observada = vistaComoObservada();

    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $observada->id, null);

    $respuesta = app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/panel/rol-activo', 'panel.rol-activo.mostrar'),
        fn () => response('selector de rol'),
    );

    expect($respuesta->isRedirect())->toBeTrue();

    $respuesta = app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/panel/solicitudes', 'panel.solicitudes.index'),
        fn () => response('pantalla del panel'),
    );

    expect($respuesta->isRedirect())->toBeFalse();
});

// --- 6. Ninguna ruta con sesión escapa al middleware ---------------------------------------------

it('toda ruta con sesión lleva AplicarVistaComo, después de abrirla y antes de autenticar y de resolver el rol', function () {
    $enrutador = app('router');
    $posicion = static function (array $pila, string $prefijo): ?int {
        foreach ($pila as $indice => $middleware) {
            if (str_starts_with($middleware, $prefijo)) {
                return $indice;
            }
        }

        return null;
    };

    $conSesion = 0;
    $incumplen = [];

    foreach ($enrutador->getRoutes() as $ruta) {
        $pila = array_values(array_filter($enrutador->gatherRouteMiddleware($ruta), is_string(...)));
        $sesion = $posicion($pila, 'Illuminate\Session\Middleware\StartSession');
        $vista = $posicion($pila, AplicarVistaComo::class);
        $etiqueta = implode('|', $ruta->methods()).' /'.$ruta->uri();

        if ($sesion === null) {
            continue;
        }

        $conSesion++;

        if ($vista === null || $vista < $sesion) {
            $incumplen[] = "{$etiqueta}: sin AplicarVistaComo después de StartSession";

            continue;
        }

        foreach (['Illuminate\Auth\Middleware\Authenticate', 'App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo'] as $posterior) {
            $indice = $posicion($pila, $posterior);

            if ($indice !== null && $indice < $vista) {
                $incumplen[] = "{$etiqueta}: {$posterior} corre antes de AplicarVistaComo";
            }
        }
    }

    // Que el barrido no pase por vacío: el panel y el portal son cientos de rutas.
    expect($conSesion)->toBeGreaterThan(40)
        ->and($incumplen)->toBe([]);
});

it('lo único que autentica sin sesión es la API de campo, con Sanctum', function () {
    $enrutador = app('router');
    $ajenas = [];

    foreach ($enrutador->getRoutes() as $ruta) {
        $pila = array_values(array_filter($enrutador->gatherRouteMiddleware($ruta), is_string(...)));
        $conSesion = array_filter($pila, static fn (string $m): bool => str_starts_with($m, 'Illuminate\Session\Middleware\StartSession')) !== [];

        foreach ($pila as $middleware) {
            if (! $conSesion && str_starts_with($middleware, 'Illuminate\Auth\Middleware\Authenticate:') && $middleware !== 'Illuminate\Auth\Middleware\Authenticate:sanctum') {
                $ajenas[] = implode('|', $ruta->methods()).' /'.$ruta->uri().' → '.$middleware;
            }
        }
    }

    // Una ruta que autentica en `interno` o `cliente` sin sesión se saltaría la vista como sin que nadie lo note.
    expect($ajenas)->toBe([]);
});

it('la ruta de salida existe, es un POST y se llama como el middleware espera', function () {
    $ruta = Rutas::getRoutes()->getByName(AplicarVistaComo::RUTA_SALIDA);

    expect($ruta)->not->toBeNull()
        ->and($ruta->methods())->toBe(['POST']);
});

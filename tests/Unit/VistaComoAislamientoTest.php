<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Dominio\Excepciones\EscrituraEnModoSoloLectura;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModoSoloLectura;
use App\Dominios\Operaciones\Contratos\DatosActaConformada;
use App\Dominios\Operaciones\Contratos\LecturaActaConformada;
use App\Dominios\Portal\Infraestructura\Http\Controllers\Web\ActasPortalController;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
 *   5. El portal, visto como el cliente X, solo consulta el contrato de X y
 *      jamás el de otro (invariante 5): mismo camino real del guard `cliente`.
 *   6. Toda ruta con sesión pasa por el middleware, en el orden correcto —el
 *      barrido que garantiza que ninguna ruta de escritura escape al rechazo—.
 *
 * Qué NO prueba (y dónde se prueba): la consulta de actas en sí
 * (`LecturaActaConformadaEloquent`) no cambió con esta tarea y depende de
 * tablas de Operaciones; acá se reemplaza por una lectura en memoria que
 * respeta su contrato, para probar lo que esta tarea sí tocó: de dónde sale el
 * contrato y que nada lee "todo" y filtra después.
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

/** El administrador de plataforma: interno, con un rol que sí tiene `ver_como`. */
function vistaComoAdministrador(): array
{
    $rol = vistaComoRol('admin_plataforma', [VISTA_COMO_PERMISO]);
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

/**
 * Lectura de actas en memoria, con el mismo contrato que la de Operaciones:
 * la lista por contrato ya viene acotada. Todo otro camino de lectura falla,
 * para que un `where` aparte o una lectura global no pasen inadvertidos.
 *
 * @param  list<DatosActaConformada>  $actas
 */
function vistaComoActasEnMemoria(array $actas): object
{
    return new class($actas) implements LecturaActaConformada
    {
        /** @var list<int> */
        public array $contratosPedidos = [];

        /** @param  list<DatosActaConformada>  $actas */
        public function __construct(private readonly array $actas) {}

        public function obtenerPorActaId(int $actaId): ?DatosActaConformada
        {
            throw new LogicException('El portal no busca un acta por id fuera de la lista de su contrato.');
        }

        public function listarFirmadas(): array
        {
            throw new LogicException('El portal no lee la lista global de actas.');
        }

        public function listarFirmadasPorContrato(int $contratoId): array
        {
            $this->contratosPedidos[] = $contratoId;

            return array_values(array_filter(
                $this->actas,
                static fn (DatosActaConformada $acta): bool => $acta->contratoId === $contratoId,
            ));
        }
    };
}

beforeEach(function () {
    vistaComoEsquema();
    Session::start();
});

afterEach(function () {
    ModoSoloLectura::desactivar();
});

// --- 1. Bitácora: entrada Y salida, a nombre del administrador real ------------------------

it('la entrada y la salida de una vista quedan en la bitácora, ambas a nombre del administrador real', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);

    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    expect(Bitacora::query()->where('tabla', 'sec_vistas_como')->count())->toBe(1);

    app(TerminarVistaComo::class)->ejecutar($vista, MotivoFinVistaComo::Manual);

    [$entrada, $salida] = vistaComoBitacora($vista->registroId);

    // Quién iba de verdad, a quién vio y bajo qué tipo: la entrada.
    expect($entrada->accion)->toBe(AccionBitacora::Creado)
        ->and($entrada->user_id)->toBe($admin->id)
        ->and($entrada->despues)->toMatchArray(['admin_id' => $admin->id, 'usuario_id' => $cliente->id, 'tipo' => 'cliente'])
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
    $rolJefe = vistaComoRol('jefe_campo');
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
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);

    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);
    app(TerminarVistaComo::class)->ejecutar($vista, MotivoFinVistaComo::Manual);
    app(TerminarVistaComo::class)->ejecutar($vista, MotivoFinVistaComo::Invalidada);

    $filas = vistaComoBitacora($vista->registroId);

    expect($filas)->toHaveCount(2)
        ->and(SecVistaComo::query()->findOrFail($vista->registroId)->motivo_fin)->toBe(MotivoFinVistaComo::Manual);
});

it('no se cierra una vista con el guard interno siendo otra persona: la salida quedaría firmada por quien no la hizo', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);
    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    Auth::guard('interno')->setUser(vistaComoCuenta(TipoUsuario::Interno));

    expect(fn () => app(TerminarVistaComo::class)->ejecutar($vista, MotivoFinVistaComo::Manual))
        ->toThrow(LogicException::class);

    expect(SecVistaComo::query()->findOrFail($vista->registroId)->finalizada_at)->toBeNull();
});

// --- 2. Quién puede abrirla ------------------------------------------------------------

it('sin el permiso en el rol activo no se abre nada: ni fila, ni bandera, ni bitácora', function () {
    $rolSinPermiso = vistaComoRol('dueno');
    $dueno = vistaComoCuenta(TipoUsuario::Interno, [$rolSinPermiso]);
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);

    expect(fn () => (new IniciarVistaComo)->ejecutar($dueno, $rolSinPermiso, $cliente->id, null))
        ->toThrow(PermisoDenegado::class);

    expect(SecVistaComo::query()->count())->toBe(0)
        ->and(Session::has(VistaComoActiva::CLAVE_SESION))->toBeFalse()
        ->and(Bitacora::query()->where('tabla', 'sec_vistas_como')->count())->toBe(0);
});

it('el permiso se evalúa contra el rol activo, no contra la unión de los roles del usuario', function () {
    $rolConPermiso = vistaComoRol('admin_plataforma', [VISTA_COMO_PERMISO]);
    $rolSinPermiso = vistaComoRol('encargado_operaciones');
    $usuario = vistaComoCuenta(TipoUsuario::Interno, [$rolConPermiso, $rolSinPermiso]);
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);

    // Con el rol que no lo tiene como activo: denegado, aunque el OTRO rol sí lo tenga.
    expect(fn () => (new IniciarVistaComo)->ejecutar($usuario, $rolSinPermiso, $cliente->id, null))
        ->toThrow(PermisoDenegado::class);

    // Con el rol que sí lo tiene: se abre.
    expect((new IniciarVistaComo)->ejecutar($usuario, $rolConPermiso, $cliente->id, null))->toBeInstanceOf(VistaComoActiva::class);
});

it('un rol activo que el usuario no tiene asignado no sirve, aunque ese rol tenga el permiso', function () {
    $rolAjeno = vistaComoRol('admin_plataforma', [VISTA_COMO_PERMISO]);
    $usuario = vistaComoCuenta(TipoUsuario::Interno, [vistaComoRol('piloto')]);
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);

    expect(fn () => (new IniciarVistaComo)->ejecutar($usuario, $rolAjeno, $cliente->id, null))
        ->toThrow(PermisoDenegado::class);
});

it('una cuenta de portal no puede abrir una vista aunque su id llegue al caso de uso', function () {
    $rol = vistaComoRol('admin_plataforma', [VISTA_COMO_PERMISO]);
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [$rol], ['contrato_id' => 7]);
    $otro = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 8]);

    expect(fn () => (new IniciarVistaComo)->ejecutar($cliente, $rol, $otro->id, null))
        ->toThrow(PermisoDenegado::class);
});

it('hay cuentas que no se pueden mirar', function (string $caso, Closure $preparar) {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    [$idObservado, $idRol, $mensaje] = $preparar($admin);

    expect(fn () => (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $idObservado, $idRol))
        ->toThrow(VistaComoNoPermitida::class);

    expect(SecVistaComo::query()->count())->toBe(0);
})->with([
    'la propia' => ['propia', fn (SecUser $admin) => [$admin->id, null, null]],
    'una que no existe' => ['inexistente', fn () => [99999, null, null]],
    'una bloqueada' => ['bloqueada', fn () => [vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7, 'state' => false])->id, null, null]],
    'una dada de baja' => ['baja', fn () => [vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7, 'deleted_at' => now()])->id, null, null]],
    'una de portal sin contrato' => ['sin contrato', fn () => [vistaComoCuenta(TipoUsuario::Cliente)->id, null, null]],
    'una interna sin roles' => ['sin roles', fn () => [vistaComoCuenta(TipoUsuario::Interno)->id, null, null]],
    'una interna con dos roles sin elegir uno' => ['dos roles', fn () => [vistaComoCuenta(TipoUsuario::Interno, [vistaComoRol('piloto'), vistaComoRol('auxiliar')])->id, null, null]],
    'una interna bajo un rol que no tiene' => ['rol ajeno', fn () => [vistaComoCuenta(TipoUsuario::Interno, [vistaComoRol('piloto')])->id, vistaComoRol('dueno'), null]],
]);

it('no se abre una segunda vista mientras hay una abierta', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $primero = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);
    $segundo = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 8]);

    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $primero->id, null);

    expect(fn () => (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $segundo->id, null))
        ->toThrow(VistaComoNoPermitida::class);

    expect(SecVistaComo::query()->count())->toBe(1);
});

// --- 3. Revalidación en cada request -----------------------------------------------------

it('si la cuenta observada se bloquea a mitad de la vista, el request no sigue como ella: la vista se cierra', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);
    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    DB::table('sec_user')->where('id', $cliente->id)->update(['state' => false]);

    $llegoAlControlador = false;
    $respuesta = app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/portal/actas', 'portal.actas.index'),
        function () use (&$llegoAlControlador) {
            $llegoAlControlador = true;

            return response('no debería verse');
        },
    );

    expect($llegoAlControlador)->toBeFalse()
        ->and($respuesta->isRedirect())->toBeTrue()
        ->and(Session::has(VistaComoActiva::CLAVE_SESION))->toBeFalse()
        ->and(SecVistaComo::query()->findOrFail($vista->registroId)->motivo_fin)->toBe(MotivoFinVistaComo::Invalidada)
        ->and(Auth::guard('cliente')->user())->toBeNull()
        ->and(Auth::guard('interno')->id())->toBe($admin->id);
});

it('si al administrador le quitan el permiso a mitad de la vista, se cierra igual', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);
    $vista = (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    DB::table('sec_role_permission')->where('id_role', $rolAdmin)->update(['deleted_at' => now()]);

    app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/portal/actas', 'portal.actas.index'),
        fn () => response('no debería verse'),
    );

    expect(SecVistaComo::query()->findOrFail($vista->registroId)->motivo_fin)->toBe(MotivoFinVistaComo::Invalidada);
});

it('una bandera de otro administrador no sustituye a nadie ni escribe a su nombre', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);
    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    // La sesión autenticada pasa a ser de otra persona, con la bandera colgada.
    $intruso = vistaComoCuenta(TipoUsuario::Interno);
    Auth::guard('interno')->setUser($intruso);

    $dentro = null;
    app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/portal/actas', 'portal.actas.index'),
        function (Request $peticion) use (&$dentro) {
            $dentro = $peticion->user('cliente');

            return response('ok');
        },
    );

    expect($dentro)->toBeNull()
        ->and(Session::has(VistaComoActiva::CLAVE_SESION))->toBeFalse()
        ->and(SecVistaComo::query()->count())->toBe(1)
        ->and(SecVistaComo::query()->first()->finalizada_at)->toBeNull();
});

// --- 4. Solo lectura con el middleware real ------------------------------------------------

it('una escritura colada en un GET falla cerrado, no queda guardada y el request se restaura', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $rolJefe = vistaComoRol('jefe_campo');
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

// --- 5. El portal, visto como el cliente X, solo consulta el contrato de X -------------------

/** @return array{0: SecUser, 1: int, 2: object} el administrador, su rol y una lectura con actas de tres contratos */
function vistaComoPortalPreparado(): array
{
    [$admin, $rolAdmin] = vistaComoAdministrador();

    $lectura = vistaComoActasEnMemoria([
        new DatosActaConformada(actaId: 11, contratoId: 7, hectareasConformadas: '100.00', firmada: true, pdfPath: 'actas/11.pdf'),
        new DatosActaConformada(actaId: 12, contratoId: 7, hectareasConformadas: '50.50', firmada: true, pdfPath: 'actas/12.pdf'),
        new DatosActaConformada(actaId: 21, contratoId: 8, hectareasConformadas: '75.00', firmada: true, pdfPath: 'actas/21.pdf'),
        new DatosActaConformada(actaId: 31, contratoId: 9, hectareasConformadas: '20.00', firmada: true, pdfPath: 'actas/31.pdf'),
    ]);

    return [$admin, $rolAdmin, $lectura];
}

it('visto como un cliente, el listado de actas es exactamente el de su contrato', function (int $contrato, array $actasEsperadas) {
    [$admin, $rolAdmin, $lectura] = vistaComoPortalPreparado();
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => $contrato]);
    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    $vista = null;
    app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/portal/actas', 'portal.actas.index'),
        function (Request $peticion) use ($lectura, &$vista) {
            $vista = app(ActasPortalController::class)->index($peticion, $lectura);

            return response('ok');
        },
    );

    $actas = array_map(static fn (DatosActaConformada $acta): int => $acta->actaId, $vista->getData()['actas']);

    expect($actas)->toBe($actasEsperadas)
        // La lectura se pidió UNA vez, con el contrato de la cuenta observada, y con ningún otro.
        ->and($lectura->contratosPedidos)->toBe([$contrato]);
})->with([
    'el cliente del contrato 7' => [7, [11, 12]],
    'el cliente del contrato 8' => [8, [21]],
    'el cliente del contrato 9' => [9, [31]],
]);

it('visto como un cliente, pedir el PDF de un acta de otro contrato es un 404, sin leer nada global', function () {
    [$admin, $rolAdmin, $lectura] = vistaComoPortalPreparado();
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);
    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    foreach ([21, 31, 999] as $actaAjena) {
        expect(fn () => app(AplicarVistaComo::class)->handle(
            vistaComoPeticion('GET', "/portal/actas/{$actaAjena}/pdf", 'portal.actas.pdf'),
            fn (Request $peticion) => app(ActasPortalController::class)->pdf($peticion, $actaAjena, $lectura),
        ))->toThrow(NotFoundHttpException::class);
    }

    // Cada intento consultó solo el contrato 7: nunca `listarFirmadas()` ni un acta suelta por id
    // (la lectura en memoria lanza si alguien lo hace).
    expect($lectura->contratosPedidos)->toBe([7, 7, 7]);
});

it('durante la vista de portal la cuenta observada ocupa el guard cliente y el administrador sigue siendo el interno; al volver no queda nada', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);
    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    $dentro = [];
    app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/portal/actas', 'portal.actas.index'),
        function (Request $peticion) use (&$dentro) {
            $dentro = [
                'cliente' => $peticion->user('cliente')?->id,
                'contrato' => $peticion->user('cliente')?->contrato_id,
                'interno' => $peticion->user('interno')?->id,
            ];

            return response('ok');
        },
    );

    expect($dentro)->toBe(['cliente' => $cliente->id, 'contrato' => 7, 'interno' => $admin->id])
        ->and(Auth::guard('cliente')->user())->toBeNull()
        ->and(Auth::guard('interno')->id())->toBe($admin->id);
});

it('el portal sin una cuenta de cliente en el guard no devuelve nada: 404, nunca un contrato por omisión', function () {
    [, , $lectura] = vistaComoPortalPreparado();

    // Un administrador que pide el portal por su cuenta, sin vista abierta: no tiene contrato.
    expect(fn () => app(ActasPortalController::class)->index(vistaComoPeticion('GET', '/portal/actas', 'portal.actas.index'), $lectura))
        ->toThrow(NotFoundHttpException::class);

    expect($lectura->contratosPedidos)->toBe([]);
});

it('una vista de portal no deja recorrer el panel, y una del panel no deja entrar al portal', function () {
    [$admin, $rolAdmin] = vistaComoAdministrador();
    $cliente = vistaComoCuenta(TipoUsuario::Cliente, [], ['contrato_id' => 7]);
    $jefe = vistaComoCuenta(TipoUsuario::Interno, [vistaComoRol('jefe_campo')]);

    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $cliente->id, null);

    $respuesta = app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/panel/usuarios', 'panel.usuarios.index'),
        fn () => response('pantalla propia del administrador'),
    );

    expect($respuesta->isRedirect())->toBeTrue();

    app(TerminarVistaComo::class)->ejecutar(VistaComoActiva::desdeSesion(Session::get(VistaComoActiva::CLAVE_SESION)), MotivoFinVistaComo::Manual);
    (new IniciarVistaComo)->ejecutar($admin, $rolAdmin, $jefe->id, null);

    $respuesta = app(AplicarVistaComo::class)->handle(
        vistaComoPeticion('GET', '/portal/actas', 'portal.actas.index'),
        fn () => response('portal'),
    );

    expect($respuesta->isRedirect())->toBeTrue();
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
    expect($conSesion)->toBeGreaterThan(100)
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

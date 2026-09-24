<?php

use App\Dominios\Seguridad\Aplicacion\AsignarPermisosRol;
use App\Dominios\Seguridad\Aplicacion\CatalogoDePermisos;
use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolProtegido;
use App\Dominios\Seguridad\Dominio\PermisosReservados;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
 * Tarea 140 (cierre de la decisión abierta) — `seguridad.usuario.ver_como` es un
 * permiso de plataforma: solo el rol `admin_plataforma` puede tenerlo. Sin esto,
 * el propio administrador de plataforma podía delegárselo desde la matriz de
 * permisos a un rol de negocio, que entonces vería como un dueño y leería finanzas.
 *
 * Contra el ESQUEMA REAL (las migraciones de seguridad y de la bitácora, en SQLite
 * en memoria) y el caso de uso real:
 *
 *   1. No se otorga a un rol que no es `admin_plataforma`, aunque quien lo otorga
 *      lo tenga — y la operación entera se rechaza: no queda escrito ni el resto.
 *   2. Sí se otorga a `admin_plataforma`, y cualquier otro permiso sigue
 *      otorgándose con normalidad a cualquier rol (la guarda no sobre-bloquea).
 *   3. Quitarlo de donde no debería estar siempre se puede.
 *   4. El dueño ya no podía otorgarlo por otra vía: la guarda anti-escalada exige
 *      tener el permiso que se otorga y el dueño no lo tiene. Queda fijado acá
 *      porque los documentos decían lo contrario.
 */

uses(TestCase::class);

const RESERVADOS_VER_COMO = 'seguridad.usuario.ver_como';
const RESERVADOS_ASIGNAR = 'seguridad.rol.asignar_permiso';

/**
 * Crea, con las migraciones reales, las tablas que la matriz necesita.
 *
 * SQLite no conoce `CREATE UNIQUE INDEX ... USING btree ... WHERE`: se tolera
 * SOLO ese error de las migraciones con índice parcial de Postgres; cualquier
 * otro rompe la prueba, como debe.
 */
function reservadosEsquema(): void
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

function reservadosPermiso(string $codigo): int
{
    $ahora = now();

    return (int) (DB::table('sec_permission')->where('code', $codigo)->value('id')
        ?? DB::table('sec_permission')->insertGetId([
            'code' => $codigo, 'description' => $codigo, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]));
}

/** @param  list<string>  $permisos códigos de permiso que el rol tiene */
function reservadosRol(string $nombre, array $permisos = []): SecRole
{
    $ahora = now();
    $rolId = DB::table('sec_role')->insertGetId([
        'name' => $nombre, 'description' => $nombre, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    foreach ($permisos as $codigo) {
        DB::table('sec_role_permission')->insert([
            'id_role' => $rolId, 'id_permission' => reservadosPermiso($codigo), 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
    }

    return SecRole::query()->findOrFail($rolId);
}

/**
 * Quien administra la matriz: una cuenta interna cuyo rol activo tiene estos permisos.
 *
 * @param  list<string>  $permisos
 * @return array{0: SecUser, 1: SecRole}
 */
function reservadosActor(string $nombreRol, array $permisos): array
{
    $rol = reservadosRol($nombreRol, $permisos);
    $ahora = now();

    $id = DB::table('sec_user')->insertGetId([
        'name' => "Actor {$nombreRol}",
        'username' => "actor.{$nombreRol}",
        'password' => 'no-se-usa',
        'type' => TipoUsuario::Interno->value,
        'state' => true,
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);
    DB::table('sec_user_role')->insert(['id_user' => $id, 'id_role' => $rol->id, 'created_at' => $ahora, 'updated_at' => $ahora]);

    $actor = SecUser::withTrashed()->findOrFail($id);
    Auth::guard('interno')->setUser($actor);

    return [$actor, $rol];
}

/** @param  list<string>  $codigosDeseados */
function reservadosAsignar(SecUser $actor, SecRole $rolActor, SecRole $destino, array $codigosDeseados): void
{
    $ids = array_map(reservadosPermiso(...), $codigosDeseados);

    (new AsignarPermisosRol(new CatalogoDePermisos))->ejecutar($actor, $destino, $ids, $rolActor->id);
}

/** @return list<string> códigos de los permisos vivos del rol */
function reservadosDelRol(SecRole $rol): array
{
    return DB::table('sec_role_permission as rp')
        ->join('sec_permission as p', 'p.id', '=', 'rp.id_permission')
        ->where('rp.id_role', $rol->id)
        ->whereNull('rp.deleted_at')
        ->orderBy('p.code')
        ->pluck('p.code')
        ->all();
}

beforeEach(function () {
    reservadosEsquema();
});

// --- La regla, en limpio ---------------------------------------------------------------

it('solo el rol admin_plataforma admite los permisos reservados', function () {
    expect(PermisosReservados::SOLO_ADMIN_PLATAFORMA)->toBe([RESERVADOS_VER_COMO])
        ->and(PermisosReservados::esReservado(RESERVADOS_VER_COMO))->toBeTrue()
        ->and(PermisosReservados::esReservado('seguridad.usuario.ver'))->toBeFalse()
        ->and(PermisosReservados::admiteElRol('admin_plataforma'))->toBeTrue()
        ->and(PermisosReservados::admiteElRol('dueno'))->toBeFalse()
        ->and(PermisosReservados::admiteElRol('Admin_Plataforma'))->toBeFalse();
});

// --- 1. No se delega ---------------------------------------------------------------

it('no otorga ver_como a un rol que no es admin_plataforma, aunque quien lo otorga lo tenga', function (string $rolDestino) {
    [$actor, $rolActor] = reservadosActor('admin_plataforma', [RESERVADOS_ASIGNAR, RESERVADOS_VER_COMO]);
    $destino = reservadosRol($rolDestino);

    expect(fn () => reservadosAsignar($actor, $rolActor, $destino, [RESERVADOS_VER_COMO]))
        ->toThrow(RolProtegido::class, RESERVADOS_VER_COMO);

    expect(reservadosDelRol($destino))->toBe([]);
})->with(['dueno', 'encargado_operaciones', 'jefe_campo', 'piloto', 'auxiliar', 'un_rol_nuevo']);

it('rechaza la operación entera: junto a ver_como no se escribe ni el resto', function () {
    [$actor, $rolActor] = reservadosActor('admin_plataforma', [RESERVADOS_ASIGNAR, RESERVADOS_VER_COMO, 'seguridad.usuario.ver']);
    $destino = reservadosRol('encargado_operaciones');

    expect(fn () => reservadosAsignar($actor, $rolActor, $destino, ['seguridad.usuario.ver', RESERVADOS_VER_COMO]))
        ->toThrow(RolProtegido::class);

    expect(reservadosDelRol($destino))->toBe([]);
});

it('el mensaje dice qué permiso y qué rol', function () {
    [$actor, $rolActor] = reservadosActor('admin_plataforma', [RESERVADOS_ASIGNAR, RESERVADOS_VER_COMO]);
    $destino = reservadosRol('encargado_operaciones');

    try {
        reservadosAsignar($actor, $rolActor, $destino, [RESERVADOS_VER_COMO]);
        $this->fail('Debió rechazar el otorgamiento.');
    } catch (RolProtegido $excepcion) {
        expect($excepcion->getMessage())
            ->toContain(RESERVADOS_VER_COMO)
            ->toContain('encargado_operaciones')
            ->toContain('exclusivo del administrador de plataforma');
    }
});

// --- 2. Lo que sí se puede ---------------------------------------------------------------

it('otorga ver_como al rol admin_plataforma', function () {
    [$actor, $rolActor] = reservadosActor('soporte_tecnico', [RESERVADOS_ASIGNAR, RESERVADOS_VER_COMO]);
    $destino = reservadosRol('admin_plataforma');

    reservadosAsignar($actor, $rolActor, $destino, [RESERVADOS_VER_COMO]);

    expect(reservadosDelRol($destino))->toBe([RESERVADOS_VER_COMO]);
});

it('cualquier otro permiso se sigue otorgando con normalidad a un rol de negocio', function () {
    [$actor, $rolActor] = reservadosActor('admin_plataforma', [RESERVADOS_ASIGNAR, RESERVADOS_VER_COMO, 'seguridad.usuario.ver']);
    $destino = reservadosRol('encargado_operaciones');

    reservadosAsignar($actor, $rolActor, $destino, ['seguridad.usuario.ver']);

    expect(reservadosDelRol($destino))->toBe(['seguridad.usuario.ver']);
});

// --- 3. Quitarlo siempre se puede ---------------------------------------------------------------

it('quita ver_como de un rol que lo tenía por un otorgamiento anterior', function () {
    [$actor, $rolActor] = reservadosActor('admin_plataforma', [RESERVADOS_ASIGNAR, RESERVADOS_VER_COMO]);
    $destino = reservadosRol('encargado_operaciones', [RESERVADOS_VER_COMO]);

    reservadosAsignar($actor, $rolActor, $destino, []);

    expect(reservadosDelRol($destino))->toBe([]);
});

it('conservar un ver_como ya otorgado no cuenta como otorgarlo de nuevo', function () {
    [$actor, $rolActor] = reservadosActor('admin_plataforma', [RESERVADOS_ASIGNAR, RESERVADOS_VER_COMO, 'seguridad.usuario.ver']);
    $destino = reservadosRol('encargado_operaciones', [RESERVADOS_VER_COMO]);

    reservadosAsignar($actor, $rolActor, $destino, [RESERVADOS_VER_COMO, 'seguridad.usuario.ver']);

    expect(reservadosDelRol($destino))->toBe(['seguridad.usuario.ver', RESERVADOS_VER_COMO]);
});

// --- 4. El dueño tampoco podía ---------------------------------------------------------------

it('un rol con asignar_permiso pero sin ver_como no lo otorga ni siquiera a admin_plataforma', function () {
    [$actor, $rolActor] = reservadosActor('dueno', [RESERVADOS_ASIGNAR, 'seguridad.usuario.ver']);
    $destino = reservadosRol('admin_plataforma');

    expect(fn () => reservadosAsignar($actor, $rolActor, $destino, [RESERVADOS_VER_COMO]))
        ->toThrow(PermisoDenegado::class);

    expect(reservadosDelRol($destino))->toBe([]);
});

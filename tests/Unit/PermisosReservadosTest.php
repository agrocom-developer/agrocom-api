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
 * Permisos reservados al administrador (ADR 0046, punto 4; plan de cuentas §7). El
 * panel tiene dos roles: `administrador` (todo) y `operador` (su matriz). Usuarios,
 * roles, configuración, «ver como» y la carga de verificación solo los tiene el
 * administrador: sin esto, quien edita la matriz podría delegárselos al operador.
 *
 * Contra el ESQUEMA REAL (las migraciones de seguridad y de la bitácora, en SQLite
 * en memoria) y el caso de uso real:
 *
 *   1. No se otorga un reservado a un rol que no es `administrador`, aunque quien lo
 *      otorga lo tenga — y la operación entera se rechaza: no queda escrito ni el resto.
 *   2. Cualquier otro permiso (también los sensibles) sigue otorgándose al operador.
 *   3. Quitar un reservado de donde no debería estar siempre se puede.
 *   4. La matriz del administrador no se edita y un permiso de app no se otorga a un rol del panel.
 *   5. El preset del operador es solo lectura de lo no sensible.
 */

uses(TestCase::class);

const RESERVADOS_ELIMINAR_USUARIO = 'seguridad.usuario.eliminar';
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
        'dos_roles_de_panel_y_ambito_de_roles_y_permisos',
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

it('solo el rol administrador admite los permisos reservados', function () {
    expect(PermisosReservados::SOLO_ADMINISTRADOR)->toContain(RESERVADOS_ELIMINAR_USUARIO, RESERVADOS_ASIGNAR, 'seguridad.usuario.asignar_rol_administrador', 'seguridad.configuracion.editar')
        ->and(PermisosReservados::esReservado(RESERVADOS_ELIMINAR_USUARIO))->toBeTrue()
        ->and(PermisosReservados::esReservado('solicitudes.solicitud.ver'))->toBeFalse()
        ->and(PermisosReservados::admiteElRol('administrador'))->toBeTrue()
        ->and(PermisosReservados::admiteElRol('operador'))->toBeFalse()
        ->and(PermisosReservados::admiteElRol('admin_plataforma'))->toBeFalse()
        ->and(PermisosReservados::admiteElRol('Administrador'))->toBeFalse()
        ->and(PermisosReservados::ROLES_DEL_PANEL)->toBe(['administrador', 'operador']);
});

it('los sensibles se asignan al operador con aviso y no son reservados', function () {
    foreach (PermisosReservados::SENSIBLES as $codigo) {
        expect(PermisosReservados::esSensible($codigo))->toBeTrue()
            ->and(PermisosReservados::esReservado($codigo))->toBeFalse();
    }

    expect(PermisosReservados::SENSIBLES)->toContain('seguridad.verificacion.ver', 'proveedores.documento.ver', 'solicitudes.solicitud.ver_seguimiento', 'seguridad.bitacora.ver');
});

it('el preset del operador es solo lectura: sin escritura, reservados ni sensibles', function () {
    foreach (PermisosReservados::PRESET_OPERADOR as $codigo) {
        expect($codigo)->toEndWith('.ver')
            ->and(PermisosReservados::esReservado($codigo))->toBeFalse()
            ->and(PermisosReservados::esSensible($codigo))->toBeFalse();
    }
});

// --- 1. No se delega ---------------------------------------------------------------

it('no otorga un reservado a un rol que no es administrador, aunque quien lo otorga lo tenga', function (string $rolDestino, string $reservado) {
    [$actor, $rolActor] = reservadosActor('administrador', [RESERVADOS_ASIGNAR, $reservado]);
    $destino = reservadosRol($rolDestino);

    expect(fn () => reservadosAsignar($actor, $rolActor, $destino, [$reservado]))
        ->toThrow(RolProtegido::class, $reservado);

    expect(reservadosDelRol($destino))->toBe([]);
})->with([
    'operador y eliminar usuarios' => ['operador', RESERVADOS_ELIMINAR_USUARIO],
    'operador y editar usuarios' => ['operador', 'seguridad.usuario.editar'],
    'operador y editar la matriz' => ['operador', RESERVADOS_ASIGNAR],
    'operador y configuración' => ['operador', 'seguridad.configuracion.editar'],
    'operador y carga de verificación' => ['operador', 'seguridad.verificacion.cargar'],
    'un rol cualquiera' => ['un_rol_nuevo', RESERVADOS_ELIMINAR_USUARIO],
]);

it('rechaza la operación entera: junto a un reservado no se escribe ni el resto', function () {
    [$actor, $rolActor] = reservadosActor('administrador', [RESERVADOS_ASIGNAR, RESERVADOS_ELIMINAR_USUARIO, 'solicitudes.solicitud.ver']);
    $destino = reservadosRol('operador');

    expect(fn () => reservadosAsignar($actor, $rolActor, $destino, ['solicitudes.solicitud.ver', RESERVADOS_ELIMINAR_USUARIO]))
        ->toThrow(RolProtegido::class);

    expect(reservadosDelRol($destino))->toBe([]);
});

it('el mensaje dice qué permiso y qué rol', function () {
    [$actor, $rolActor] = reservadosActor('administrador', [RESERVADOS_ASIGNAR, RESERVADOS_ELIMINAR_USUARIO]);
    $destino = reservadosRol('operador');

    try {
        reservadosAsignar($actor, $rolActor, $destino, [RESERVADOS_ELIMINAR_USUARIO]);
        $this->fail('Debió rechazar el otorgamiento.');
    } catch (RolProtegido $excepcion) {
        expect($excepcion->getMessage())
            ->toContain(RESERVADOS_ELIMINAR_USUARIO)
            ->toContain('operador')
            ->toContain('exclusivo del administrador');
    }
});

// --- 2. Lo que sí se puede ---------------------------------------------------------------

it('otorga al operador los permisos asignables y también los sensibles', function () {
    [$actor, $rolActor] = reservadosActor('administrador', [RESERVADOS_ASIGNAR, 'solicitudes.solicitud.ver', 'proveedores.documento.ver']);
    $destino = reservadosRol('operador');

    reservadosAsignar($actor, $rolActor, $destino, ['solicitudes.solicitud.ver', 'proveedores.documento.ver']);

    expect(reservadosDelRol($destino))->toBe(['proveedores.documento.ver', 'solicitudes.solicitud.ver']);
});

// --- 3. Quitarlo siempre se puede ---------------------------------------------------------------

it('quita un reservado de un rol que lo tenía por un otorgamiento anterior', function () {
    [$actor, $rolActor] = reservadosActor('administrador', [RESERVADOS_ASIGNAR, RESERVADOS_ELIMINAR_USUARIO]);
    $destino = reservadosRol('operador', [RESERVADOS_ELIMINAR_USUARIO]);

    reservadosAsignar($actor, $rolActor, $destino, []);

    expect(reservadosDelRol($destino))->toBe([]);
});

it('conservar un reservado ya otorgado no cuenta como otorgarlo de nuevo', function () {
    [$actor, $rolActor] = reservadosActor('administrador', [RESERVADOS_ASIGNAR, RESERVADOS_ELIMINAR_USUARIO, 'solicitudes.solicitud.ver']);
    $destino = reservadosRol('operador', [RESERVADOS_ELIMINAR_USUARIO]);

    reservadosAsignar($actor, $rolActor, $destino, [RESERVADOS_ELIMINAR_USUARIO, 'solicitudes.solicitud.ver']);

    expect(reservadosDelRol($destino))->toBe(['seguridad.usuario.eliminar', 'solicitudes.solicitud.ver']);
});

// --- 4. Matriz del administrador y permisos de app ---------------------------------------------------------------

it('la matriz del administrador no se edita', function () {
    [$actor, $rolActor] = reservadosActor('operador_que_edita', [RESERVADOS_ASIGNAR, 'solicitudes.solicitud.ver']);
    $administrador = reservadosRol('administrador', ['solicitudes.solicitud.ver', 'solicitudes.cliente.ver']);

    expect(fn () => reservadosAsignar($actor, $rolActor, $administrador, ['solicitudes.solicitud.ver']))
        ->toThrow(RolProtegido::class, 'matriz');

    expect(reservadosDelRol($administrador))->toBe(['solicitudes.cliente.ver', 'solicitudes.solicitud.ver']);
});

it('un permiso de app no se otorga a un rol del panel', function () {
    [$actor, $rolActor] = reservadosActor('administrador', [RESERVADOS_ASIGNAR, 'negociacion.oferta.cliente']);
    DB::table('sec_permission')->where('code', 'negociacion.oferta.cliente')->update(['ambito' => 'app']);
    $destino = reservadosRol('operador');

    expect(fn () => reservadosAsignar($actor, $rolActor, $destino, ['negociacion.oferta.cliente']))
        ->toThrow(RolProtegido::class, 'negociacion.oferta.cliente');

    expect(reservadosDelRol($destino))->toBe([]);
});

// --- 5. Quien no tiene el permiso tampoco lo concede ---------------------------------------------------------------

it('quien edita la matriz no concede un permiso que no tiene (anti-escalada)', function () {
    [$actor, $rolActor] = reservadosActor('operador_que_edita', [RESERVADOS_ASIGNAR, 'solicitudes.solicitud.ver']);
    $destino = reservadosRol('otro_rol');

    expect(fn () => reservadosAsignar($actor, $rolActor, $destino, ['solicitudes.cliente.ver']))
        ->toThrow(PermisoDenegado::class);

    expect(reservadosDelRol($destino))->toBe([]);
});

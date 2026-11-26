<?php

use App\Dominios\Seguridad\Aplicacion\AsignarRolesUsuario;
use App\Dominios\Seguridad\Aplicacion\EliminarRol;
use App\Dominios\Seguridad\Aplicacion\GuardarRol;
use App\Dominios\Seguridad\Aplicacion\ListarRolesConPermisos;
use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolProtegido;
use App\Dominios\Seguridad\Dominio\PermisosReservados;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * Roles del panel (ADR 0046, punto 4; plan de cuentas, fase A1): `administrador` y
 * `operador`, una matriz por rol. Contra el esquema real (SQLite en memoria) y el
 * seeder y los casos de uso reales.
 */

uses(TestCase::class);

function matrizMundo(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'agrega_tipo_cuenta_a_sec_user',
        'create_sec_role_table',
        'create_sec_permission_table',
        'create_sec_role_permission_table',
        'create_sec_user_role_table',
        'create_sec_menu_table',
        'dos_roles_de_panel_y_ambito_de_roles_y_permisos',
    ]);
}

/** @return list<string> códigos de los permisos vivos del rol */
function matrizPermisosDe(string $rol): array
{
    return DB::table('sec_role_permission as rp')
        ->join('sec_permission as p', 'p.id', '=', 'rp.id_permission')
        ->join('sec_role as r', 'r.id', '=', 'rp.id_role')
        ->where('r.name', $rol)
        ->whereNull('rp.deleted_at')
        ->orderBy('p.code')
        ->pluck('p.code')
        ->all();
}

function matrizCuenta(string $username, array $roles, array $extra = []): int
{
    $ahora = now();
    $id = DB::table('sec_user')->insertGetId([
        'name' => ucfirst($username), 'username' => $username, 'password' => 'x', 'type' => TipoUsuario::Interno->value,
        'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora, ...$extra,
    ]);

    foreach ($roles as $rol) {
        $rolId = DB::table('sec_role')->where('name', $rol)->value('id')
            ?? DB::table('sec_role')->insertGetId(['name' => $rol, 'description' => $rol, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
        DB::table('sec_user_role')->insert(['id_user' => $id, 'id_role' => $rolId, 'created_at' => $ahora, 'updated_at' => $ahora]);
    }

    return $id;
}

test('el catálogo siembra solo dos roles de panel y deja los de app aparte', function () {
    matrizMundo();
    (new SeguridadSeeder)->run();

    expect(SecRole::query()->where('ambito', 'panel')->orderBy('name')->pluck('name')->all())->toBe(['administrador', 'operador'])
        ->and(SecRole::query()->where('ambito', 'app')->orderBy('name')->pluck('name')->all())->toBe(['cliente', 'piloto', 'proveedor']);
});

test('el administrador tiene todos los permisos del panel y ninguno de las apps', function () {
    matrizMundo();
    (new SeguridadSeeder)->run();

    $delPanel = DB::table('sec_permission')->whereNull('deleted_at')->where('ambito', 'panel')->orderBy('code')->pluck('code')->all();
    $deApp = DB::table('sec_permission')->whereNull('deleted_at')->where('ambito', 'app')->pluck('code')->all();

    expect(matrizPermisosDe('administrador'))->toBe($delPanel)
        ->and($deApp)->not->toBeEmpty()
        ->and(array_intersect(matrizPermisosDe('administrador'), $deApp))->toBe([]);
});

test('los permisos reservados existen en el catálogo y el administrador los tiene', function () {
    matrizMundo();
    (new SeguridadSeeder)->run();

    // `seguridad.verificacion.cargar` lo crea la fase de verificación (A6/A7); hasta entonces solo está en la lista.
    $sembrados = array_values(array_filter(
        PermisosReservados::SOLO_ADMINISTRADOR,
        fn (string $codigo): bool => $codigo !== 'seguridad.verificacion.cargar',
    ));

    expect(array_diff($sembrados, matrizPermisosDe('administrador')))->toBe([]);
});

test('el operador nace con su preset de solo lectura, sin reservados ni sensibles', function () {
    matrizMundo();
    (new SeguridadSeeder)->run();

    $operador = matrizPermisosDe('operador');
    $preset = PermisosReservados::PRESET_OPERADOR;
    sort($preset);

    expect($operador)->toBe($preset);

    foreach ($operador as $codigo) {
        expect($codigo)->toEndWith('.ver')
            ->and(PermisosReservados::esReservado($codigo))->toBeFalse()
            ->and(PermisosReservados::esSensible($codigo))->toBeFalse();
    }
});

test('sembrar de nuevo no pisa lo que el administrador marcó en la matriz del operador, pero repone la suya', function () {
    matrizMundo();
    (new SeguridadSeeder)->run();

    $operadorId = DB::table('sec_role')->where('name', 'operador')->value('id');
    $permisoQuitado = DB::table('sec_permission')->where('code', 'disputas.disputa.ver')->value('id');
    DB::table('sec_role_permission')->where('id_role', $operadorId)->where('id_permission', $permisoQuitado)->update(['deleted_at' => now()]);

    $adminId = DB::table('sec_role')->where('name', 'administrador')->value('id');
    DB::table('sec_role_permission')->where('id_role', $adminId)->where('id_permission', $permisoQuitado)->update(['deleted_at' => now()]);

    (new SeguridadSeeder)->run();

    expect(matrizPermisosDe('operador'))->not->toContain('disputas.disputa.ver')
        ->and(matrizPermisosDe('administrador'))->toContain('disputas.disputa.ver');
});

test('los roles retirados se dan de baja con sus cuentas, que pasan a administrador', function () {
    matrizMundo();
    $ahora = now();

    foreach (['dueno', 'encargado_operaciones', 'jefe_campo', 'soporte', 'admin_plataforma'] as $rol) {
        DB::table('sec_role')->insert(['name' => $rol, 'description' => $rol, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
    }

    $miguelo = matrizCuenta('miguelo', ['admin_plataforma']);
    $ana = matrizCuenta('ana', ['encargado_operaciones', 'jefe_campo']);
    $beto = matrizCuenta('beto', ['soporte']);
    $piloto = matrizCuenta('rodrigo', ['piloto']);

    (new SeguridadSeeder)->run();

    $rolesDe = fn (int $id): array => DB::table('sec_user_role as ur')->join('sec_role as r', 'r.id', '=', 'ur.id_role')
        ->where('ur.id_user', $id)->whereNull('ur.deleted_at')->orderBy('r.name')->pluck('r.name')->all();

    expect($rolesDe($miguelo))->toBe(['administrador'])
        ->and($rolesDe($ana))->toBe(['administrador'])
        ->and($rolesDe($beto))->toBe(['administrador'])
        ->and($rolesDe($piloto))->toBe(['piloto'])
        ->and(SecRole::query()->whereIn('name', ['dueno', 'encargado_operaciones', 'jefe_campo', 'soporte', 'admin_plataforma'])->count())->toBe(0)
        ->and(SecRole::withTrashed()->whereIn('name', ['dueno', 'soporte'])->count())->toBe(2);
});

test('la migración pasa las cuentas de panel a administrador, da de baja lógica los roles viejos y es reversible', function () {
    Esquema::migrar([
        'create_plt_bitacoras_table', 'create_sec_user_table', 'create_sec_role_table', 'create_sec_permission_table',
        'create_sec_role_permission_table', 'create_sec_user_role_table',
    ]);
    $ahora = now();

    foreach (['dueno', 'encargado_operaciones', 'piloto'] as $rol) {
        DB::table('sec_role')->insert(['name' => $rol, 'description' => $rol, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
    }

    DB::table('sec_permission')->insert([
        ['code' => 'solicitudes.solicitud.ver', 'description' => 'x', 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ['code' => 'disputas.disputa.ver', 'description' => 'x', 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ['code' => 'negociacion.oferta.cliente', 'description' => 'x', 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);

    $dueno = matrizCuenta('dueno1', ['dueno']);
    $encargado = matrizCuenta('encargado1', ['encargado_operaciones']);
    $piloto = matrizCuenta('piloto1', ['piloto']);

    $migracion = require database_path('migrations/2026_12_10_100000_dos_roles_de_panel_y_ambito_de_roles_y_permisos.php');
    $migracion->up();

    $rolesDe = fn (int $id): array => DB::table('sec_user_role as ur')->join('sec_role as r', 'r.id', '=', 'ur.id_role')
        ->where('ur.id_user', $id)->whereNull('ur.deleted_at')->orderBy('r.name')->pluck('r.name')->all();

    expect($rolesDe($dueno))->toBe(['administrador'])
        ->and($rolesDe($encargado))->toBe(['administrador'])
        ->and($rolesDe($piloto))->toBe(['piloto'])
        ->and(DB::table('sec_role')->where('name', 'piloto')->value('ambito'))->toBe('app')
        ->and(DB::table('sec_role')->where('name', 'operador')->value('ambito'))->toBe('panel')
        ->and(DB::table('sec_permission')->where('code', 'negociacion.oferta.cliente')->value('ambito'))->toBe('app')
        ->and(DB::table('sec_role')->whereIn('name', ['dueno', 'encargado_operaciones'])->whereNull('deleted_at')->count())->toBe(0)
        ->and(DB::table('sec_role')->whereIn('name', ['dueno', 'encargado_operaciones'])->count())->toBe(2)
        ->and(matrizPermisosDe('administrador'))->toBe(['disputas.disputa.ver', 'solicitudes.solicitud.ver'])
        ->and(matrizPermisosDe('operador'))->toBe(['disputas.disputa.ver', 'solicitudes.solicitud.ver']);

    $migracion->down();

    expect($rolesDe($dueno))->toBe(['dueno'])
        ->and($rolesDe($encargado))->toBe(['encargado_operaciones'])
        ->and($rolesDe($piloto))->toBe(['piloto'])
        ->and(DB::table('sec_role')->whereIn('name', ['dueno', 'encargado_operaciones'])->whereNull('deleted_at')->count())->toBe(2);
});

test('la pantalla de roles lista solo los dos roles del panel y no cuenta permisos de app', function () {
    matrizMundo();
    (new SeguridadSeeder)->run();

    $filas = app(ListarRolesConPermisos::class)->ejecutar();

    expect($filas->map(fn (array $fila): string => $fila['rol']->name)->all())->toBe(['administrador', 'operador']);
});

test('una cuenta de panel tiene un solo rol, de los dos del panel', function () {
    matrizMundo();
    (new SeguridadSeeder)->run();
    $administradorId = (int) DB::table('sec_role')->where('name', 'administrador')->value('id');
    $operadorId = (int) DB::table('sec_role')->where('name', 'operador')->value('id');
    $pilotoId = (int) DB::table('sec_role')->where('name', 'piloto')->value('id');
    $actor = SecUser::query()->findOrFail(matrizCuenta('actor', ['administrador']));
    $asignar = app(AsignarRolesUsuario::class);

    $alta = fn (array $roles): SecUser => $asignar->ejecutar($actor, null, 'nuevo.'.uniqid(), null, 'secreto123', 'Nuevo', TipoUsuario::Interno, null, $roles, null);

    expect($alta([$operadorId]))->toBeInstanceOf(SecUser::class)
        ->and(fn () => $alta([]))->toThrow(RolProtegido::class)
        ->and(fn () => $alta([$operadorId, $administradorId]))->toThrow(RolProtegido::class)
        ->and(fn () => $alta([$pilotoId]))->toThrow(RolProtegido::class);
});

test('asignar el rol administrador exige asignar_rol_administrador', function () {
    matrizMundo();
    (new SeguridadSeeder)->run();
    $administradorId = (int) DB::table('sec_role')->where('name', 'administrador')->value('id');
    $operadorId = (int) DB::table('sec_role')->where('name', 'operador')->value('id');

    // Un operador al que se le marcó (indebidamente) crear usuarios pero no asignar_rol_administrador.
    $permisoCrear = DB::table('sec_permission')->where('code', 'seguridad.usuario.crear')->value('id');
    DB::table('sec_role_permission')->insert(['id_role' => $operadorId, 'id_permission' => $permisoCrear, 'created_at' => now(), 'updated_at' => now()]);
    $operador = SecUser::query()->findOrFail(matrizCuenta('op', ['operador']));

    expect(fn () => app(AsignarRolesUsuario::class)->ejecutar($operador, null, 'nuevo.admin', null, 'secreto123', 'Nuevo', TipoUsuario::Interno, null, [$administradorId], null))
        ->toThrow(PermisoDenegado::class);

    expect(app(AsignarRolesUsuario::class)->ejecutar($operador, null, 'nuevo.op', null, 'secreto123', 'Nuevo', TipoUsuario::Interno, null, [$operadorId], null))
        ->toBeInstanceOf(SecUser::class);
});

test('los roles del panel son fijos: no se crean, no se renombran, no se desactivan el administrador ni se eliminan', function () {
    matrizMundo();
    (new SeguridadSeeder)->run();
    $actor = SecUser::query()->findOrFail(matrizCuenta('admin', ['administrador']));
    $administrador = SecRole::query()->where('name', 'administrador')->firstOrFail();
    $operador = SecRole::query()->where('name', 'operador')->firstOrFail();
    $guardar = app(GuardarRol::class);

    expect(fn () => $guardar->ejecutar($actor, null, 'supervisor', 'Otro', true, null))->toThrow(RolProtegido::class)
        ->and(fn () => $guardar->ejecutar($actor, $operador, 'otro_nombre', 'Otro', true, null))->toThrow(RolProtegido::class)
        ->and(fn () => $guardar->ejecutar($actor, $administrador, 'administrador', 'Otro', false, null))->toThrow(RolProtegido::class)
        ->and(fn () => app(EliminarRol::class)->ejecutar($actor, $operador, null))->toThrow(RolProtegido::class)
        ->and(fn () => app(EliminarRol::class)->ejecutar($actor, $administrador, null))->toThrow(RolProtegido::class);

    $editado = $guardar->ejecutar($actor, $operador, 'operador', 'Atiende la operación diaria.', true, null);

    expect($editado->description)->toBe('Atiende la operación diaria.');
});

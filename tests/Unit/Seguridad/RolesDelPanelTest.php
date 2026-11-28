<?php

use App\Dominios\Compartido\Infraestructura\Http\Middleware\RecordarOrigenNavegacion;
use App\Dominios\Seguridad\Aplicacion\AsignarRolesUsuario;
use App\Dominios\Seguridad\Aplicacion\CatalogoDePermisos;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Dominio\Excepciones\RolProtegido;
use App\Dominios\Seguridad\Dominio\PermisosReservados;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\ColorDeModulo;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * Roles del panel (/panel/roles): listado, ficha, alta, edición, baja y matriz de permisos con
 * sus permisos seguridad.rol.*, y las reglas que protegen al sistema: el rol 1 (administrador)
 * no se elimina ni se toca por la vía que sea, un rol con cuentas tampoco, y el sistema no se
 * queda sin administrador. Contra el esquema real (SQLite en memoria), el seeder y los casos de
 * uso reales; la autorización de la pantalla evalúa los permisos reales del rol activo (no un
 * doble que los concede todos), y la cáscara de la vista es mínima.
 */

uses(TestCase::class);

beforeEach(function () {
    // La prueba no compila assets: la vista no debe pedir el manifiesto de Vite.
    $this->withoutVite();

    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'agrega_tipo_cuenta_a_sec_user',
        'create_sec_user_preferencia_table',
        'create_sec_role_table',
        'create_sec_permission_table',
        'create_sec_role_permission_table',
        'create_sec_user_role_table',
        'create_sec_menu_table',
        'dos_roles_de_panel_y_ambito_de_roles_y_permisos',
    ]);

    (new SeguridadSeeder)->run();
    (new SecMenuSeeder)->run();

    app()->instance(AutorizacionPanelWeb::class, new class implements AutorizacionPanelWeb
    {
        public function tienePermiso(Request $request, string $codigoPermiso): bool
        {
            return $request->user('interno')?->tienePermisoEnRol($codigoPermiso, (int) $request->session()->get('sec_rol_activo_id')) ?? false;
        }

        public function cascara(Request $request): array
        {
            return [
                'menu' => [], 'roles' => collect(), 'rolActivoId' => (int) $request->session()->get('sec_rol_activo_id'),
                'activeRoleLabel' => 'Administrador', 'userName' => 'Prueba', 'tema' => 'light', 'zonaHoraria' => null,
                'notifications' => [], 'menuBadges' => [], 'version' => 'prueba',
            ];
        }

        public function personaId(Request $request): ?int
        {
            return null;
        }

        public function primerDestinoVisible(Request $request): string
        {
            return '/panel';
        }
    });

    // La sesión se queda (el rol activo vive en ella); fuera el CSRF, el rol activo y la pila de navegación.
    $this->withoutMiddleware([ValidateCsrfToken::class, ResolverRolActivo::class, RecordarOrigenNavegacion::class]);
});

function rolesCuenta(string $username, string $rol): SecUser
{
    $ahora = now();
    $id = DB::table('sec_user')->insertGetId([
        'name' => ucfirst($username), 'username' => $username, 'password' => 'x', 'type' => TipoUsuario::Interno->value,
        'tipo_cuenta' => 'panel', 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    DB::table('sec_user_role')->insert(['id_user' => $id, 'id_role' => SecRole::query()->where('name', $rol)->value('id'), 'created_at' => $ahora, 'updated_at' => $ahora]);

    return SecUser::query()->findOrFail($id);
}

/** Entra como `$usuario`, operando con su rol. */
function rolesEntrar(object $prueba, SecUser $usuario, string $rol = 'administrador'): void
{
    $prueba->actingAs($usuario, 'interno');
    $prueba->withSession(['sec_rol_activo_id' => (int) SecRole::query()->where('name', $rol)->value('id')]);
}

function rolesCrear(string $nombre, bool $conPermisos = false): SecRole
{
    $rol = SecRole::query()->create(['name' => $nombre, 'description' => 'Rol de prueba', 'ambito' => 'panel', 'state' => true]);

    if ($conPermisos) {
        DB::table('sec_role_permission')->insert([
            'id_role' => $rol->id, 'id_permission' => DB::table('sec_permission')->where('code', 'solicitudes.solicitud.ver')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $rol;
}

// --- Listado -------------------------------------------------------------------------------

test('el listado de roles pide seguridad.rol.ver y lo ve el administrador con sus acciones de fila', function () {
    $admin = rolesCuenta('admin', 'administrador');
    $operador = rolesCuenta('op', 'operador');

    rolesEntrar($this, $operador, 'operador');
    $this->get('/panel/roles')->assertForbidden();

    rolesEntrar($this, $admin);
    $respuesta = $this->get('/panel/roles')->assertOk()->assertSee('Roles y permisos');
    $html = $respuesta->getContent();

    // Las acciones de la fila en el orden del panel: Ver, Editar, Permisos y Eliminar al final.
    $filaOperador = substr($html, (int) strpos($html, 'rol-eliminar-form') ?: 0);
    expect($html)->toContain('ag-table-toolbar')
        ->toContain('Limpiar')
        ->and(strpos($html, 'data-ag-row-action="ver"'))->toBeLessThan(strpos($html, 'data-ag-row-action="editar"'))
        ->and(strpos($html, 'data-ag-row-action="editar"'))->toBeLessThan(strpos($html, 'data-ag-row-action="permisos"'))
        ->and(strpos($html, 'data-ag-row-action="permisos"'))->toBeLessThan(strpos($html, 'data-ag-row-action="eliminar"'))
        ->and($filaOperador)->not->toBe('');
});

test('el listado no ofrece Eliminar para el rol 1 (administrador) y sí para los demás', function () {
    $admin = rolesCuenta('admin', 'administrador');
    $operador = SecRole::query()->where('name', 'operador')->firstOrFail();
    $administrador = SecRole::query()->where('name', 'administrador')->firstOrFail();
    rolesEntrar($this, $admin);

    $html = $this->get('/panel/roles')->assertOk()->getContent();

    expect($administrador->id)->toBe(1)
        ->and($html)->toContain('id="rol-eliminar-modal-'.$operador->id.'"')
        ->and($html)->not->toContain('rol-eliminar-modal-'.$administrador->id.'"')
        ->and($html)->not->toContain('id="rol-eliminar-'.$administrador->id.'"')
        ->and($html)->toContain(route('panel.roles.show', $administrador))
        ->and($html)->toContain(route('panel.roles.permisos.edit', $administrador));
});

test('el buscador y el filtro de estado acotan el listado y el vacío orienta', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    rolesCrear('Supervisor de campo');

    $this->get('/panel/roles?q=supervisor')->assertOk()->assertSee('Supervisor de campo')->assertDontSee('Administrador del panel');
    $this->get('/panel/roles?q=zzzz')->assertOk()->assertSee('Ningún rol coincide');
    $this->get('/panel/roles?estado=inactivo')->assertOk()->assertSee('Ningún rol coincide');
});

// --- Ficha ---------------------------------------------------------------------------------

test('la ficha del rol muestra sus datos, las cuentas que lo tienen enlazadas y el resumen por módulo con su color', function () {
    $admin = rolesCuenta('admin', 'administrador');
    $ana = rolesCuenta('ana', 'operador');
    $operador = SecRole::query()->where('name', 'operador')->firstOrFail();
    rolesEntrar($this, $admin);

    $html = $this->get(route('panel.roles.show', $operador))->assertOk()->getContent();

    expect($html)->toContain('Ana')
        ->toContain(route('panel.usuarios.show', $ana->id))
        ->toContain('ag-modulo-tono--success')
        ->toContain('ag-modulo-tono--info')
        ->toContain(route('panel.roles.edit', $operador))
        ->toContain(route('panel.roles.permisos.edit', $operador))
        ->toContain('rol-eliminar-modal');

    // La del administrador no ofrece Eliminar.
    $administrador = SecRole::query()->where('name', 'administrador')->firstOrFail();
    $this->get(route('panel.roles.show', $administrador))->assertOk()->assertDontSee('rol-eliminar-modal')->assertSee('Sistema');

    // Un rol de app no es del panel: no hay ficha.
    $this->get(route('panel.roles.show', SecRole::query()->where('name', 'piloto')->firstOrFail()))->assertNotFound();
});

test('la ficha sin seguridad.rol.ver responde 403', function () {
    rolesEntrar($this, rolesCuenta('op', 'operador'), 'operador');

    $this->get(route('panel.roles.show', SecRole::query()->where('name', 'operador')->firstOrFail()))->assertForbidden();
});

// --- Alta y edición ------------------------------------------------------------------------

test('crear un rol lo deja sin permisos y aterriza en su matriz; sin seguridad.rol.crear responde 403', function () {
    rolesEntrar($this, rolesCuenta('op', 'operador'), 'operador');
    $this->get('/panel/roles/crear')->assertForbidden();
    $this->post('/panel/roles', ['name' => 'Taller', 'description' => 'x', 'state' => '1'])->assertForbidden();

    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    $this->get('/panel/roles/crear')->assertOk()->assertSee('Nuevo rol');

    // El switch del formulario manda `on` cuando está marcado.
    $respuesta = $this->post('/panel/roles', ['name' => 'Supervisor de campo', 'description' => 'Supervisa los servicios.', 'state' => 'on']);
    $rol = SecRole::query()->where('name', 'Supervisor de campo')->firstOrFail();

    $respuesta->assertRedirect(route('panel.roles.permisos.edit', $rol));
    expect($rol->ambito)->toBe('panel')
        ->and($rol->state)->toBeTrue()
        ->and(app(CatalogoDePermisos::class)->idsPermisoDeRol($rol->id))->toBe([])
        ->and(DB::table('plt_bitacoras')->where('tabla', 'sec_role')->where('registro_id', $rol->id)->exists())->toBeTrue();
});

test('el nombre del rol se valida: obligatorio, formato y único sin distinguir mayúsculas', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    rolesCrear('Taller');

    $this->post('/panel/roles', ['description' => 'x'])->assertSessionHasErrors(['name']);
    $this->post('/panel/roles', ['name' => 'a', 'description' => 'x'])->assertSessionHasErrors(['name']);
    $this->post('/panel/roles', ['name' => '@@@ roles', 'description' => 'x'])->assertSessionHasErrors(['name']);
    $this->post('/panel/roles', ['name' => 'TALLER', 'description' => 'x'])->assertSessionHasErrors(['name']);
    $this->post('/panel/roles', ['name' => 'Administrador', 'description' => 'x'])->assertSessionHasErrors(['name']);
    $this->post('/panel/roles', ['name' => 'Bodega', 'description' => ''])->assertSessionHasErrors(['description']);

    expect(SecRole::query()->where('ambito', 'panel')->count())->toBe(3);
});

test('editar un rol cambia su nombre, descripción y estado y vuelve a la ficha', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    $rol = rolesCrear('Taller');

    $this->get(route('panel.roles.edit', $rol))->assertOk()->assertSee('Editar Taller');

    $this->put(route('panel.roles.update', $rol), ['name' => 'Taller central', 'description' => 'Nueva.', '_enviado' => '1'])
        ->assertRedirect(route('panel.roles.show', $rol));

    $rol->refresh();
    expect($rol->name)->toBe('Taller central')->and($rol->description)->toBe('Nueva.')->and($rol->state)->toBeFalse();
});

test('el rol 1 conserva su nombre y su estado aunque se mande el formulario a mano', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    $administrador = SecRole::query()->where('name', 'administrador')->firstOrFail();

    $this->from('/x')->put(route('panel.roles.update', $administrador), ['name' => 'jefe', 'description' => 'x', 'state' => '1'])
        ->assertSessionHasErrors('estado');
    $this->from('/x')->put(route('panel.roles.update', $administrador), ['name' => 'administrador', 'description' => 'x'])
        ->assertSessionHasErrors('estado');

    $administrador->refresh();
    expect($administrador->name)->toBe('administrador')->and($administrador->state)->toBeTrue();
});

// --- Baja ----------------------------------------------------------------------------------

test('eliminar el rol 1 por URL directa se rechaza con un mensaje y no borra nada', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    $administrador = SecRole::query()->where('name', 'administrador')->firstOrFail();
    $permisosAntes = DB::table('sec_role_permission')->where('id_role', $administrador->id)->whereNull('deleted_at')->count();

    $this->from('/panel/roles')->delete(route('panel.roles.destroy', $administrador))
        ->assertRedirect(route('panel.roles.index'))
        ->assertSessionHasErrors('estado');

    expect(SecRole::query()->whereKey(1)->exists())->toBeTrue()
        ->and(DB::table('sec_role_permission')->where('id_role', $administrador->id)->whereNull('deleted_at')->count())->toBe($permisosAntes);
});

test('eliminar exige seguridad.rol.eliminar: sin él responde 403 y el rol sigue vivo', function () {
    $rol = rolesCrear('Taller');
    rolesEntrar($this, rolesCuenta('op', 'operador'), 'operador');

    $this->delete(route('panel.roles.destroy', $rol))->assertForbidden();

    expect(SecRole::query()->whereKey($rol->id)->exists())->toBeTrue();
});

test('un rol sin cuentas se elimina con sus permisos, queda en la bitácora y su nombre sigue ocupado', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    $rol = rolesCrear('Taller', conPermisos: true);

    $this->delete(route('panel.roles.destroy', $rol))->assertRedirect(route('panel.roles.index'))->assertSessionHas('estado');

    expect(SecRole::query()->whereKey($rol->id)->exists())->toBeFalse()
        ->and(SecRole::withTrashed()->whereKey($rol->id)->exists())->toBeTrue()
        ->and(DB::table('sec_role_permission')->where('id_role', $rol->id)->whereNull('deleted_at')->count())->toBe(0)
        ->and(DB::table('plt_bitacoras')->where('tabla', 'sec_role')->where('registro_id', $rol->id)->count())->toBeGreaterThanOrEqual(2);

    // El nombre del rol eliminado no se libera: crearlo de nuevo se rechaza en el campo.
    $this->post('/panel/roles', ['name' => 'Taller', 'description' => 'x'])->assertSessionHasErrors('name');
    // Y el sembrado no choca con un rol sembrado y eliminado (el operador).
    $operador = SecRole::query()->where('name', 'operador')->firstOrFail();
    $this->delete(route('panel.roles.destroy', $operador))->assertSessionHas('estado');
    (new SeguridadSeeder)->run();
    expect(SecRole::query()->where('name', 'operador')->exists())->toBeFalse();
});

test('un rol con cuentas asignadas no se elimina: hay que reasignarlas antes', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    $rol = rolesCrear('Taller');
    $cuenta = rolesCuenta('beto', 'operador');
    DB::table('sec_user_role')->where('id_user', $cuenta->id)->update(['id_role' => $rol->id]);

    $this->from('/panel/roles')->delete(route('panel.roles.destroy', $rol))->assertSessionHasErrors('estado');
    expect(SecRole::query()->whereKey($rol->id)->exists())->toBeTrue();

    // Con la cuenta ya reasignada se puede.
    DB::table('sec_user_role')->where('id_user', $cuenta->id)->update(['id_role' => SecRole::query()->where('name', 'operador')->value('id')]);
    $this->delete(route('panel.roles.destroy', $rol))->assertSessionHas('estado');
    expect(SecRole::query()->whereKey($rol->id)->exists())->toBeFalse();
});

test('el rol con el que operas no se elimina', function () {
    // Un rol propio con la llave de eliminar (reservada al administrador, así que se le da a mano).
    $propio = rolesCrear('Jefatura');
    foreach (['seguridad.rol.ver', 'seguridad.rol.eliminar'] as $codigo) {
        DB::table('sec_role_permission')->insert(['id_role' => $propio->id, 'id_permission' => DB::table('sec_permission')->where('code', $codigo)->value('id'), 'created_at' => now(), 'updated_at' => now()]);
    }
    rolesEntrar($this, rolesCuenta('jefe', 'Jefatura'), 'Jefatura');

    $this->from('/panel/roles')->delete(route('panel.roles.destroy', $propio))->assertSessionHasErrors('estado');
    expect(SecRole::query()->whereKey($propio->id)->exists())->toBeTrue();
});

// --- Permisos ------------------------------------------------------------------------------

test('la pantalla de permisos pinta cada módulo con su color, su contador y su interruptor «Todo el módulo»', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    $rol = rolesCrear('Taller', conPermisos: true);

    $html = $this->get(route('panel.roles.permisos.edit', $rol))->assertOk()->getContent();

    expect($html)->toContain('data-ag-modulo-todo')
        ->toContain('Todo el módulo')
        ->toContain('data-ag-modulo-resumen')
        ->toContain('ag-modulo-tono--success')
        ->toContain('ag-modulo-tono--distintivo-1')
        ->and(substr_count($html, 'data-ag-modulo-todo'))->toBeGreaterThanOrEqual(5);
});

test('la matriz del administrador no se edita ni por URL directa', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    $administrador = SecRole::query()->where('name', 'administrador')->firstOrFail();
    $antes = app(CatalogoDePermisos::class)->idsPermisoDeRol($administrador->id);

    $this->get(route('panel.roles.permisos.edit', $administrador))->assertOk()->assertSee('no se edita');
    $this->from('/x')->put(route('panel.roles.permisos.update', $administrador), ['permisos' => [$antes[0]]])->assertSessionHasErrors('estado');

    expect(app(CatalogoDePermisos::class)->idsPermisoDeRol($administrador->id))->toBe($antes);
});

test('a un rol creado no se le dan permisos reservados al administrador', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    $rol = rolesCrear('Taller');
    $reservado = DB::table('sec_permission')->where('code', 'seguridad.rol.eliminar')->value('id');

    $this->from('/x')->put(route('panel.roles.permisos.update', $rol), ['permisos' => [$reservado]])->assertSessionHasErrors('estado');

    expect(app(CatalogoDePermisos::class)->idsPermisoDeRol($rol->id))->toBe([]);
});

test('un rol creado recibe permisos del catálogo desde la matriz', function () {
    rolesEntrar($this, rolesCuenta('admin', 'administrador'));
    $rol = rolesCrear('Taller');
    $ver = (int) DB::table('sec_permission')->where('code', 'solicitudes.solicitud.ver')->value('id');

    $this->put(route('panel.roles.permisos.update', $rol), ['permisos' => [$ver]])->assertSessionHas('estado');

    expect(app(CatalogoDePermisos::class)->idsPermisoDeRol($rol->id))->toBe([$ver]);
});

// --- Cuentas -------------------------------------------------------------------------------

test('una cuenta puede tener un rol creado en el panel, y el sistema no se queda sin administrador', function () {
    $actor = rolesCuenta('admin', 'administrador');
    $rol = rolesCrear('Taller');
    $administradorId = (int) SecRole::query()->where('name', 'administrador')->value('id');
    $asignar = app(AsignarRolesUsuario::class);

    $creada = $asignar->ejecutar($actor, null, 'nuevo.taller', null, 'secreto123', 'Nuevo', TipoUsuario::Interno, [$rol->id], null);
    expect($creada->idsDeRoles())->toBe([$rol->id]);

    // La única cuenta con el rol administrador no se pasa a otro rol.
    expect(fn () => $asignar->ejecutar($actor, $actor->id, 'admin', null, null, 'Admin', TipoUsuario::Interno, [$rol->id], null))
        ->toThrow(RolProtegido::class);

    // Con una segunda cuenta administradora activa, sí.
    $segunda = rolesCuenta('segunda', 'administrador');
    $asignar->ejecutar($segunda, $actor->id, 'admin', null, null, 'Admin', TipoUsuario::Interno, [$rol->id], null);
    expect($actor->refresh()->idsDeRoles())->toBe([$rol->id])->and($segunda->idsDeRoles())->toBe([$administradorId]);
});

test('el listado de usuarios y la ficha de usuario enlazan el rol de la cuenta a «Ver rol»', function () {
    $raiz = dirname(__DIR__, 3).'/app/Dominios/Seguridad/Infraestructura/Http/Views/pages/usuarios';

    foreach (['index', 'show'] as $vista) {
        expect((string) file_get_contents($raiz.'/'.$vista.'.blade.php'))->toContain("route('panel.roles.show'");
    }

    $roles = (string) file_get_contents(dirname($raiz).'/roles/show.blade.php');
    expect($roles)->toContain("route('panel.usuarios.show'");
});

// --- Color por módulo ----------------------------------------------------------------------

test('cada módulo del menú tiene un color propio, estable y distinto del de los demás', function () {
    $arbol = app(CatalogoDePermisos::class)->arbolDeConcesiones();
    $claves = array_column($arbol['modulos'], 'clave');
    $claves[] = ColorDeModulo::CLAVE_SUELTOS;

    $tonos = ColorDeModulo::asignar($claves);

    // Todos los módulos reales tienen su tono declarado (no caen en la reserva) y no se repite ninguno.
    foreach ($claves as $clave) {
        expect(ColorDeModulo::POR_MODULO)->toHaveKey($clave);
    }
    expect($tonos)->toHaveCount(count($claves))
        ->and(array_unique($tonos))->toHaveCount(count($claves))
        ->and(ColorDeModulo::asignar(array_reverse($claves)))->toBe($tonos)
        ->and(array_intersect($tonos, ['danger', 'alert', 'primary-2', 'distintivo-3']))->toBe([]);

    // Un módulo nuevo toma un tono libre de la reserva y sigue sin repetirse.
    $conNuevo = ColorDeModulo::asignar([...$claves, 'menu.nuevo.label']);
    expect($conNuevo['menu.nuevo.label'])->toBeIn(ColorDeModulo::RESERVA)
        ->and(array_unique($conNuevo))->toHaveCount(count($claves) + 1)
        ->and(array_diff_key($conNuevo, ['menu.nuevo.label' => 1]))->toBe($tonos);

    // Y cada tono tiene su clase y sus tokens en claro y oscuro.
    $css = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/components/modulo-tono.css');
    $claro = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/tokens/semantic/theme-light.css');
    $oscuro = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/tokens/semantic/theme-dark.css');

    foreach ([...array_values(ColorDeModulo::POR_MODULO), ...ColorDeModulo::RESERVA] as $tono) {
        expect($css)->toContain(".ag-modulo-tono--{$tono} {");

        if ($tono !== 'neutral') {
            expect($claro)->toContain("--ag-color-{$tono}-contrast-fill:")->and($oscuro)->toContain("--ag-color-{$tono}-contrast-fill:");
        }
    }
});

test('los permisos seguridad.rol.* existen, son reservados al administrador y el operador no los recibe', function () {
    $codigos = ['seguridad.rol.ver', 'seguridad.rol.crear', 'seguridad.rol.editar', 'seguridad.rol.eliminar', 'seguridad.rol.asignar_permiso'];

    foreach ($codigos as $codigo) {
        expect(DB::table('sec_permission')->where('code', $codigo)->whereNull('deleted_at')->exists())->toBeTrue()
            ->and(PermisosReservados::esReservado($codigo))->toBeTrue()
            ->and(PermisosReservados::PRESET_OPERADOR)->not->toContain($codigo);
    }
});

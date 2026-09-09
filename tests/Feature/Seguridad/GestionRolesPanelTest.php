<?php

use App\Dominios\Seguridad\Aplicacion\CatalogoDePermisos;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Administración de roles y de la matriz rol↔permiso desde el panel — la
 * última parte del modelo `sec_*` que solo existía como seeder.
 *
 * El grueso de este archivo son las GUARDAS, no los caminos felices: esta
 * pantalla es la primera que permite dejar el sistema en un estado del que no
 * se vuelve sin acceso al servidor (un permiso sin ningún rol, un panel sin
 * nadie que pueda administrar permisos, el propio actor fuera de la pantalla
 * que está usando). Cada una de esas cuatro formas de tirar la llave adentro
 * de la casa tiene su test.
 *
 * Permisos evaluados contra el ROL ACTIVO de la sesión, nunca la unión de los
 * roles del usuario (invariante 10 de CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Seguridad/GestionUsuariosPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaRoles(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

/** Entra al panel con un rol activo fijado, como haría el login. */
function entrarAlPanelParaRoles(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function idPermiso(string $codigo): int
{
    return (int) SecPermission::query()->where('code', $codigo)->value('id');
}

/** IDs vivos del rol, tal como los ve el catálogo. */
function permisosDe(int $idRol): array
{
    return app(CatalogoDePermisos::class)->idsPermisoDeRol($idRol);
}

/** El dueño es el único rol con los permisos de esta pantalla. */
function duenoEnElPanel(): array
{
    [$usuario, $idRol] = usuarioConRolParaRoles('dueno.panel', 'dueno');
    entrarAlPanelParaRoles($usuario, $idRol);

    return [$usuario, $idRol];
}

// ----------------------------------------------------------------- acceso

it('gatea las cuatro pantallas por sus permisos, contra el rol activo', function () {
    // `encargado_operaciones` tiene 84 de los 96 permisos, pero ninguno de
    // los cinco de `seguridad.rol.*`: es exactamente el caso que la pantalla
    // tiene que rechazar, y el que un `tienePermiso()` por unión dejaría
    // pasar si el usuario tuviera además otro rol.
    [$encargado, $idRol] = usuarioConRolParaRoles('encargado', 'encargado_operaciones');
    entrarAlPanelParaRoles($encargado, $idRol);

    $otro = SecRole::query()->where('name', 'piloto')->sole();

    $this->get(route('panel.roles.index'))->assertForbidden();
    $this->get(route('panel.roles.create'))->assertForbidden();
    $this->get(route('panel.roles.edit', $otro))->assertForbidden();
    $this->get(route('panel.roles.permisos.edit', $otro))->assertForbidden();
});

it('el dueño ve el listado con las pantallas y las acciones contadas por separado', function () {
    duenoEnElPanel();

    $this->get(route('panel.roles.index'))
        ->assertOk()
        ->assertSee('Jefe de campo')
        ->assertSee('jefe_campo')
        ->assertSee(__('seguridad.roles.col_pantallas'));
});

it('el ítem de menú de roles solo aparece para quien tiene seguridad.rol.ver', function () {
    duenoEnElPanel();
    $this->get(route('panel.roles.index'))->assertOk()->assertSee(__('menu.seguridad.items.roles'));

    [$encargado, $idRol] = usuarioConRolParaRoles('encargado2', 'encargado_operaciones');
    entrarAlPanelParaRoles($encargado, $idRol);

    $this->get(route('panel.usuarios.index'))
        ->assertOk()
        ->assertDontSee(__('menu.seguridad.items.roles'));
});

// -------------------------------------------------------------- alta y baja

it('da de alta un rol sin ningún permiso y lleva directo a dárselos', function () {
    duenoEnElPanel();

    $this->post(route('panel.roles.store'), [
        'name' => 'supervisor_taller',
        'description' => 'Supervisa el taller.',
        'state' => '1',
        '_enviado' => '1',
    ])->assertRedirect();

    $rol = SecRole::query()->where('name', 'supervisor_taller')->sole();

    expect($rol->state)->toBeTrue()
        ->and(permisosDe($rol->id))->toBe([]);

    $this->post(route('panel.roles.store'), [
        'name' => 'otro_rol',
        'description' => 'Otro.',
        '_enviado' => '1',
    ])->assertRedirect(route('panel.roles.permisos.edit', SecRole::query()->where('name', 'otro_rol')->sole()));
});

it('rechaza un nombre interno que no respeta la forma del catálogo', function () {
    duenoEnElPanel();

    $this->post(route('panel.roles.store'), [
        'name' => 'Jefe De Taller',
        'description' => 'Con mayúsculas y espacios.',
        '_enviado' => '1',
    ])->assertSessionHasErrors('name');

    expect(SecRole::query()->count())->toBe(5);
});

it('un rol dado de baja no libera su nombre', function () {
    [$dueno] = duenoEnElPanel();

    $rol = SecRole::query()->create(['name' => 'temporal', 'description' => 'Temporal.', 'state' => true]);
    $rol->delete();

    $this->post(route('panel.roles.store'), [
        'name' => 'temporal',
        'description' => 'Otra cosa con el mismo nombre.',
        '_enviado' => '1',
    ])->assertSessionHasErrors('name');
});

it('no deja dar de baja un rol con cuentas vivas detrás', function () {
    duenoEnElPanel();

    // El piloto del seeder demo no tiene cuentas; se le crea una.
    $piloto = SecRole::query()->where('name', 'piloto')->sole();
    [$usuarioPiloto] = usuarioConRolParaRoles('piloto.uno', 'piloto');

    $this->delete(route('panel.roles.destroy', $piloto))
        ->assertRedirect(route('panel.roles.index'))
        ->assertSessionHasErrors('estado');

    expect($piloto->fresh()->trashed())->toBeFalse();
});

it('da de baja un rol sin usuarios y le da de baja también sus permisos', function () {
    duenoEnElPanel();

    $auxiliar = SecRole::query()->where('name', 'auxiliar')->sole();
    expect(permisosDe($auxiliar->id))->not->toBe([]);

    $this->delete(route('panel.roles.destroy', $auxiliar))
        ->assertRedirect(route('panel.roles.index'))
        ->assertSessionHasNoErrors();

    expect($auxiliar->fresh()->trashed())->toBeTrue()
        // El pivote acompaña: si quedara vivo, el catálogo contaría un rol
        // muerto como portador de sus permisos.
        ->and(permisosDe($auxiliar->id))->toBe([])
        ->and(SecRolePermission::withTrashed()->where('id_role', $auxiliar->id)->whereNotNull('deleted_at')->exists())->toBeTrue();
});

it('no deja dar de baja ni desactivar el rol con el que se está operando', function () {
    [$dueno, $idRolActivo] = duenoEnElPanel();
    $propio = SecRole::query()->findOrFail($idRolActivo);

    $this->delete(route('panel.roles.destroy', $propio))->assertSessionHasErrors('estado');
    expect($propio->fresh()->trashed())->toBeFalse();

    $this->put(route('panel.roles.update', $propio), [
        'name' => $propio->name,
        'description' => $propio->description,
        '_enviado' => '1',
    ])->assertSessionHasErrors('estado');

    expect($propio->fresh()->state)->toBeTrue();
});

// ------------------------------------------------------ matriz de permisos

it('guarda el set completo: lo que no viene en el payload se revoca', function () {
    duenoEnElPanel();

    $jefe = SecRole::query()->where('name', 'jefe_campo')->sole();
    $verTrabajo = idPermiso('operaciones.trabajo.ver');
    $verOrden = idPermiso('operaciones.orden.ver');

    expect(permisosDe($jefe->id))->toContain($verTrabajo);

    $this->put(route('panel.roles.permisos.update', $jefe), ['permisos' => [$verOrden]])
        ->assertRedirect(route('panel.roles.permisos.edit', $jefe))
        ->assertSessionHasNoErrors();

    expect(permisosDe($jefe->id))->toBe([$verOrden]);
});

it('reactiva el otorgamiento anterior en vez de acumular filas muertas', function () {
    duenoEnElPanel();

    $jefe = SecRole::query()->where('name', 'jefe_campo')->sole();
    $verTrabajo = idPermiso('operaciones.trabajo.ver');

    $filasAntes = SecRolePermission::withTrashed()->where('id_role', $jefe->id)->count();

    // Quitar y devolver el mismo permiso: es el mismo hecho de negocio, no
    // uno nuevo — si insertara, la tabla juntaría una fila por cada duda.
    $this->put(route('panel.roles.permisos.update', $jefe), ['permisos' => []]);
    $this->put(route('panel.roles.permisos.update', $jefe), ['permisos' => [$verTrabajo]]);

    expect(permisosDe($jefe->id))->toBe([$verTrabajo])
        ->and(SecRolePermission::withTrashed()->where('id_role', $jefe->id)->count())->toBe($filasAntes);
});

it('no deja que un permiso quede sin ningún rol vivo que lo tenga', function () {
    [$dueno, $idRolActivo] = duenoEnElPanel();

    // `finanzas.planilla.aprobar` lo sostiene solo el dueño: quitárselo lo
    // dejaría huérfano y, como nadie puede conceder lo que no tiene, ningún
    // rol podría recuperarlo desde el panel.
    $aprobar = idPermiso('finanzas.planilla.aprobar');
    $rolDueno = SecRole::query()->findOrFail($idRolActivo);

    expect(app(CatalogoDePermisos::class)->idsPermisoConPortadorUnico($rolDueno->id))->toContain($aprobar);

    $sinEse = array_values(array_diff(permisosDe($rolDueno->id), [$aprobar]));

    $this->put(route('panel.roles.permisos.update', $rolDueno), ['permisos' => $sinEse])
        ->assertSessionHasErrors('estado');

    expect(permisosDe($rolDueno->id))->toContain($aprobar);
});

it('no deja que el actor se quite la llave de su propio rol activo', function () {
    [$dueno, $idRolActivo] = duenoEnElPanel();
    $rolDueno = SecRole::query()->findOrFail($idRolActivo);

    $asignar = idPermiso('seguridad.rol.asignar_permiso');

    // Se le da la llave a otro rol vivo primero, para que la guarda del
    // permiso huérfano NO sea la que dispare: lo que se prueba acá es la
    // otra, la de no quedarse afuera uno mismo.
    $piloto = SecRole::query()->where('name', 'piloto')->sole();
    (new SecRolePermission(['id_role' => $piloto->id, 'id_permission' => $asignar]))->save();
    (new SecRolePermission(['id_role' => $piloto->id, 'id_permission' => idPermiso('seguridad.rol.ver')]))->save();

    $sinLlave = array_values(array_diff(permisosDe($rolDueno->id), [$asignar]));

    $this->put(route('panel.roles.permisos.update', $rolDueno), ['permisos' => $sinLlave])
        ->assertSessionHasErrors('estado');

    expect(permisosDe($rolDueno->id))->toContain($asignar);
});

it('no deja conceder un permiso que el propio rol activo no tiene', function () {
    // Un rol acotado: puede administrar permisos, pero no tiene finanzas.
    $acotado = SecRole::query()->create([
        'name' => 'admin_seguridad',
        'description' => 'Administra roles, sin acceso a finanzas.',
        'state' => true,
    ]);

    foreach (['seguridad.rol.ver', 'seguridad.rol.asignar_permiso'] as $codigo) {
        (new SecRolePermission(['id_role' => $acotado->id, 'id_permission' => idPermiso($codigo)]))->save();
    }

    [$usuario] = usuarioConRolParaRoles('admin.seg', 'admin_seguridad');
    entrarAlPanelParaRoles($usuario, $acotado->id);

    $piloto = SecRole::query()->where('name', 'piloto')->sole();
    $aprobarPlanilla = idPermiso('finanzas.planilla.aprobar');

    $this->put(route('panel.roles.permisos.update', $piloto), [
        'permisos' => [...permisosDe($piloto->id), $aprobarPlanilla],
    ])->assertSessionHasErrors('estado');

    expect(permisosDe($piloto->id))->not->toContain($aprobarPlanilla);
});

it('deja el permiso huérfano posible solo cuando otro rol vivo lo sostiene', function () {
    [$dueno, $idRolActivo] = duenoEnElPanel();
    $rolDueno = SecRole::query()->findOrFail($idRolActivo);
    $aprobar = idPermiso('finanzas.planilla.aprobar');

    // Se lo damos primero al encargado: ahora hay dos portadores vivos y
    // quitárselo al dueño deja de ser irreversible.
    $encargado = SecRole::query()->where('name', 'encargado_operaciones')->sole();
    (new SecRolePermission(['id_role' => $encargado->id, 'id_permission' => $aprobar]))->save();

    $sinEse = array_values(array_diff(permisosDe($rolDueno->id), [$aprobar]));

    $this->put(route('panel.roles.permisos.update', $rolDueno), ['permisos' => $sinEse])
        ->assertSessionHasNoErrors();

    expect(permisosDe($rolDueno->id))->not->toContain($aprobar)
        ->and(permisosDe($encargado->id))->toContain($aprobar);
});

it('un rol desactivado no cuenta como portador de sus permisos', function () {
    // Es la condición que se olvida: el pivote sigue vivo aunque el rol no
    // pueda usarse para entrar, así que contarlo haría creer que la llave
    // está a salvo cuando no la tiene nadie que pueda iniciar sesión.
    $aprobar = idPermiso('finanzas.planilla.aprobar');

    $suplente = SecRole::query()->create([
        'name' => 'suplente',
        'description' => 'Rol de respaldo, desactivado.',
        'state' => false,
    ]);
    (new SecRolePermission(['id_role' => $suplente->id, 'id_permission' => $aprobar]))->save();

    $idsVivos = app(CatalogoDePermisos::class)->idsRolVivoConPermiso('finanzas.planilla.aprobar');

    expect($idsVivos)->not->toContain($suplente->id);
});

// ------------------------------------------------------------ presentación

it('la matriz separa pantallas de acciones y no esconde las que no cuelgan de ninguna', function () {
    duenoEnElPanel();

    $arbol = app(CatalogoDePermisos::class)->arbolDeConcesiones();

    $pantallas = collect($arbol['modulos'])->flatMap(fn (array $m) => $m['pantallas']);
    $acciones = $pantallas->flatMap(fn (array $p) => $p['acciones']);

    // Todo el catálogo vivo queda alcanzable desde la pantalla: ninguno
    // escondido, ninguno contado dos veces.
    expect($pantallas->count() + $acciones->count() + count($arbol['sueltos']))
        ->toBe(SecPermission::query()->where('state', true)->count());

    // Las dos del acta se ejercen desde `agrocom-field`: no cuelgan de
    // ninguna pantalla del panel y van aparte, nunca ocultas.
    expect(collect($arbol['sueltos'])->pluck('codigo')->all())
        ->toBe(['operaciones.acta.firmar', 'operaciones.acta.generar']);
});

it('la matriz manda el bloqueado como hidden para que no se lea como revocado', function () {
    [$dueno, $idRolActivo] = duenoEnElPanel();
    $rolDueno = SecRole::query()->findOrFail($idRolActivo);

    // Un checkbox deshabilitado no se envía; sin el hidden, "bloqueado"
    // llegaría al servidor como "quitámelo" y el guardado entero fallaría.
    $this->get(route('panel.roles.permisos.edit', $rolDueno))
        ->assertOk()
        ->assertSee('type="hidden" name="permisos[]"', false)
        ->assertSee(__('seguridad.roles.permisos_aviso_rol_propio'));
});

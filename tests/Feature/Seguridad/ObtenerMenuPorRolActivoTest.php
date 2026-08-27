<?php

use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-02 — sec_menu / ObtenerMenuPorRolActivo: árbol de menú filtrado por el
 * ROL ACTIVO de la sesión (ADR 0004, extensión 27/8/2026; CLAUDE.md
 * invariante 10 — nunca la unión de todos los roles del usuario).
 *
 * La cobertura genérica de "delete() es lógico" y "forceDelete() está
 * bloqueado" para SecMenu vive en
 * tests/Feature/Modelos/BorradoLogicoSeguridadPersonalTest.php (dataset
 * compartido de todos los modelos de Personal/Seguridad, ADR 0007 /
 * invariante 8) — no se repite acá. Esta suite solo prueba el
 * comportamiento propio de ObtenerMenuPorRolActivo: que un ítem
 * soft-deleteado no aparezca en el árbol resuelto.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->caso = new ObtenerMenuPorRolActivo;
});

function menuAsignarRol(SecUser $usuario, int $idRol): void
{
    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();
}

/** @param  array<string, mixed>  $atributos */
function menuItem(array $atributos): SecMenu
{
    $item = new SecMenu($atributos);
    $item->save();

    return $item;
}

function menuIdPermiso(string $codigo): int
{
    return (int) SecPermission::query()->where('code', $codigo)->value('id');
}

function menuIdRol(string $nombre): int
{
    return (int) SecRole::query()->where('name', $nombre)->value('id');
}

it('arma el árbol con jerarquía: un padre visible incluye a sus hijos visibles, ordenados por orden', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = menuIdRol('dueno');
    menuAsignarRol($usuario, $idDueno);

    $padre = menuItem(['label' => 'menu.padre', 'icono' => 'folder', 'ruta' => null, 'orden' => 1]);
    $hijoB = menuItem(['label' => 'menu.hijo_b', 'icono' => 'b', 'ruta' => 'panel.b', 'padre_id' => $padre->id, 'orden' => 2]);
    $hijoA = menuItem(['label' => 'menu.hijo_a', 'icono' => 'a', 'ruta' => 'panel.a', 'padre_id' => $padre->id, 'orden' => 1]);

    $arbol = $this->caso->ejecutar($usuario, $idDueno);

    expect($arbol)->toHaveCount(1)
        ->and($arbol[0]->id)->toBe($padre->id)
        ->and($arbol[0]->label)->toBe('menu.padre')
        ->and($arbol[0]->hijos)->toHaveCount(2)
        ->and($arbol[0]->hijos[0]->id)->toBe($hijoA->id) // orden 1 antes que orden 2
        ->and($arbol[0]->hijos[1]->id)->toBe($hijoB->id);
});

it('un ítem sin permission_id es visible para cualquier rol activo', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = menuIdRol('piloto'); // sin ningún permiso de seguridad
    menuAsignarRol($usuario, $idPiloto);

    $item = menuItem(['label' => 'menu.inicio', 'icono' => 'home', 'ruta' => 'panel.dashboard', 'orden' => 1]);

    $arbol = $this->caso->ejecutar($usuario, $idPiloto);

    expect($arbol)->toHaveCount(1)->and($arbol[0]->id)->toBe($item->id);
});

it('oculta un ítem con permiso cuando el ROL ACTIVO no lo tiene, aunque el usuario tenga ese permiso en otro rol asignado', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = menuIdRol('piloto'); // sin permisos
    $idDueno = menuIdRol('dueno'); // seguridad.usuario.ver, entre otros
    menuAsignarRol($usuario, $idPiloto);
    menuAsignarRol($usuario, $idDueno);

    $item = menuItem([
        'label' => 'menu.usuarios',
        'icono' => 'group',
        'ruta' => 'panel.usuarios.index',
        'orden' => 1,
        'permission_id' => menuIdPermiso('seguridad.usuario.ver'),
    ]);

    $arbolComoDueno = $this->caso->ejecutar($usuario, $idDueno);
    $arbolComoPiloto = $this->caso->ejecutar($usuario, $idPiloto);

    expect($arbolComoDueno)->toHaveCount(1)
        ->and($arbolComoDueno[0]->id)->toBe($item->id)
        ->and($arbolComoPiloto)->toBe([]);
});

it('oculta por completo un padre sin permiso propio cuyos hijos están todos ocultos', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = menuIdRol('piloto'); // sin permisos
    menuAsignarRol($usuario, $idPiloto);

    $padre = menuItem(['label' => 'menu.padre', 'icono' => 'folder', 'ruta' => null, 'orden' => 1]);
    menuItem([
        'label' => 'menu.hijo_oculto',
        'icono' => 'child',
        'ruta' => 'panel.usuarios.index',
        'padre_id' => $padre->id,
        'orden' => 1,
        'permission_id' => menuIdPermiso('seguridad.usuario.ver'),
    ]);

    $arbol = $this->caso->ejecutar($usuario, $idPiloto);

    expect($arbol)->toBe([]);
});

it('muestra el padre si al menos un hijo es visible, aunque el padre no tenga permiso propio', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = menuIdRol('dueno');
    menuAsignarRol($usuario, $idDueno);

    $padre = menuItem(['label' => 'menu.padre', 'icono' => 'folder', 'ruta' => null, 'orden' => 1]);
    $hijoVisible = menuItem([
        'label' => 'menu.hijo_visible',
        'icono' => 'a',
        'ruta' => 'panel.usuarios.index',
        'padre_id' => $padre->id,
        'orden' => 1,
        'permission_id' => menuIdPermiso('seguridad.usuario.ver'),
    ]);

    $arbol = $this->caso->ejecutar($usuario, $idDueno);

    expect($arbol)->toHaveCount(1)
        ->and($arbol[0]->id)->toBe($padre->id)
        ->and($arbol[0]->hijos)->toHaveCount(1)
        ->and($arbol[0]->hijos[0]->id)->toBe($hijoVisible->id);
});

it('muestra un padre con permiso propio satisfecho aunque su único hijo quede oculto por permiso', function () {
    $usuario = SecUser::factory()->create();
    $idEncargado = menuIdRol('encargado_operaciones'); // todo salvo asignar_rol_dueno
    menuAsignarRol($usuario, $idEncargado);

    $padre = menuItem([
        'label' => 'menu.padre',
        'icono' => 'folder',
        'ruta' => 'panel.usuarios.index',
        'orden' => 1,
        'permission_id' => menuIdPermiso('seguridad.usuario.ver'),
    ]);
    menuItem([
        'label' => 'menu.hijo_oculto',
        'icono' => 'lock',
        'ruta' => 'panel.usuarios.dueno',
        'padre_id' => $padre->id,
        'orden' => 1,
        'permission_id' => menuIdPermiso('seguridad.usuario.asignar_rol_dueno'),
    ]);

    $arbol = $this->caso->ejecutar($usuario, $idEncargado);

    expect($arbol)->toHaveCount(1)
        ->and($arbol[0]->id)->toBe($padre->id)
        ->and($arbol[0]->hijos)->toBe([]);
});

it('un ítem soft-deleteado no aparece en el árbol, sin borrarse físicamente', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = menuIdRol('dueno');
    menuAsignarRol($usuario, $idDueno);

    $item = menuItem(['label' => 'menu.borrado', 'icono' => 'delete', 'ruta' => 'panel.x', 'orden' => 1]);
    $item->delete();

    $arbol = $this->caso->ejecutar($usuario, $idDueno);

    expect($arbol)->toBe([])
        ->and(SecMenu::query()->whereKey($item->id)->exists())->toBeFalse()
        ->and(SecMenu::withTrashed()->whereKey($item->id)->exists())->toBeTrue();
});

it('completa created_by/updated_by automáticamente desde el usuario autenticado (RegistraAutoria)', function () {
    $usuario = SecUser::factory()->create();
    $this->actingAs($usuario, 'interno');

    $item = new SecMenu(['label' => 'menu.auditado', 'icono' => 'edit', 'ruta' => null, 'orden' => 1]);
    $item->save();

    expect($item->created_by)->toBe($usuario->id)
        ->and($item->updated_by)->toBe($usuario->id);

    $item->orden = 2;
    $item->save();

    expect($item->fresh()->updated_by)->toBe($usuario->id);
});

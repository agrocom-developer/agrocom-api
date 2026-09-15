<?php

use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Tarea 77 (HU-54, pedido del dueño 7/9/2026): "Campos y lotes" se separa en
 * dos ítems de menú (Propiedades / Lotes) y "Personas" se renombra a
 * "Personal". La etapa 1 sembró "Lotes" como "botón sin link" (sin pantalla
 * todavía, mismo criterio que cualquier ítem del catálogo sin ruta, ver el
 * docblock de `SecMenuSeeder::item()`); la etapa 2 agrega la ficha propia de
 * lote y con ella la ruta `panel.lotes.index` — `item()` la activa sola en
 * cualquier instalación que ya tuviera el ítem sembrado, sin migración de
 * datos nueva.
 *
 * ADR 0020 (15/9/2026) va más allá: `Campo` se elimina como entidad, así que
 * el ítem legado "campos" ya no vuelve a llamarse "Campos" (como hacía ADR
 * 0018) — el seeder lo retira del árbol (ver la primera prueba de este
 * archivo).
 */

uses(RefreshDatabase::class);

function crearArbolMenuComercialLegado(): SecMenu
{
    $comercial = SecMenu::query()->firstOrCreate(
        ['label' => 'menu.comercial.label', 'padre_id' => null],
        ['descripcion' => 'menu.comercial.descripcion', 'icono' => 'handshake', 'ruta' => null, 'orden' => 2, 'permission_id' => null],
    );

    $campos = new SecMenu([
        'label' => 'menu.comercial.items.campos',
        'icono' => 'map',
        'ruta' => 'panel.campos.index',
        'padre_id' => $comercial->id,
        'orden' => 3,
        'permission_id' => null,
    ]);
    $campos->save();

    return $campos;
}

function rutaMigracionMenuPropiedadesLotes(): string
{
    return database_path('migrations/2026_09_08_200001_dividir_menu_propiedades_lotes_y_renombrar_personal.php');
}

it('el seeder retira el ítem legado de "campos" (ADR 0020) y "propiedades"/"lotes" quedan como ítems independientes', function () {
    // ADR 0020 (15/9/2026) elimina `Campo` como entidad — `Lote` cuelga
    // directo de `Propiedad`, sin nivel intermedio. La fila legada de
    // "campos" (la misma fila que, bajo la tarea 77 y luego ADR 0018, pasó
    // de "Campos y lotes" a "Propiedades" y de vuelta a "Campos") ya no
    // tiene pantalla ni permiso vigentes: el seeder la RETIRA
    // (`SecMenuSeeder::retirarItemCampos()`, soft delete) en vez de
    // revertirle el nombre. "Propiedades" sigue siendo el ítem nuevo con su
    // propia ruta (`panel.propiedades.index`); "Lotes" también.
    $this->seed(SeguridadSeeder::class);
    $vieja = crearArbolMenuComercialLegado();

    $this->seed(SecMenuSeeder::class);

    $campos = $vieja->fresh();
    expect($campos)->not->toBeNull()
        ->and($campos->label)->toBe('menu.comercial.items.campos')
        ->and($campos->ruta)->toBe('panel.campos.index')
        ->and($campos->trashed())->toBeTrue();

    $propiedades = SecMenu::query()->where('label', 'menu.comercial.items.propiedades')->sole();
    $idPermisoPropiedad = (int) SecPermission::query()->where('code', 'comercial.propiedad.ver')->value('id');

    expect($propiedades->padre_id)->toBe($campos->padre_id)
        ->and($propiedades->ruta)->toBe('panel.propiedades.index')
        ->and($propiedades->permission_id)->toBe($idPermisoPropiedad);

    $lotes = SecMenu::query()->where('label', 'menu.comercial.items.lotes')->sole();
    $idPermisoLote = (int) SecPermission::query()->where('code', 'comercial.lote.ver')->value('id');

    expect($lotes->padre_id)->toBe($campos->padre_id)
        ->and($lotes->ruta)->toBe('panel.lotes.index')
        ->and($lotes->permission_id)->toBe($idPermisoLote);
});

it('el seeder renombra "personas" a "personal" conservando ruta y permiso', function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);

    expect(SecMenu::query()->where('label', 'menu.recursos.items.personas')->exists())->toBeFalse();

    $personal = SecMenu::query()->where('label', 'menu.recursos.items.personal')->sole();
    $idPermiso = (int) SecPermission::query()->where('code', 'personal.persona.ver')->value('id');

    expect($personal->ruta)->toBe('panel.personas.index')
        ->and($personal->permission_id)->toBe($idPermiso);
});

it('la migración transforma el árbol de una instalación ya sembrada, y su down lo revierte', function () {
    $this->seed(SeguridadSeeder::class);
    $vieja = crearArbolMenuComercialLegado();

    $migracion = require rutaMigracionMenuPropiedadesLotes();
    $migracion->up();

    $propiedades = $vieja->fresh();
    expect($propiedades->label)->toBe('menu.comercial.items.propiedades');

    $lotes = SecMenu::query()->where('label', 'menu.comercial.items.lotes')->sole();
    expect($lotes->padre_id)->toBe($propiedades->padre_id);

    $migracion->down();

    expect($vieja->fresh()->label)->toBe('menu.comercial.items.campos')
        ->and(SecMenu::query()->where('label', 'menu.comercial.items.lotes')->exists())->toBeFalse();
});

it('la migración no hace nada en una base sin sec_menu sembrado (instalación nueva, la siembra el seeder)', function () {
    $migracion = require rutaMigracionMenuPropiedadesLotes();

    $migracion->up();

    expect(SecMenu::query()->count())->toBe(0);
});

it('correr la migración y volver a sembrar dos veces no duplica el árbol ni restaura un permiso quitado a mano', function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);

    $encargado = SecRole::query()->where('name', 'encargado_operaciones')->firstOrFail();
    $idPermisoLote = (int) SecPermission::query()->where('code', 'comercial.lote.ver')->value('id');

    // Simula al dueño quitando el permiso desde el panel: soft delete real
    // de la fila (mismo efecto que AsignarPermisosRol::quitar()).
    SecRolePermission::query()
        ->where('id_role', $encargado->id)
        ->where('id_permission', $idPermisoLote)
        ->firstOrFail()
        ->delete();

    // "migrate --seed" de nuevo, sobre una base que ya tiene el árbol nuevo.
    $migracion = require rutaMigracionMenuPropiedadesLotes();
    $migracion->up();
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);

    expect(SecMenu::query()->where('label', 'menu.comercial.items.propiedades')->count())->toBe(1)
        ->and(SecMenu::query()->where('label', 'menu.comercial.items.lotes')->count())->toBe(1);

    expect(SecRolePermission::query()
        ->where('id_role', $encargado->id)
        ->where('id_permission', $idPermisoLote)
        ->exists())->toBeFalse();

    expect(SecRolePermission::withTrashed()
        ->where('id_role', $encargado->id)
        ->where('id_permission', $idPermisoLote)
        ->whereNotNull('deleted_at')
        ->exists())->toBeTrue();
});

it('el item "Lotes" del sidebar se gobierna por comercial.lote.ver, independiente de comercial.propiedad.ver', function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);

    $rol = SecRole::query()->create(['name' => 'rol_prueba_lotes_77', 'description' => 'Rol de prueba', 'state' => true]);
    $idPermisoPropiedad = (int) SecPermission::query()->where('code', 'comercial.propiedad.ver')->value('id');
    $idPermisoLote = (int) SecPermission::query()->where('code', 'comercial.lote.ver')->value('id');

    (new SecRolePermission(['id_role' => $rol->id, 'id_permission' => $idPermisoPropiedad]))->save();

    $usuario = SecUser::factory()->create();
    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $rol->id]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    $obtenerMenu = app(ObtenerMenuPorRolActivo::class);

    $comercial = collect($obtenerMenu->ejecutar($usuario, $rol->id))->firstWhere('label', 'menu.comercial.label');
    $etiquetas = collect($comercial->hijos)->map(fn ($item) => $item->label)->all();

    // ADR 0020: "campos" ya no existe en el árbol (el seeder lo retira, ver
    // la primera prueba del archivo). Un rol con solo `comercial.propiedad.ver`
    // ve "propiedades" pero no "lotes" — el gating de ítems hermanos sigue
    // siendo independiente.
    expect($etiquetas)->toContain('menu.comercial.items.propiedades')
        ->not->toContain('menu.comercial.items.lotes');

    (new SecRolePermission(['id_role' => $rol->id, 'id_permission' => $idPermisoLote]))->save();

    $comercialConLote = collect($obtenerMenu->ejecutar($usuario, $rol->id))->firstWhere('label', 'menu.comercial.label');
    $etiquetasConLote = collect($comercialConLote->hijos)->map(fn ($item) => $item->label)->all();

    expect($etiquetasConLote)->toContain('menu.comercial.items.lotes');
});

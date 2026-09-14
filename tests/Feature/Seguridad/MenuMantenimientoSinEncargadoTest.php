<?php

use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-88 (tarea 103, docs/negocio/observaciones_mantenimiento_2026-09-13.md):
 * "Plan de Mantenimiento", "Repuestos" y "Stock Base" salen del menú del
 * encargado de operaciones — las usa poco y le ensucian la navegación del
 * día a día. Las pantallas siguen existiendo para quien sí tenga el permiso
 * (el dueño, que recibe el catálogo completo sin excepción).
 *
 * El cambio real vive en `SeguridadSeeder::PERMISOS_ENCARGADO_OPERACIONES`
 * (se le sacó el catálogo entero de `mantenimiento.plan.*`,
 * `inventario.repuesto.*` e `inventario.movimiento.*`, no solo `.ver`): el
 * menú (`ObtenerMenuPorRolActivo`) ya filtraba por permiso del rol activo
 * desde antes, así que esta suite prueba el efecto, no un cambio de
 * `SecMenuSeeder`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

function usuarioConRolParaMenuMantenimiento(string $username, string $rol): array
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
function entrarAlPanelParaMenuMantenimiento(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/**
 * Aplana el árbol de menú (padres + hijos) en la lista de labels visibles,
 * para no depender de en qué grupo esté cada ítem.
 *
 * @param  list<object>  $arbol
 * @return list<string>
 */
function etiquetasVisibles(array $arbol): array
{
    $etiquetas = [];

    foreach ($arbol as $item) {
        $etiquetas[] = $item->label;
        $etiquetas = [...$etiquetas, ...etiquetasVisibles($item->hijos)];
    }

    return $etiquetas;
}

it('el encargado de operaciones ya no ve Plan de Mantenimiento, Repuestos ni Stock Base en su menú', function () {
    [$encargado, $idRol] = usuarioConRolParaMenuMantenimiento('encargado.menu', 'encargado_operaciones');

    $arbol = app(ObtenerMenuPorRolActivo::class)->ejecutar($encargado, $idRol);
    $etiquetas = etiquetasVisibles($arbol);

    expect($etiquetas)
        ->not->toContain('menu.mantenimiento.items.planes')
        ->not->toContain('menu.mantenimiento.items.repuestos')
        ->not->toContain('menu.mantenimiento.items.stock')
        // La orden de mantenimiento (abrir/cerrar) no es una de las tres
        // pantallas que la HU pide esconder: sigue en su menú.
        ->toContain('menu.mantenimiento.items.ordenes');
});

it('el dueño sigue viendo Plan de Mantenimiento, Repuestos y Stock Base en su menú', function () {
    [$dueno, $idRol] = usuarioConRolParaMenuMantenimiento('dueno.menu', 'dueno');

    $arbol = app(ObtenerMenuPorRolActivo::class)->ejecutar($dueno, $idRol);
    $etiquetas = etiquetasVisibles($arbol);

    expect($etiquetas)
        ->toContain('menu.mantenimiento.items.planes')
        ->toContain('menu.mantenimiento.items.repuestos')
        ->toContain('menu.mantenimiento.items.stock');
});

it('el dueño sigue accediendo por URL directa a planes de mantenimiento, repuestos y stock', function () {
    [$dueno, $idRol] = usuarioConRolParaMenuMantenimiento('dueno.url', 'dueno');
    entrarAlPanelParaMenuMantenimiento($dueno, $idRol);

    $this->get(route('panel.planes-mantenimiento.index'))->assertOk();
    $this->get(route('panel.repuestos.index'))->assertOk();
    $this->get(route('panel.stock.index'))->assertOk();
});

it('el encargado de operaciones recibe 403 por URL directa en planes de mantenimiento, repuestos y stock', function () {
    [$encargado, $idRol] = usuarioConRolParaMenuMantenimiento('encargado.url', 'encargado_operaciones');
    entrarAlPanelParaMenuMantenimiento($encargado, $idRol);

    $this->get(route('panel.planes-mantenimiento.index'))->assertForbidden();
    $this->get(route('panel.repuestos.index'))->assertForbidden();
    $this->get(route('panel.stock.index'))->assertForbidden();
});

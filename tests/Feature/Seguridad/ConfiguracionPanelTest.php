<?php

use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Tarea 78 (HU-55) — `/panel/configuracion`: llaves y tokens de
 * infraestructura, exclusivo del dueño. El corazón de la tarea es que un
 * secreto guardado NUNCA vuelva en el HTML de esta pantalla, ni completo ni
 * cifrado — ninguna de las aserciones de acá se conforma con un 200 OK.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

function configuracionAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

it('el dueño ve la pantalla con los tres sectores y permiso de edición', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = configuracionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $respuesta = $this->get(route('panel.configuracion.index'))
        ->assertOk()
        ->assertViewIs('seguridad::pages.configuracion.index')
        ->assertViewHas('puedeEditar', true);

    $grupos = collect($respuesta->viewData('grupos'))->pluck('clave')->all();
    expect($grupos)->toBe(['mapas', 'correo', 'integraciones']);

    $filasPorGrupo = $respuesta->viewData('filasPorGrupo');
    expect($filasPorGrupo['integraciones'])->toBe([])
        ->and(collect($filasPorGrupo['mapas'])->pluck('clave')->all())->toContain('mapas.google_maps_api_key');
});

it('un rol que no es dueño recibe 403 en /panel/configuracion y no ve el ítem de menú', function () {
    $usuario = SecUser::factory()->create();
    $idEncargado = configuracionAsignarRol($usuario, 'encargado_operaciones');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idEncargado]);

    $this->get(route('panel.configuracion.index'))->assertStatus(403);

    $arbol = (new ObtenerMenuPorRolActivo)->ejecutar($usuario, $idEncargado);
    $seguridad = collect($arbol)->first(fn ($item) => $item->label === 'menu.seguridad.label');
    $etiquetasHijos = collect($seguridad->hijos)->pluck('label')->all();

    expect($etiquetasHijos)->not->toContain('menu.seguridad.items.configuracion');
});

it('el dueño ve el ítem de menú Configuración del sistema', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = configuracionAsignarRol($usuario, 'dueno');

    $arbol = (new ObtenerMenuPorRolActivo)->ejecutar($usuario, $idDueno);
    $seguridad = collect($arbol)->first(fn ($item) => $item->label === 'menu.seguridad.label');
    $etiquetasHijos = collect($seguridad->hijos)->pluck('label')->all();

    expect($etiquetasHijos)->toContain('menu.seguridad.items.configuracion');
});

it('guarda un valor no secreto y lo refleja en la siguiente carga de la pantalla', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = configuracionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->post(route('panel.configuracion.actualizar', ['grupo' => 'mapas']), [
        'valores' => [
            'mapas.proveedor_preferido' => 'leaflet_esri',
            'mapas.google_maps_api_key' => '',
        ],
        'borrar' => [],
    ])->assertRedirect(route('panel.configuracion.index', ['grupo' => 'mapas']));

    $respuesta = $this->get(route('panel.configuracion.index'));
    $mapas = collect($respuesta->viewData('filasPorGrupo')['mapas'])->keyBy('clave');

    expect($mapas['mapas.proveedor_preferido']['valorVisible'])->toBe('leaflet_esri');
});

it('guardar un secreto no lo expone en el HTML de la pantalla, ni completo ni cifrado', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = configuracionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $llave = 'AIzaSyD-una-llave-bien-secreta-000111222';

    $this->post(route('panel.configuracion.actualizar', ['grupo' => 'mapas']), [
        'valores' => ['mapas.google_maps_api_key' => $llave],
        'borrar' => [],
    ]);

    $html = $this->get(route('panel.configuracion.index'))->getContent();

    expect($html)->not->toContain($llave);

    $crudo = DB::table('plt_configuraciones')->where('clave', 'mapas.google_maps_api_key')->value('valor');
    expect($html)->not->toContain($crudo);
});

it('la pantalla muestra "Configurada" y los últimos 4 caracteres de un secreto guardado, nunca más', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = configuracionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->post(route('panel.configuracion.actualizar', ['grupo' => 'mapas']), [
        'valores' => ['mapas.google_maps_api_key' => 'AIzaSyD-una-llave-bien-secreta-wXyZ'],
        'borrar' => [],
    ]);

    $respuesta = $this->get(route('panel.configuracion.index'));
    $mapas = collect($respuesta->viewData('filasPorGrupo')['mapas'])->keyBy('clave');

    expect($mapas['mapas.google_maps_api_key']['configurada'])->toBeTrue()
        ->and($mapas['mapas.google_maps_api_key']['ultimos4'])->toBe('wXyZ')
        ->and($respuesta->getContent())->toContain('wXyZ');
});

it('guardar vacío en un secreto ya configurado no lo borra', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = configuracionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->post(route('panel.configuracion.actualizar', ['grupo' => 'mapas']), [
        'valores' => ['mapas.google_maps_api_key' => 'valor-original'],
        'borrar' => [],
    ]);

    $this->post(route('panel.configuracion.actualizar', ['grupo' => 'mapas']), [
        'valores' => ['mapas.google_maps_api_key' => ''],
        'borrar' => [],
    ]);

    $configuracion = Configuracion::query()->where('clave', 'mapas.google_maps_api_key')->first();
    expect($configuracion->valor)->toBe('valor-original');
});

it('el borrado explícito de un secreto sí lo borra', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = configuracionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->post(route('panel.configuracion.actualizar', ['grupo' => 'mapas']), [
        'valores' => ['mapas.google_maps_api_key' => 'valor-original'],
        'borrar' => [],
    ]);

    $this->post(route('panel.configuracion.actualizar', ['grupo' => 'mapas']), [
        'valores' => ['mapas.google_maps_api_key' => ''],
        'borrar' => ['mapas.google_maps_api_key' => '1'],
    ]);

    $configuracion = Configuracion::query()->where('clave', 'mapas.google_maps_api_key')->first();
    expect($configuracion->valor)->toBeNull();
});

it('ver sin poder editar responde 403 al intentar guardar', function () {
    $usuario = SecUser::factory()->create();

    $rol = SecRole::query()->create(['name' => 'auditor_configuracion', 'description' => 'Rol de prueba', 'state' => true]);
    $idPermisoVer = (int) SecPermission::query()->where('code', 'seguridad.configuracion.ver')->value('id');
    (new SecRolePermission(['id_role' => $rol->id, 'id_permission' => $idPermisoVer]))->save();
    configuracionAsignarRol($usuario, 'auditor_configuracion');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $rol->id]);

    $this->get(route('panel.configuracion.index'))->assertOk();

    $this->post(route('panel.configuracion.actualizar', ['grupo' => 'mapas']), [
        'valores' => ['mapas.google_maps_api_key' => 'algo'],
        'borrar' => [],
    ])->assertStatus(403);
});

it('un grupo fuera del catálogo no resuelve ninguna ruta (404)', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = configuracionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->post('/panel/configuracion/otro-grupo', ['valores' => [], 'borrar' => []])
        ->assertStatus(404);
});

it('exige autenticación', function () {
    $this->getJson(route('panel.configuracion.index'))->assertStatus(401);
});

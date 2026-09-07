<?php

use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * TE-13 (tarea 59): CR-01 retiró Mezclas del plan (Agrocom no prepara la
 * mezcla ni dosifica) — el ítem de menú `operacion.mezclas`, sembrado como
 * placeholder antes de que CR-01 se cerrara, no debe quedar en el árbol.
 *
 * Tarea 62 (fuga 3): dos placeholders más, `operacion.evidencias` y
 * `comercial.reportes_cliente`, se sembraban sin `ruta` NI `permission_id` —
 * por la regla de grupos de `ObtenerMenuPorRolActivo` (un grupo es visible si
 * algún hijo lo es, o si él mismo tiene un permiso propio satisfecho; un
 * hijo sin permiso SIEMPRE cumple la regla de hoja), eso hacía visibles a
 * "Operación"/"Comercial" completos para cualquier rol, con un ítem que no
 * lleva a ningún lado. Se retiran igual que `mezclas`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
});

it('no siembra el ítem de menú de mezclas en una base limpia', function () {
    $this->seed(SecMenuSeeder::class);

    expect(SecMenu::query()->where('label', 'menu.operacion.items.mezclas')->exists())->toBeFalse();
});

it('retira el ítem de menú de mezclas si ya estaba sembrado de una vuelta anterior', function () {
    $operacion = SecMenu::query()->where('label', 'menu.operacion.label')->first();

    if ($operacion === null) {
        $operacion = new SecMenu([
            'label' => 'menu.operacion.label',
            'descripcion' => 'menu.operacion.descripcion',
            'icono' => 'flight_takeoff',
            'ruta' => null,
            'padre_id' => null,
            'orden' => 1,
            'permission_id' => null,
        ]);
        $operacion->save();
    }

    $vieja = new SecMenu([
        'label' => 'menu.operacion.items.mezclas',
        'icono' => 'science',
        'ruta' => null,
        'padre_id' => $operacion->id,
        'orden' => 6,
        'permission_id' => null,
    ]);
    $vieja->save();

    $this->seed(SecMenuSeeder::class);

    expect(SecMenu::query()->where('label', 'menu.operacion.items.mezclas')->exists())->toBeFalse()
        ->and($vieja->fresh()?->trashed())->toBeTrue();
});

it('no siembra los ítems de evidencias ni reportes_cliente en una base limpia', function () {
    $this->seed(SecMenuSeeder::class);

    expect(SecMenu::query()->where('label', 'menu.operacion.items.evidencias')->exists())->toBeFalse()
        ->and(SecMenu::query()->where('label', 'menu.comercial.items.reportes_cliente')->exists())->toBeFalse();
});

it('retira evidencias y reportes_cliente si ya estaban sembrados de una vuelta anterior', function () {
    $this->seed(SecMenuSeeder::class);

    $operacion = SecMenu::query()->where('label', 'menu.operacion.label')->firstOrFail();
    $comercial = SecMenu::query()->where('label', 'menu.comercial.label')->firstOrFail();

    $viejaEvidencias = new SecMenu([
        'label' => 'menu.operacion.items.evidencias',
        'icono' => 'photo_library',
        'ruta' => null,
        'padre_id' => $operacion->id,
        'orden' => 7,
        'permission_id' => null,
    ]);
    $viejaEvidencias->save();

    $viejoReportesCliente = new SecMenu([
        'label' => 'menu.comercial.items.reportes_cliente',
        'icono' => 'picture_as_pdf',
        'ruta' => null,
        'padre_id' => $comercial->id,
        'orden' => 4,
        'permission_id' => null,
    ]);
    $viejoReportesCliente->save();

    $this->seed(SecMenuSeeder::class);

    expect($viejaEvidencias->fresh()?->trashed())->toBeTrue()
        ->and($viejoReportesCliente->fresh()?->trashed())->toBeTrue();
});

it('ningún ítem de sec_menu con ruta queda sin permission_id', function () {
    $this->seed(SecMenuSeeder::class);

    $huerfanos = SecMenu::query()->whereNotNull('ruta')->whereNull('permission_id')->pluck('label');

    expect($huerfanos)->toBeEmpty();
});

it('el menú de piloto no contiene los módulos Comercial ni Seguridad, y de Operación solo su tablero', function () {
    $this->seed(SecMenuSeeder::class);

    $usuario = SecUser::factory()->create();
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    $arbol = app(ObtenerMenuPorRolActivo::class)->ejecutar($usuario, $idPiloto);
    $etiquetasModulo = collect($arbol)->map(fn ($item) => $item->label)->all();

    expect($etiquetasModulo)->not->toContain('menu.comercial.label')
        ->not->toContain('menu.seguridad.label');

    // Desde la tarea 67 el piloto tiene `seguridad.dashboard.ver`, así que
    // Operación aparece — con UN solo ítem, su tablero. Ni órdenes, ni
    // trabajos, ni la cola de validación: esos siguen exigiendo permisos que
    // no tiene.
    $operacion = collect($arbol)->firstWhere('label', 'menu.operacion.label');

    expect($operacion)->not->toBeNull();
    expect(collect($operacion->hijos)->map(fn ($item) => $item->label)->all())
        ->toBe(['menu.operacion.items.tablero']);
});

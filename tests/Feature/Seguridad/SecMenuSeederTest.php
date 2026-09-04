<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * TE-13 (tarea 59): CR-01 retiró Mezclas del plan (Agrocom no prepara la
 * mezcla ni dosifica) — el ítem de menú `operacion.mezclas`, sembrado como
 * placeholder antes de que CR-01 se cerrara, no debe quedar en el árbol.
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

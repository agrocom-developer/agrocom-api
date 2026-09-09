<?php

use App\Dominios\Compartido\Dominio\Excepciones\BorradoFisicoNoPermitido;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosFiscales;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * ADR 0007 / invariante 8 — mismo criterio que
 * tests/Feature/Modelos/BorradoLogicoTest.php (núcleo comercial), acá para
 * los modelos nuevos de HU-01: Personal y Seguridad. `SecDatosFiscales`
 * (tarea 78, HU-55) se suma acá: ya queda protegido por la regla
 * arquitectónica genérica de extender `ModeloDominio`, pero ningún test
 * ejercitaba `delete()`/`forceDelete()` sobre él hasta ahora.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);

    $base = PerBase::query()->create(['nombre' => 'Base Demo', 'ubicacion' => null]);

    PerPersona::query()->create([
        'nombre' => 'Persona Demo',
        'rol' => RolOperativoPersona::Piloto,
        'base_id' => $base->id,
        'activo' => true,
    ]);

    $usuario = SecUser::factory()->create();
    $idRol = SecRole::query()->value('id');

    (new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]))->save();

    SecMenu::query()->create([
        'label' => 'seguridad.menu.demo',
        'icono' => 'home',
        'ruta' => null,
        'orden' => 1,
    ]);

    SecDatosFiscales::query()->create([
        'razon_social_fiscal' => 'Agrocom S.R.L.',
        'nit' => '123456789',
        'domicilio_fiscal' => 'Av. Demo 123',
        'actividad_economica' => 'Fumigación aérea',
    ]);
});

dataset('modelos de dominio de Personal y Seguridad', [
    'PerBase' => [PerBase::class],
    'PerPersona' => [PerPersona::class],
    'SecUser' => [SecUser::class],
    'SecRole' => [SecRole::class],
    'SecPermission' => [SecPermission::class],
    'SecUserRole' => [SecUserRole::class],
    'SecRolePermission' => [SecRolePermission::class],
    'SecMenu' => [SecMenu::class],
    'SecDatosFiscales' => [SecDatosFiscales::class],
]);

it('delete() hace borrado lógico y saca el registro de los listados por defecto', function (string $clase) {
    $modelo = $clase::query()->firstOrFail();

    $modelo->delete();

    expect($modelo->deleted_at)->not->toBeNull()
        ->and($clase::query()->whereKey($modelo->getKey())->exists())->toBeFalse()
        ->and($clase::withTrashed()->whereKey($modelo->getKey())->exists())->toBeTrue();
})->with('modelos de dominio de Personal y Seguridad');

it('bloquea el borrado físico: forceDelete() lanza excepción y el registro sobrevive', function (string $clase) {
    $modelo = $clase::query()->firstOrFail();

    expect(fn () => $modelo->forceDelete())->toThrow(BorradoFisicoNoPermitido::class)
        ->and($clase::withTrashed()->whereKey($modelo->getKey())->exists())->toBeTrue();
})->with('modelos de dominio de Personal y Seguridad');

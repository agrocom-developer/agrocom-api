<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-20 — el permiso `distribucion.version.autorizar` (ADR 0004 lo nombra en
 * `sec_action`, nunca materializada; la fuente de verdad real es
 * `sec_permission`, ver runs/10-diseno.md) queda seedeado y asignado
 * exclusivamente al rol dueño.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
});

it('siembra el permiso distribucion.version.autorizar', function () {
    expect(SecPermission::query()->where('code', 'distribucion.version.autorizar')->exists())->toBeTrue();
});

it('lo asigna al rol dueño y a ningún otro', function () {
    $idPermiso = (int) SecPermission::query()->where('code', 'distribucion.version.autorizar')->value('id');

    $rolesConElPermiso = SecRolePermission::query()
        ->where('id_permission', $idPermiso)
        ->pluck('id_role')
        ->map(fn (int|string $id): string => (string) SecRole::query()->find($id)?->name)
        ->all();

    expect($rolesConElPermiso)->toBe(['dueno']);
});

<?php

use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-20 — pantalla del panel (listar, registrar, autorizar), gateada por el
 * único permiso `distribucion.version.autorizar`. Sin ese permiso, cualquier
 * acción responde 403 (CA obligatorio del criterio de aceptación). El
 * binario vive en el release de `agrocom-field`; acá solo se registra su URL.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolDistribucion(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelDistribucion(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

it('el dueño ve el listado de versiones', function () {
    VersionApk::factory()->create(['version' => '1.0.0', 'version_code' => 100]);
    [$dueno, $idRol] = usuarioConRolDistribucion('duenio', 'dueno');

    entrarAlPanelDistribucion($dueno, $idRol);

    $this->get('/panel/versiones-apk')
        ->assertOk()
        ->assertSee('1.0.0');
});

it('el dueño registra una versión nueva con la url del release: queda pendiente', function () {
    [$dueno, $idRol] = usuarioConRolDistribucion('duenio', 'dueno');
    entrarAlPanelDistribucion($dueno, $idRol);

    $url = 'https://github.com/agrocom-developer/agrocom-field/releases/download/v1.5.0/agrocom-field.apk';

    $this->post('/panel/versiones-apk', [
        'version' => '1.5.0',
        'version_code' => 15000,
        'url_apk' => $url,
    ])->assertRedirect(route('panel.versiones-apk.index'));

    $version = VersionApk::query()->where('version', '1.5.0')->sole();

    expect($version->estado)->toBe(EstadoVersionApk::Pendiente)
        ->and($version->version_code)->toBe(15000)
        ->and($version->url_apk)->toBe($url);
});

it('el dueño autoriza una versión pendiente y desautoriza la vigente anterior', function () {
    $anterior = VersionApk::factory()->create([
        'version' => '1.0.0',
        'version_code' => 100,
        'estado' => EstadoVersionApk::Autorizada,
    ]);
    $nueva = VersionApk::factory()->create(['version' => '1.1.0', 'version_code' => 110]);

    [$dueno, $idRol] = usuarioConRolDistribucion('duenio', 'dueno');
    entrarAlPanelDistribucion($dueno, $idRol);

    $this->post("/panel/versiones-apk/{$nueva->id}/autorizar")
        ->assertRedirect(route('panel.versiones-apk.index'));

    expect($nueva->fresh()?->estado)->toBe(EstadoVersionApk::Autorizada)
        ->and($anterior->fresh()?->estado)->toBe(EstadoVersionApk::Pendiente);
});

it('sin el permiso distribucion.version.autorizar, listar/subir/autorizar responden 403', function () {
    $version = VersionApk::factory()->create();
    [$piloto, $idRol] = usuarioConRolDistribucion('piloto.curioso', 'piloto');

    entrarAlPanelDistribucion($piloto, $idRol);

    $this->get('/panel/versiones-apk')->assertForbidden();

    $this->post('/panel/versiones-apk', [
        'version' => '9.9.9',
        'version_code' => 99999,
        'url_apk' => 'https://github.com/agrocom-developer/agrocom-field/releases/download/v9.9.9/agrocom-field.apk',
    ])->assertForbidden();

    $this->post("/panel/versiones-apk/{$version->id}/autorizar")->assertForbidden();

    expect($version->fresh()?->estado)->toBe(EstadoVersionApk::Pendiente)
        ->and(VersionApk::query()->where('version', '9.9.9')->exists())->toBeFalse();
});

it('exige sesión de panel para llegar a la pantalla', function () {
    $this->get('/panel/versiones-apk')->assertRedirect();
});

it('publica el ítem de menú de versiones del APK gateado por el permiso de autorizar', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.seguridad.items.versiones_apk')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'distribucion.version.autorizar')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.versiones-apk.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});

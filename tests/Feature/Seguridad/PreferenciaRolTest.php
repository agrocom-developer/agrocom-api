<?php

use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Aplicacion\IniciarSesionPanel;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Quinta vuelta — preferencias de rol de la pantalla de selección (maqueta
 * 5c): "Entrar siempre con este rol" (`rol_preferido_id`, saltea el
 * selector) y "ÚLTIMO USADO" (`ultimo_rol_id`, registrado por
 * ElegirRolActivo). Ninguna de las dos gobierna permisos: el rol activo
 * sigue siendo estado de sesión (ADR 0004) y la preferencia se ignora si el
 * rol dejó de estar vivo — nunca un fallback silencioso.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

function prefAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

function prefFijarPreferido(SecUser $usuario, int $idRol): void
{
    SecUserPreferencia::query()->create([
        'user_id' => $usuario->id,
        'rol_preferido_id' => $idRol,
    ]);
}

// --- Último rol usado --------------------------------------------------------

it('ElegirRolActivo registra el último rol usado en la preferencia del usuario', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = prefAsignarRol($usuario, 'piloto');

    $this->actingAs($usuario, 'interno');
    app(ElegirRolActivo::class)->ejecutar($usuario, $idPiloto);

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('ultimo_rol_id'))
        ->toBe($idPiloto);
});

it('el selector marca el último rol usado y lo preselecciona', function () {
    $usuario = SecUser::factory()->create();
    prefAsignarRol($usuario, 'piloto');
    $idDueno = prefAsignarRol($usuario, 'dueno');

    SecUserPreferencia::query()->create(['user_id' => $usuario->id, 'ultimo_rol_id' => $idDueno]);

    $this->actingAs($usuario, 'interno');

    $this->get(route('panel.rol-activo.selector'))
        ->assertOk()
        ->assertViewHas('ultimoRolId', $idDueno)
        ->assertViewHas('preseleccionId', $idDueno);
});

// --- "Entrar siempre con este rol" ------------------------------------------

it('el login con 2+ roles y rol preferido vivo lo activa solo, sin pedir selección', function () {
    $usuario = SecUser::factory()->create();
    prefAsignarRol($usuario, 'piloto');
    $idDueno = prefAsignarRol($usuario, 'dueno');
    prefFijarPreferido($usuario, $idDueno);

    $resultado = app(IniciarSesionPanel::class)->ejecutar($usuario);

    expect($resultado->requiereSeleccion)->toBeFalse()
        ->and($resultado->rolActivo?->id)->toBe($idDueno)
        ->and(session('sec_rol_activo_id'))->toBe($idDueno);
});

it('el login ignora un rol preferido que dejó de estar vivo y vuelve a pedir selección', function () {
    $usuario = SecUser::factory()->create();
    prefAsignarRol($usuario, 'piloto');
    prefAsignarRol($usuario, 'auxiliar');
    $idDueno = prefAsignarRol($usuario, 'dueno');
    prefFijarPreferido($usuario, $idDueno);

    // Se revoca la asignación del preferido (soft delete del pivote).
    SecUserRole::query()
        ->where('id_user', $usuario->id)
        ->where('id_role', $idDueno)
        ->first()
        ->delete();

    $resultado = app(IniciarSesionPanel::class)->ejecutar($usuario);

    expect($resultado->requiereSeleccion)->toBeTrue()
        ->and(session('sec_rol_activo_id'))->toBeNull();
});

it('el selector con rol preferido vivo se saltea y redirige al dashboard', function () {
    $usuario = SecUser::factory()->create();
    prefAsignarRol($usuario, 'piloto');
    $idDueno = prefAsignarRol($usuario, 'dueno');
    prefFijarPreferido($usuario, $idDueno);

    $this->actingAs($usuario, 'interno');

    $this->get(route('panel.rol-activo.selector'))
        ->assertRedirect(route('panel.dashboard'));

    expect(session('sec_rol_activo_id'))->toBe($idDueno);
});

it('el selector con rol preferido SÍ se muestra cuando el cambio de rol es explícito (?cambiar=1)', function () {
    $usuario = SecUser::factory()->create();
    prefAsignarRol($usuario, 'piloto');
    $idDueno = prefAsignarRol($usuario, 'dueno');
    prefFijarPreferido($usuario, $idDueno);

    $this->actingAs($usuario, 'interno');

    $this->get(route('panel.rol-activo.selector', ['cambiar' => 1]))
        ->assertOk()
        ->assertViewIs('seguridad::pages.seleccionar-rol')
        ->assertViewHas('recordarInicial', true)
        ->assertViewHas('preseleccionId', $idDueno);
});

it('el middleware rol.activo auto-fija el rol preferido con 2+ roles vivos', function () {
    $usuario = SecUser::factory()->create();
    prefAsignarRol($usuario, 'piloto');
    $idDueno = prefAsignarRol($usuario, 'dueno');
    prefFijarPreferido($usuario, $idDueno);

    $this->actingAs($usuario, 'interno');

    $this->get(route('panel.dashboard'))->assertOk();
    expect(session('sec_rol_activo_id'))->toBe($idDueno);
});

// --- POST /panel/rol-activo con `recordar` ----------------------------------

it('el POST de rol activo con recordar=true fija la preferencia al rol elegido', function () {
    $usuario = SecUser::factory()->create();
    prefAsignarRol($usuario, 'piloto');
    $idDueno = prefAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno');

    $this->postJson(route('panel.rol-activo.actualizar'), ['id_role' => $idDueno, 'recordar' => true])
        ->assertOk();

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('rol_preferido_id'))
        ->toBe($idDueno);
});

it('el POST de rol activo con recordar=false limpia la preferencia', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = prefAsignarRol($usuario, 'piloto');
    $idDueno = prefAsignarRol($usuario, 'dueno');
    prefFijarPreferido($usuario, $idDueno);

    $this->actingAs($usuario, 'interno');

    $this->postJson(route('panel.rol-activo.actualizar'), ['id_role' => $idPiloto, 'recordar' => false])
        ->assertOk();

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('rol_preferido_id'))
        ->toBeNull();
});

it('el POST de rol activo sin el campo recordar no toca la preferencia', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = prefAsignarRol($usuario, 'piloto');
    $idDueno = prefAsignarRol($usuario, 'dueno');
    prefFijarPreferido($usuario, $idDueno);

    $this->actingAs($usuario, 'interno');

    $this->postJson(route('panel.rol-activo.actualizar'), ['id_role' => $idPiloto])
        ->assertOk();

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('rol_preferido_id'))
        ->toBe($idDueno);
});

// --- POST /panel/preferencias/tema ------------------------------------------

it('el POST de tema persiste la preferencia y responde el atributo de Bootstrap', function () {
    $usuario = SecUser::factory()->create();
    prefAsignarRol($usuario, 'piloto');

    $this->actingAs($usuario, 'interno');

    $this->postJson(route('panel.preferencias.tema'), ['tema' => 'dark'])
        ->assertOk()
        ->assertJson(['tema' => 'dark']);

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->first()->tema)
        ->toBe(TemaPreferencia::Oscuro);
});

it('el POST de tema rechaza valores fuera de light/dark', function () {
    $usuario = SecUser::factory()->create();
    prefAsignarRol($usuario, 'piloto');

    $this->actingAs($usuario, 'interno');

    $this->postJson(route('panel.preferencias.tema'), ['tema' => 'sepia'])
        ->assertStatus(422);
});

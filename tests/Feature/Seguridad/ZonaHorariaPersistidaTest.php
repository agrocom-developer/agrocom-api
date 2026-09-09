<?php

use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioCliente;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Tarea 63 — POST /panel/preferencias/zona-horaria y su equivalente de
 * portal: cambio EXPLÍCITO desde el selector, siempre pisa lo que hubiera
 * (a diferencia del fijado automático del login, ver FijarZonaHorariaUsuarioTest
 * y LoginPanelTest/LoginPortalTest). Sin `rol.activo`, mismo criterio que el
 * toggle de tema: el selector también vive en la pantalla de selección de rol.
 */

uses(RefreshDatabase::class);

it('persiste la zona horaria elegida en el selector del panel', function () {
    $usuario = SecUser::factory()->create();

    $this->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->postJson(route('panel.preferencias.zona-horaria'), ['zona_horaria' => 'America/La_Paz'])
        ->assertOk()
        ->assertJson(['zona_horaria' => 'America/La_Paz']);

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('zona_horaria'))->toBe('America/La_Paz');
});

it('el selector del panel pisa una zona horaria ya fijada por el login', function () {
    $usuario = SecUser::factory()->create();
    SecUserPreferencia::query()->create(['user_id' => $usuario->id, 'zona_horaria' => 'America/Asuncion']);

    $this->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->postJson(route('panel.preferencias.zona-horaria'), ['zona_horaria' => 'Europe/Madrid'])
        ->assertOk();

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('zona_horaria'))->toBe('Europe/Madrid');
});

it('rechaza con 422 un identificador que no existe en la base IANA, sin persistirlo', function () {
    $usuario = SecUser::factory()->create();

    $this->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->postJson(route('panel.preferencias.zona-horaria'), ['zona_horaria' => 'Marte/Cráter'])
        ->assertStatus(422);

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->exists())->toBeFalse();
});

it('no toca el tema ya persistido al cambiar solo la zona horaria', function () {
    $usuario = SecUser::factory()->create();
    SecUserPreferencia::query()->create(['user_id' => $usuario->id, 'tema' => TemaPreferencia::Oscuro]);

    $this->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->postJson(route('panel.preferencias.zona-horaria'), ['zona_horaria' => 'America/La_Paz'])
        ->assertOk();

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->first()->tema)->toBe(TemaPreferencia::Oscuro);
});

it('persiste la zona horaria elegida en el selector del portal, sin tocar el tema oscuro ya elegido', function () {
    $usuario = SecUser::factory()->create(['type' => TipoUsuario::Cliente]);
    SecUserPreferencia::query()->create(['user_id' => $usuario->id, 'tema' => TemaPreferencia::Oscuro]);

    $this->actingAs(SecUsuarioCliente::query()->findOrFail($usuario->id), 'cliente')
        ->postJson(route('portal.preferencias.zona-horaria'), ['zona_horaria' => 'America/Asuncion'])
        ->assertOk()
        ->assertJson(['zona_horaria' => 'America/Asuncion']);

    $preferencia = SecUserPreferencia::query()->where('user_id', $usuario->id)->first();

    expect($preferencia->zona_horaria)->toBe('America/Asuncion')
        ->and($preferencia->tema)->toBe(TemaPreferencia::Oscuro);
});

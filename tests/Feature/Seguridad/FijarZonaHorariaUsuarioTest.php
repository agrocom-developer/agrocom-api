<?php

use App\Dominios\Seguridad\Aplicacion\FijarZonaHorariaUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Tarea 63 — fijado automático de zona horaria en el login: SOLO si la
 * preferencia está vacía (nunca pisa una ya elegida, a mano o en un login
 * anterior) y SOLO si el navegador mandó un identificador IANA real. A
 * diferencia de ActualizarPreferenciaUsuario (cambio explícito, siempre
 * pisa), este caso de uso nunca lanza: un login no puede fallar por esto.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->caso = new FijarZonaHorariaUsuario;
});

it('fija la zona horaria cuando el usuario todavía no tenía preferencia', function () {
    $usuario = SecUser::factory()->create();

    $this->caso->ejecutarSiVacia($usuario, 'America/La_Paz');

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('zona_horaria'))->toBe('America/La_Paz');
});

it('fija la zona horaria cuando ya había preferencia de tema pero sin zona', function () {
    $usuario = SecUser::factory()->create();
    SecUserPreferencia::query()->create(['user_id' => $usuario->id]);

    $this->caso->ejecutarSiVacia($usuario, 'America/La_Paz');

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('zona_horaria'))->toBe('America/La_Paz');
});

it('no pisa una zona horaria ya elegida', function () {
    $usuario = SecUser::factory()->create();
    SecUserPreferencia::query()->create(['user_id' => $usuario->id, 'zona_horaria' => 'Europe/Madrid']);

    $this->caso->ejecutarSiVacia($usuario, 'America/La_Paz');

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('zona_horaria'))->toBe('Europe/Madrid');
});

it('ignora en silencio un identificador que no existe en la base IANA', function () {
    $usuario = SecUser::factory()->create();

    $this->caso->ejecutarSiVacia($usuario, 'Marte/Cráter');

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->exists())->toBeFalse();
});

it('ignora en silencio un valor nulo o vacío, sin crear la fila', function () {
    $usuario = SecUser::factory()->create();

    $this->caso->ejecutarSiVacia($usuario, null);
    $this->caso->ejecutarSiVacia($usuario, '');

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->exists())->toBeFalse();
});

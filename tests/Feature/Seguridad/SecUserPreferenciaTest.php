<?php

use App\Dominios\Seguridad\Aplicacion\ActualizarPreferenciaUsuario;
use App\Dominios\Seguridad\Dominio\Excepciones\IdiomaNoSoportado;
use App\Dominios\Seguridad\Dominio\Excepciones\ZonaHorariaInvalida;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-02 — ActualizarPreferenciaUsuario y SecUserPreferencia: preferencia de
 * panel (tema/idioma) por usuario, tabla satélite de sec_user dentro de
 * Seguridad, no un módulo Identidad (ADR 0011, extensión 27/8/2026, puntos
 * 7-8; ADR 0013 punto 2: español único idioma habilitado en v1).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->caso = new ActualizarPreferenciaUsuario;
});

it('crea la preferencia cuando el usuario todavía no tenía una', function () {
    $usuario = SecUser::factory()->create();

    $preferencia = $this->caso->ejecutar($usuario, TemaPreferencia::Oscuro, 'es');

    expect($preferencia->exists)->toBeTrue()
        ->and($preferencia->user_id)->toBe($usuario->id)
        ->and($preferencia->tema)->toBe(TemaPreferencia::Oscuro)
        ->and($preferencia->idioma)->toBe('es')
        ->and($usuario->preferencia()->first()->tema)->toBe(TemaPreferencia::Oscuro);
});

it('actualiza la preferencia existente en vez de duplicarla', function () {
    $usuario = SecUser::factory()->create();

    $this->caso->ejecutar($usuario, TemaPreferencia::Claro, 'es');
    $this->caso->ejecutar($usuario, TemaPreferencia::Oscuro, 'es');

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->count())->toBe(1)
        ->and(SecUserPreferencia::query()->where('user_id', $usuario->id)->first()->tema)->toBe(TemaPreferencia::Oscuro);
});

it('rechaza un idioma no soportado en v1, sin dejar preferencia a medias', function () {
    $usuario = SecUser::factory()->create();

    expect(fn () => $this->caso->ejecutar($usuario, TemaPreferencia::Claro, 'en'))
        ->toThrow(IdiomaNoSoportado::class);

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->exists())->toBeFalse();
});

it('la columna tema por defecto es claro y el idioma es es cuando se omiten', function () {
    $usuario = SecUser::factory()->create();

    $preferencia = new SecUserPreferencia(['user_id' => $usuario->id]);
    $preferencia->created_by = $usuario->id;
    $preferencia->updated_by = $usuario->id;
    $preferencia->save();

    expect($preferencia->fresh()->tema)->toBe(TemaPreferencia::Claro)
        ->and($preferencia->fresh()->idioma)->toBe('es');
});

it('revocar (soft delete) la preferencia no borra físicamente la fila', function () {
    $usuario = SecUser::factory()->create();
    $preferencia = $this->caso->ejecutar($usuario, TemaPreferencia::Oscuro, 'es');

    $preferencia->delete();

    expect(SecUserPreferencia::query()->whereKey($preferencia->id)->exists())->toBeFalse()
        ->and(SecUserPreferencia::withTrashed()->whereKey($preferencia->id)->exists())->toBeTrue();
});

/*
 * Tarea 63 — zona horaria IANA, tercer campo de la misma preferencia. A
 * diferencia de tema/idioma (siempre se mandan), acá `null` significa "no
 * la toques": el selector manual pasa el valor elegido, pero nada más del
 * caso de uso (ej. `PreferenciasController::actualizarTema`) debe poder
 * borrarla de rebote.
 */
it('guarda la zona horaria cuando es un identificador IANA válido', function () {
    $usuario = SecUser::factory()->create();

    $preferencia = $this->caso->ejecutar($usuario, TemaPreferencia::Claro, 'es', 'America/La_Paz');

    expect($preferencia->zona_horaria)->toBe('America/La_Paz')
        ->and($preferencia->fresh()->zona_horaria)->toBe('America/La_Paz');
});

it('no toca la zona horaria ya guardada cuando se omite en un cambio de tema', function () {
    $usuario = SecUser::factory()->create();
    $this->caso->ejecutar($usuario, TemaPreferencia::Claro, 'es', 'America/La_Paz');

    $this->caso->ejecutar($usuario, TemaPreferencia::Oscuro, 'es');

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->first()->zona_horaria)->toBe('America/La_Paz');
});

it('rechaza un identificador de zona horaria que no existe en la base IANA', function () {
    $usuario = SecUser::factory()->create();

    expect(fn () => $this->caso->ejecutar($usuario, TemaPreferencia::Claro, 'es', 'Marte/Cráter'))
        ->toThrow(ZonaHorariaInvalida::class);

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->exists())->toBeFalse();
});

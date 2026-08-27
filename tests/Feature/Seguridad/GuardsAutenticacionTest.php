<?php

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioCliente;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-01 — coordinación §6 del diseño `modulos-roles`: sec_user reemplaza a
 * App\Models\User como única fuente de identidad, con dos guards separados
 * (`interno`/`cliente`) sobre la misma tabla física. Estos tests fijan esa
 * configuración para que una regresión futura (p. ej. alguien reintroduciendo
 * un guard sin scope) no pase inadvertida.
 */

uses(RefreshDatabase::class);

it('el guard y los providers por defecto apuntan a SecUser, sin App\Models\User', function () {
    expect(config('auth.defaults.guard'))->toBe('interno')
        ->and(config('auth.guards.interno.provider'))->toBe('usuarios_internos')
        ->and(config('auth.guards.cliente.provider'))->toBe('usuarios_cliente')
        ->and(config('auth.providers.usuarios_internos.model'))->toBe(SecUsuarioInterno::class)
        ->and(config('auth.providers.usuarios_cliente.model'))->toBe(SecUsuarioCliente::class);
});

it('el modelo del guard interno solo ve cuentas type=interno, nunca las de portal', function () {
    SecUser::query()->create([
        'name' => 'Interno Demo',
        'username' => 'interno.demo',
        'password' => 'secreto123',
        'type' => TipoUsuario::Interno,
    ]);

    SecUser::query()->create([
        'name' => 'Cliente Demo',
        'username' => 'cliente.demo',
        'password' => 'secreto123',
        'type' => TipoUsuario::Cliente,
    ]);

    expect(SecUsuarioInterno::query()->pluck('username')->all())->toBe(['interno.demo'])
        ->and(SecUsuarioCliente::query()->pluck('username')->all())->toBe(['cliente.demo']);
});

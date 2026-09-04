<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioCliente;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-41 (tarea 55) — POST /portal/login y POST /portal/logout: autenticación
 * del portal del cliente por `username`+password (nunca correo) contra el
 * guard `cliente`, sin selección de rol (ADR 0004: una cuenta de portal no
 * tiene `sec_user_role`). Cubre además que ningún guard autentique la cuenta
 * del otro (invariante 5 de CLAUDE.md aplicada a la autenticación misma).
 */

uses(RefreshDatabase::class);

function contratoParaLoginPortal(): Contrato
{
    $cliente = Cliente::create(['razon_social' => 'Cliente portal login']);

    return Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '10.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '5.00',
        'monto_total' => '0.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
}

it('inicia sesión de portal por username y contrato, sin pedir selección de rol', function () {
    $contrato = contratoParaLoginPortal();
    $usuario = SecUser::factory()->create([
        'username' => 'cliente.portal',
        'password' => 'Secreta123',
        'type' => TipoUsuario::Cliente,
        'contrato_id' => $contrato->id,
    ]);

    $respuesta = $this->postJson('/portal/login', ['username' => 'cliente.portal', 'password' => 'Secreta123']);

    $respuesta->assertOk()->assertJsonMissing(['requiere_seleccion_rol']);

    $this->assertAuthenticatedAs(SecUsuarioCliente::query()->findOrFail($usuario->id), 'cliente');
});

it('rechaza credenciales inválidas con 422 y no autentica', function () {
    $contrato = contratoParaLoginPortal();
    SecUser::factory()->create([
        'username' => 'cliente.portal',
        'password' => 'Secreta123',
        'type' => TipoUsuario::Cliente,
        'contrato_id' => $contrato->id,
    ]);

    $respuesta = $this->postJson('/portal/login', ['username' => 'cliente.portal', 'password' => 'incorrecta']);

    $respuesta->assertStatus(422);
    $this->assertGuest('cliente');
});

it('exige username y password', function () {
    $this->postJson('/portal/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'password']);
});

it('no autentica una cuenta interna contra el guard cliente', function () {
    SecUser::factory()->create(['username' => 'interno.demo', 'password' => 'Secreta123', 'type' => TipoUsuario::Interno]);

    $respuesta = $this->postJson('/portal/login', ['username' => 'interno.demo', 'password' => 'Secreta123']);

    $respuesta->assertStatus(422);
    $this->assertGuest('cliente');
});

it('no autentica una cuenta de portal contra el guard interno', function () {
    $contrato = contratoParaLoginPortal();
    SecUser::factory()->create([
        'username' => 'cliente.portal',
        'password' => 'Secreta123',
        'type' => TipoUsuario::Cliente,
        'contrato_id' => $contrato->id,
    ]);

    $respuesta = $this->postJson('/login', ['username' => 'cliente.portal', 'password' => 'Secreta123']);

    $respuesta->assertStatus(422);
    $this->assertGuest('interno');
});

it('cierra la sesión de portal', function () {
    $contrato = contratoParaLoginPortal();
    $usuario = SecUser::factory()->create([
        'username' => 'cliente.portal',
        'password' => 'Secreta123',
        'type' => TipoUsuario::Cliente,
        'contrato_id' => $contrato->id,
    ]);

    $this->actingAs(SecUsuarioCliente::query()->findOrFail($usuario->id), 'cliente')
        ->postJson('/portal/logout')
        ->assertOk();

    $this->assertGuest('cliente');
});

it('/portal/logout no es alcanzable por una sesión del panel interno', function () {
    $usuario = SecUser::factory()->create(['type' => TipoUsuario::Interno]);

    $this->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->postJson('/portal/logout')
        ->assertUnauthorized();
});

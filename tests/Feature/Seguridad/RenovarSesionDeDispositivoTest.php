<?php

use App\Dominios\Proveedores\Contratos\Eventos\ProveedorRegistrado;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Soporte\Esquema;

/*
 * Sesión por biometría (ADR 0046, punto 32): el servidor no recibe biometría; solo valida, renueva y revoca el token del
 * dispositivo, que la app guarda en el almacenamiento seguro y reutiliza tras el desbloqueo local.
 */

const UUID_BIO = '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31';

beforeEach(function () {
    RateLimiter::for('registro-publico', fn () => Limit::none());
    Cache::flush();
    Event::fake([ProveedorRegistrado::class]);

    Esquema::migrar([
        'create_plt_bitacoras_table', 'create_sec_user_table', 'agrega_foto_perfil_a_sec_user', 'agrega_tipo_cuenta_a_sec_user',
        'crea_sec_personas_y_telefono_de_cuenta', 'create_sec_role_table', 'create_sec_permission_table', 'create_sec_role_permission_table',
        'create_sec_user_role_table', 'create_sec_token_dispositivo_table', 'create_sol_zonas_table', 'create_sol_clientes_table',
        'create_sol_terrenos_table', 'create_sol_solicitudes_servicio_table', 'agrega_reputacion_a_sol_clientes',
        'lleva_sol_clientes_al_ciclo_de_aprobacion', 'agrega_motivo_estado_a_sol_clientes', 'crea_sol_cliente_contactos',
        'create_prv_proveedores_table', 'create_prv_pilotos_table', 'agrega_negociacion_a_sol_solicitudes_servicio',
        'agrega_ubicacion_a_sol_terrenos', 'agrega_ubicacion_a_sol_solicitudes_servicio', 'retira_zonas', 'create_sec_user_preferencia_table', 'crea_sec_eliminaciones',
    ]);

    foreach (['sec_user_preferencia', 'sec_token_dispositivo', 'sol_cliente_contactos', 'sol_clientes', 'sec_user_role', 'sec_user', 'sec_personas', 'sec_role'] as $tabla) {
        DB::table($tabla)->delete();
    }

    DB::table('sec_role')->insert(['name' => 'cliente', 'description' => 'cliente', 'state' => true, 'created_at' => now(), 'updated_at' => now()]);

    $this->postJson('/api/clientes/registro', ['tipo' => 'persona', 'nombre' => 'Luis Antelo', 'username' => 'luis.antelo', 'password' => 'abcd', 'telefono' => '70012345'])->assertCreated();
});

function entrarEnBio(): array
{
    $respuesta = test()->postJson('/api/auth/token', ['username' => 'luis.antelo', 'password' => 'abcd', 'uuid_dispositivo' => UUID_BIO, 'nombre_dispositivo' => 'Moto G', 'app' => 'cliente'])->assertCreated();

    return [$respuesta->json('token'), $respuesta];
}

it('el dispositivo informa cuándo caduca su token', function () {
    [, $respuesta] = entrarEnBio();

    expect($respuesta->json('dispositivo.expira_en'))->not->toBeNull();
});

it('renovar emite un token nuevo para el mismo dispositivo y revoca el anterior', function () {
    [$viejo] = entrarEnBio();

    $renovado = comoDispositivo($viejo)->postJson('/api/auth/renovar')->assertCreated();
    $nuevo = $renovado->json('token');

    expect($nuevo)->not->toBe($viejo)
        ->and($renovado->json('dispositivo.uuid_dispositivo'))->toBe(UUID_BIO)
        ->and($renovado->json('dispositivo.nombre_dispositivo'))->toBe('Moto G');

    comoDispositivo($viejo)->getJson('/api/auth/sesion')->assertStatus(401);
    comoDispositivo($nuevo)->getJson('/api/auth/sesion')->assertOk();
    expect(DB::table('sec_token_dispositivo')->whereNull('deleted_at')->count())->toBe(1);
});

it('un token revocado, caducado o ausente no se renueva', function () {
    [$token] = entrarEnBio();

    $this->postJson('/api/auth/renovar')->assertStatus(401);

    comoDispositivo($token)->deleteJson('/api/auth/token')->assertNoContent();
    comoDispositivo($token)->postJson('/api/auth/renovar')->assertStatus(401);

    [$otro] = entrarEnBio();
    DB::table('sec_token_dispositivo')->whereNull('deleted_at')->update(['expires_at' => now()->subMinute()]);
    comoDispositivo($otro)->postJson('/api/auth/renovar')->assertStatus(401);
});

it('una cuenta deshabilitada no renueva', function () {
    [$token] = entrarEnBio();
    DB::table('sec_user')->where('username', 'luis.antelo')->update(['state' => false]);

    comoDispositivo($token)->postJson('/api/auth/renovar')->assertStatus(401);
});

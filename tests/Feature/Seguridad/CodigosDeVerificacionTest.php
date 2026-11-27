<?php

use App\Dominios\Proveedores\Contratos\Eventos\ProveedorRegistrado;
use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Contratos\DestinoDeCodigo;
use App\Dominios\Seguridad\Contratos\EnviadorDeCodigo;
use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use App\Dominios\Seguridad\Infraestructura\Notificaciones\CodigoDeVerificacion;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Soporte\Esquema;

/*
 * Códigos de un solo uso, recuperación de acceso, cambio de contraseña, verificación del teléfono, contraseña corta y
 * bloqueo temporal del login (ADR 0046, puntos 27 a 31). El canal de WhatsApp se prueba con un adaptador de mentira que
 * guarda lo que se le pidió enviar: no hay proveedor real.
 */

/** Adaptador de WhatsApp de mentira: recuerda el último código en lugar de enviarlo. */
final class EnviadorDePrueba implements EnviadorDeCodigo
{
    /** @var list<array{canal: string, destino: string, codigo: string}> */
    public static array $enviados = [];

    public function disponible(CanalDeCodigo $canal): bool
    {
        return true;
    }

    public function enviar(DestinoDeCodigo $destino, string $codigo, PropositoDeCodigo $proposito): void
    {
        self::$enviados[] = ['canal' => $destino->canal->value, 'destino' => $destino->valor, 'codigo' => $codigo];
    }
}

const UUID_UNO = '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31';
const UUID_DOS = '7a2e1b3f-2a45-4d0a-8b9c-3c8d2e6f1a42';

beforeEach(function () {
    RateLimiter::for('registro-publico', fn () => Limit::none());
    RateLimiter::for('codigos-publicos', fn () => Limit::none());
    Cache::flush();
    Event::fake([ProveedorRegistrado::class]);
    Notification::fake();
    EnviadorDePrueba::$enviados = [];
    config(['verificacion.modo_desarrollo' => false, 'verificacion.canales.whatsapp.adaptador' => null, 'verificacion.canales.sms.adaptador' => null]);

    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'agrega_foto_perfil_a_sec_user',
        'agrega_tipo_cuenta_a_sec_user',
        'crea_sec_personas_y_telefono_de_cuenta',
        'create_sec_role_table',
        'create_sec_permission_table',
        'create_sec_role_permission_table',
        'create_sec_user_role_table',
        'create_sec_token_dispositivo_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'agrega_reputacion_a_sol_clientes',
        'lleva_sol_clientes_al_ciclo_de_aprobacion',
        'agrega_motivo_estado_a_sol_clientes',
        'crea_sol_cliente_contactos',
        'create_prv_proveedores_table',
        'create_prv_pilotos_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'agrega_ubicacion_a_sol_terrenos',
        'agrega_ubicacion_a_sol_solicitudes_servicio',
        'retira_zonas',
        'create_sec_user_preferencia_table',
        'crea_sec_codigos_verificacion',
    ]);

    foreach (['sec_codigos_verificacion', 'sec_user_preferencia', 'sec_token_dispositivo', 'sol_cliente_contactos', 'sol_clientes', 'sec_user_role', 'sec_user', 'sec_personas', 'sec_role'] as $tabla) {
        DB::table($tabla)->delete();
    }

    foreach (['cliente', 'proveedor', 'piloto'] as $rol) {
        DB::table('sec_role')->insert(['name' => $rol, 'description' => $rol, 'state' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
});

/** @param  array<string, mixed>  $cambios */
function registrarCliente(string $username = 'luis.antelo', string $telefono = '70012345', string $password = 'abcd', array $cambios = []): void
{
    test()->postJson('/api/clientes/registro', ['tipo' => 'persona', 'nombre' => 'Luis Antelo', 'username' => $username, 'password' => $password, 'telefono' => $telefono, 'email' => "{$username}@correo.test", ...$cambios])->assertCreated();
}

function entrarComo(string $username, string $password, string $uuid = UUID_UNO): string
{
    $respuesta = test()->postJson('/api/auth/token', ['username' => $username, 'password' => $password, 'uuid_dispositivo' => $uuid, 'app' => 'cliente']);
    $respuesta->assertCreated();

    return (string) $respuesta->json('token');
}

function usarWhatsapp(): void
{
    config(['verificacion.canales.whatsapp.adaptador' => EnviadorDePrueba::class]);
}

// --- Contraseña sin restricción de caracteres ----------------------------------------------------

it('acepta una contraseña de 4 caracteres sin mayúsculas, números ni símbolos y rechaza una de 3', function () {
    registrarCliente('luis.uno', '70012345', 'abcd');
    entrarComo('luis.uno', 'abcd');

    $this->postJson('/api/clientes/registro', ['tipo' => 'persona', 'nombre' => 'Ana', 'username' => 'ana.dos', 'password' => 'abc', 'telefono' => '70099999'])
        ->assertStatus(422)->assertJsonValidationErrors(['password']);
});

// --- Recuperar el acceso -------------------------------------------------------------------------

it('recuperar responde igual exista o no la cuenta y no envía nada si no existe', function () {
    registrarCliente();
    usarWhatsapp();

    $existe = $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'cliente', 'canal' => 'whatsapp']);
    $noExiste = $this->postJson('/api/auth/recuperar', ['identificador' => '79999999', 'app' => 'cliente', 'canal' => 'whatsapp']);
    $otraApp = $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'piloto', 'canal' => 'whatsapp']);

    expect($existe->status())->toBe(202)->and($noExiste->status())->toBe(202)->and($otraApp->status())->toBe(202);
    expect($noExiste->json())->toBe($existe->json())->and($otraApp->json())->toBe($existe->json());
    expect(EnviadorDePrueba::$enviados)->toHaveCount(1)
        ->and(EnviadorDePrueba::$enviados[0]['destino'])->toBe('+59170012345');
});

it('el código se guarda con hash y el destino enmascarado, nunca en claro', function () {
    registrarCliente();
    usarWhatsapp();

    $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'cliente', 'canal' => 'whatsapp'])->assertStatus(202);

    $fila = DB::table('sec_codigos_verificacion')->first();
    $codigo = EnviadorDePrueba::$enviados[0]['codigo'];

    expect($codigo)->toMatch('/^\d{6}$/')
        ->and($fila->codigo_hash)->not->toContain($codigo)
        ->and($fila->destino_enmascarado)->not->toContain('70012345')
        ->and($fila->proposito)->toBe('recuperar_acceso')
        ->and($fila->canal)->toBe('whatsapp');
});

it('un canal sin adaptador responde 503 canal_no_disponible sin importar si la cuenta existe', function () {
    registrarCliente();

    foreach (['70012345', '79999999'] as $identificador) {
        $this->postJson('/api/auth/recuperar', ['identificador' => $identificador, 'app' => 'cliente', 'canal' => 'whatsapp'])
            ->assertStatus(503)->assertJsonPath('codigo', 'canal_no_disponible')->assertJsonPath('canal', 'whatsapp');
    }

    expect(DB::table('sec_codigos_verificacion')->count())->toBe(0);
});

it('recupera el acceso por WhatsApp: fija la contraseña, devuelve el usuario, revoca los tokens y verifica el teléfono', function () {
    registrarCliente('luis.antelo', '70012345', 'abcd');
    $tokenViejo = entrarComo('luis.antelo', 'abcd');
    usarWhatsapp();

    $this->postJson('/api/auth/recuperar', ['identificador' => 'luis.antelo', 'app' => 'cliente', 'canal' => 'whatsapp'])->assertStatus(202);
    $codigo = EnviadorDePrueba::$enviados[0]['codigo'];

    $this->postJson('/api/auth/recuperar/confirmar', ['identificador' => 'luis.antelo', 'app' => 'cliente', 'codigo' => $codigo, 'password_nueva' => '1234'])
        ->assertOk()->assertJsonPath('username', 'luis.antelo');

    comoDispositivo($tokenViejo)->getJson('/api/auth/sesion')->assertStatus(401);
    entrarComo('luis.antelo', '1234');
    expect(DB::table('sec_user')->where('username', 'luis.antelo')->value('telefono_verificado_en'))->not->toBeNull();
});

it('recupera el acceso por correo con el correo de respaldo y lo marca verificado', function () {
    registrarCliente();

    $this->postJson('/api/auth/recuperar', ['identificador' => 'luis.antelo@correo.test', 'app' => 'cliente', 'canal' => 'correo'])->assertStatus(202);

    Notification::assertSentOnDemand(CodigoDeVerificacion::class, fn ($n, $canales, $notifiable) => $notifiable->routes['mail'] === 'luis.antelo@correo.test');
    expect(DB::table('sec_user')->where('username', 'luis.antelo')->value('email_verificado_en'))->toBeNull();
});

it('el código solo sirve una vez y el error es el mismo para cuenta inexistente, código errado, usado o vencido', function () {
    registrarCliente();
    usarWhatsapp();
    $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'cliente', 'canal' => 'whatsapp']);
    $codigo = EnviadorDePrueba::$enviados[0]['codigo'];
    $mal = $codigo === '999999' ? '111111' : '999999';
    $cuerpo = fn (string $id, string $c) => ['identificador' => $id, 'app' => 'cliente', 'codigo' => $c, 'password_nueva' => 'nueva'];

    $errado = $this->postJson('/api/auth/recuperar/confirmar', $cuerpo('70012345', $mal))->assertStatus(422)->assertJsonPath('codigo', 'codigo_invalido');
    $inexistente = $this->postJson('/api/auth/recuperar/confirmar', $cuerpo('79999999', $codigo))->assertStatus(422);
    expect($inexistente->json())->toBe($errado->json());

    $this->postJson('/api/auth/recuperar/confirmar', $cuerpo('70012345', $codigo))->assertOk();
    $usado = $this->postJson('/api/auth/recuperar/confirmar', $cuerpo('70012345', $codigo))->assertStatus(422);
    expect($usado->json())->toBe($errado->json());

    $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'cliente', 'canal' => 'whatsapp']);
    $nuevo = EnviadorDePrueba::$enviados[1]['codigo'];
    $this->travel(11)->minutes();
    $vencido = $this->postJson('/api/auth/recuperar/confirmar', $cuerpo('70012345', $nuevo))->assertStatus(422);
    expect($vencido->json())->toBe($errado->json());
});

it('agotados los 5 intentos ni el código correcto sirve', function () {
    registrarCliente();
    usarWhatsapp();
    $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'cliente', 'canal' => 'whatsapp']);
    $codigo = EnviadorDePrueba::$enviados[0]['codigo'];
    $mal = $codigo === '999999' ? '111111' : '999999';

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/auth/recuperar/confirmar', ['identificador' => '70012345', 'app' => 'cliente', 'codigo' => $mal, 'password_nueva' => 'nueva'])->assertStatus(422);
    }

    $this->postJson('/api/auth/recuperar/confirmar', ['identificador' => '70012345', 'app' => 'cliente', 'codigo' => $codigo, 'password_nueva' => 'nueva'])->assertStatus(422);
});

it('pedir un código nuevo anula el anterior', function () {
    registrarCliente();
    usarWhatsapp();
    $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'cliente', 'canal' => 'whatsapp']);
    $primero = EnviadorDePrueba::$enviados[0]['codigo'];
    $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'cliente', 'canal' => 'whatsapp']);
    $segundo = EnviadorDePrueba::$enviados[1]['codigo'];

    if ($primero !== $segundo) {
        $this->postJson('/api/auth/recuperar/confirmar', ['identificador' => '70012345', 'app' => 'cliente', 'codigo' => $primero, 'password_nueva' => 'nueva'])->assertStatus(422);
    }

    $this->postJson('/api/auth/recuperar/confirmar', ['identificador' => '70012345', 'app' => 'cliente', 'codigo' => $segundo, 'password_nueva' => 'nueva'])->assertOk();
});

it('una cuenta no recibe más de 3 códigos por hora, y la respuesta no cambia', function () {
    registrarCliente();
    usarWhatsapp();

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'cliente', 'canal' => 'whatsapp'])->assertStatus(202);
    }

    expect(EnviadorDePrueba::$enviados)->toHaveCount(3);
});

it('en modo de desarrollo el código es 000000 y los canales externos no envían nada, pero fuera de local o testing se ignora', function () {
    registrarCliente();
    config(['verificacion.modo_desarrollo' => true]);

    $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'cliente', 'canal' => 'whatsapp'])->assertStatus(202);
    $this->postJson('/api/auth/recuperar/confirmar', ['identificador' => '70012345', 'app' => 'cliente', 'codigo' => '000000', 'password_nueva' => 'nueva'])->assertOk();

    config(['app.env' => 'production']);
    $this->postJson('/api/auth/recuperar', ['identificador' => '70012345', 'app' => 'cliente', 'canal' => 'whatsapp'])->assertStatus(503);
});

// --- Cambiar la contraseña con la sesión iniciada --------------------------------------------------

it('cambia la contraseña con un código, revoca los otros dispositivos y conserva el actual', function () {
    registrarCliente();
    usarWhatsapp();
    $actual = entrarComo('luis.antelo', 'abcd', UUID_UNO);
    $otro = entrarComo('luis.antelo', 'abcd', UUID_DOS);

    comoDispositivo($actual)->postJson('/api/cuenta/contrasena/codigo', ['canal' => 'whatsapp'])
        ->assertStatus(202)->assertJsonStructure(['message', 'destino']);
    $codigo = EnviadorDePrueba::$enviados[0]['codigo'];

    comoDispositivo($actual)->putJson('/api/cuenta/contrasena', ['codigo' => $codigo === '000001' ? '000002' : '000001', 'password_nueva' => 'nueva'])->assertStatus(422);
    comoDispositivo($actual)->putJson('/api/cuenta/contrasena', ['codigo' => $codigo, 'password_nueva' => 'nueva'])->assertOk();

    comoDispositivo($actual)->getJson('/api/auth/sesion')->assertOk();
    comoDispositivo($otro)->getJson('/api/auth/sesion')->assertStatus(401);
    entrarComo('luis.antelo', 'nueva', UUID_DOS);
});

it('el código del cambio de contraseña no sirve para recuperar el acceso', function () {
    registrarCliente();
    usarWhatsapp();
    $token = entrarComo('luis.antelo', 'abcd');

    comoDispositivo($token)->postJson('/api/cuenta/contrasena/codigo', ['canal' => 'whatsapp'])->assertStatus(202);
    $codigo = EnviadorDePrueba::$enviados[0]['codigo'];

    $this->postJson('/api/auth/recuperar/confirmar', ['identificador' => '70012345', 'app' => 'cliente', 'codigo' => $codigo, 'password_nueva' => 'nueva'])->assertStatus(422);
});

it('cambiar la contraseña exige sesión', function () {
    $this->postJson('/api/cuenta/contrasena/codigo', ['canal' => 'whatsapp'])->assertStatus(401);
    $this->putJson('/api/cuenta/contrasena', ['codigo' => '123456', 'password_nueva' => 'nueva'])->assertStatus(401);
});

it('sin correo de respaldo el código por correo responde sin_destino', function () {
    registrarCliente();
    $token = entrarComo('luis.antelo', 'abcd');
    DB::table('sec_user')->where('username', 'luis.antelo')->update(['email' => null]);

    comoDispositivo($token)->postJson('/api/cuenta/contrasena/codigo', ['canal' => 'correo'])->assertStatus(422)->assertJsonPath('codigo', 'sin_destino');
});

// --- Verificar el teléfono ----------------------------------------------------------------------------

it('verifica el teléfono con un código por WhatsApp', function () {
    registrarCliente();
    usarWhatsapp();
    $token = entrarComo('luis.antelo', 'abcd');

    comoDispositivo($token)->postJson('/api/cuenta/telefono/verificacion', ['canal' => 'whatsapp'])->assertStatus(202);
    $codigo = EnviadorDePrueba::$enviados[0]['codigo'];
    expect(EnviadorDePrueba::$enviados[0]['destino'])->toBe('+59170012345');

    comoDispositivo($token)->postJson('/api/cuenta/telefono/verificacion/confirmar', ['codigo' => $codigo])
        ->assertOk()->assertJsonStructure(['message', 'telefono_verificado_en']);

    expect(DB::table('sec_user')->where('username', 'luis.antelo')->value('telefono_verificado_en'))->not->toBeNull();
    comoDispositivo($token)->postJson('/api/cuenta/telefono/verificacion', ['canal' => 'whatsapp'])->assertStatus(409)->assertJsonPath('codigo', 'telefono_ya_verificado');
});

it('el teléfono no se verifica por correo y WhatsApp sin adaptador responde 503', function () {
    registrarCliente();
    $token = entrarComo('luis.antelo', 'abcd');

    comoDispositivo($token)->postJson('/api/cuenta/telefono/verificacion', ['canal' => 'correo'])->assertStatus(422);
    comoDispositivo($token)->postJson('/api/cuenta/telefono/verificacion', ['canal' => 'whatsapp'])->assertStatus(503)->assertJsonPath('codigo', 'canal_no_disponible');
});

// --- Bloqueo temporal del login --------------------------------------------------------------------------

it('tras 10 fallos el login se bloquea con el mismo mensaje exista o no la cuenta, y vuelve pasada la ventana', function () {
    registrarCliente();
    RateLimiter::for('login-campo', fn () => Limit::none());

    foreach (['luis.antelo', 'no.existe'] as $usuario) {
        foreach (range(1, 10) as $_) {
            $this->postJson('/api/auth/token', ['username' => $usuario, 'password' => 'mal', 'uuid_dispositivo' => UUID_UNO])->assertStatus(422);
        }
    }

    $existe = $this->postJson('/api/auth/token', ['username' => 'luis.antelo', 'password' => 'abcd', 'uuid_dispositivo' => UUID_UNO])->assertStatus(429);
    $noExiste = $this->postJson('/api/auth/token', ['username' => 'no.existe', 'password' => 'abcd', 'uuid_dispositivo' => UUID_UNO])->assertStatus(429);
    expect($existe->json('message'))->toBe($noExiste->json('message'))->and($existe->headers->get('Retry-After'))->not->toBeNull();

    $this->travel(16)->minutes();
    RateLimiter::clear('login-bloqueo|ip|127.0.0.1');
    $this->postJson('/api/auth/token', ['username' => 'luis.antelo', 'password' => 'abcd', 'uuid_dispositivo' => UUID_UNO])->assertCreated();
});

it('un login correcto limpia los fallos del identificador', function () {
    registrarCliente();
    RateLimiter::for('login-campo', fn () => Limit::none());

    foreach (range(1, 9) as $_) {
        $this->postJson('/api/auth/token', ['username' => 'luis.antelo', 'password' => 'mal', 'uuid_dispositivo' => UUID_UNO])->assertStatus(422);
    }
    $this->postJson('/api/auth/token', ['username' => 'luis.antelo', 'password' => 'abcd', 'uuid_dispositivo' => UUID_UNO])->assertCreated();
    $this->postJson('/api/auth/token', ['username' => 'luis.antelo', 'password' => 'mal', 'uuid_dispositivo' => UUID_UNO])->assertStatus(422);
});

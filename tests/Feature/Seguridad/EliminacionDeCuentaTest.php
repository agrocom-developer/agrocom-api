<?php

use App\Dominios\Proveedores\Contratos\Eventos\ProveedorRegistrado;
use App\Dominios\Seguridad\Aplicacion\AnonimizarCuentaDeSeguridad;
use App\Dominios\Seguridad\Aplicacion\CodigosDeVerificacion;
use App\Dominios\Seguridad\Aplicacion\EjecutarEliminacionesDeCuenta;
use App\Dominios\Seguridad\Aplicacion\EliminarMiCuenta;
use App\Dominios\Seguridad\Aplicacion\MaquinaEstados\MaquinaEstadosEliminacion;
use App\Dominios\Seguridad\Aplicacion\RevocarTokensDeCuenta;
use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Contratos\DestinoDeCodigo;
use App\Dominios\Seguridad\Contratos\EnviadorDeCodigo;
use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;
use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Soporte\Esquema;

/*
 * Eliminar mi cuenta desde la app (ADR 0047, puntos 1 a 6): pedirla con la contraseña o con un código, lo que la impide, lo que
 * se deshace al pedirla, los 7 días de gracia con la sesión restringida, la cancelación y la anonimización al vencer la gracia.
 * Los módulos que participan (solicitudes, proveedores…) se prueban aparte; acá se usa un participante de mentira que registra
 * lo que se le pidió.
 */

/** Participante de mentira: impide si se le pide y recuerda cada llamada. */
final class ParticipanteDePrueba implements ParticipanteDeEliminacion
{
    /** @var list<string> */
    public static array $impedimentos = [];

    /** @var list<string> */
    public static array $llamadas = [];

    public function impedimentos(int $usuarioId): array
    {
        return self::$impedimentos;
    }

    public function alSolicitar(int $usuarioId): void
    {
        self::$llamadas[] = "solicitar:{$usuarioId}";
    }

    public function anonimizar(int $usuarioId): void
    {
        self::$llamadas[] = "anonimizar:{$usuarioId}";
    }
}

/** WhatsApp de mentira. */
final class WhatsappDePruebaEliminacion implements EnviadorDeCodigo
{
    /** @var list<string> */
    public static array $codigos = [];

    public function disponible(CanalDeCodigo $canal): bool
    {
        return true;
    }

    public function enviar(DestinoDeCodigo $destino, string $codigo, PropositoDeCodigo $proposito): void
    {
        self::$codigos[] = $codigo;
    }
}

const UUID_ELIM = '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31';

beforeEach(function () {
    RateLimiter::for('registro-publico', fn () => Limit::none());
    Cache::flush();
    Event::fake([ProveedorRegistrado::class]);
    ParticipanteDePrueba::$impedimentos = [];
    ParticipanteDePrueba::$llamadas = [];
    WhatsappDePruebaEliminacion::$codigos = [];
    config(['verificacion.modo_desarrollo' => false, 'verificacion.canales.whatsapp.adaptador' => WhatsappDePruebaEliminacion::class]);

    Esquema::migrar([
        'create_plt_bitacoras_table', 'create_sec_user_table', 'agrega_foto_perfil_a_sec_user', 'agrega_tipo_cuenta_a_sec_user',
        'crea_sec_personas_y_telefono_de_cuenta', 'create_sec_role_table', 'create_sec_permission_table', 'create_sec_role_permission_table',
        'create_sec_user_role_table', 'create_sec_token_dispositivo_table', 'create_sol_zonas_table', 'create_sol_clientes_table',
        'create_sol_terrenos_table', 'create_sol_solicitudes_servicio_table', 'agrega_reputacion_a_sol_clientes',
        'lleva_sol_clientes_al_ciclo_de_aprobacion', 'agrega_motivo_estado_a_sol_clientes', 'crea_sol_cliente_contactos',
        'create_prv_proveedores_table', 'create_prv_pilotos_table', 'agrega_negociacion_a_sol_solicitudes_servicio',
        'agrega_ubicacion_a_sol_terrenos', 'agrega_ubicacion_a_sol_solicitudes_servicio', 'retira_zonas', 'create_sec_user_preferencia_table',
        'crea_sec_codigos_verificacion', 'crea_sec_eliminaciones',
    ]);

    foreach (['sec_eliminaciones', 'sec_codigos_verificacion', 'sec_user_preferencia', 'sec_token_dispositivo', 'sol_cliente_contactos', 'sol_clientes', 'sec_user_role', 'sec_user', 'sec_personas', 'sec_role'] as $tabla) {
        DB::table($tabla)->delete();
    }

    DB::table('sec_role')->insert(['name' => 'cliente', 'description' => 'cliente', 'state' => true, 'created_at' => now(), 'updated_at' => now()]);

    // Solo el participante de mentira: los módulos reales se prueban en sus propias pruebas.
    app()->bind(EliminarMiCuenta::class, fn ($app) => new EliminarMiCuenta($app->make(CodigosDeVerificacion::class), $app->make(RevocarTokensDeCuenta::class), $app->make(MaquinaEstadosEliminacion::class), [new ParticipanteDePrueba]));
    app()->bind(EjecutarEliminacionesDeCuenta::class, fn ($app) => new EjecutarEliminacionesDeCuenta([new ParticipanteDePrueba], $app->make(AnonimizarCuentaDeSeguridad::class), $app->make(MaquinaEstadosEliminacion::class)));

    test()->postJson('/api/clientes/registro', ['tipo' => 'persona', 'nombre' => 'Luis Antelo', 'username' => 'luis.antelo', 'password' => 'abcd', 'telefono' => '70012345', 'email' => 'luis@correo.test'])->assertCreated();
});

function entrarParaEliminar(string $password = 'abcd'): string
{
    $respuesta = test()->postJson('/api/auth/token', ['username' => 'luis.antelo', 'password' => $password, 'uuid_dispositivo' => UUID_ELIM, 'app' => 'cliente']);
    $respuesta->assertCreated();

    return (string) $respuesta->json('token');
}

it('informa que no hay eliminación pedida y lo que la impediría', function () {
    $token = entrarParaEliminar();
    ParticipanteDePrueba::$impedimentos = ['reclamo_abierto'];

    comoDispositivo($token)->getJson('/api/cuenta/eliminacion')
        ->assertOk()->assertJsonPath('eliminacion', null)->assertJsonPath('impedimentos', ['reclamo_abierto']);
});

it('sin contraseña ni código no acepta nada, y con una contraseña incorrecta tampoco', function () {
    $token = entrarParaEliminar();

    comoDispositivo($token)->postJson('/api/cuenta/eliminacion', [])->assertStatus(422)->assertJsonValidationErrors(['password', 'codigo']);
    comoDispositivo($token)->postJson('/api/cuenta/eliminacion', ['password' => 'incorrecta'])->assertStatus(422)->assertJsonPath('codigo', 'contrasena_incorrecta');

    expect(DB::table('sec_eliminaciones')->count())->toBe(0)->and(ParticipanteDePrueba::$llamadas)->toBe([]);
    comoDispositivo($token)->getJson('/api/auth/sesion')->assertOk();
});

it('algo que la impide responde 409 con los motivos y no cambia nada', function () {
    $token = entrarParaEliminar();
    ParticipanteDePrueba::$impedimentos = ['servicio_en_curso', 'pilotos_vinculados'];

    comoDispositivo($token)->postJson('/api/cuenta/eliminacion', ['password' => 'abcd'])
        ->assertStatus(409)->assertJsonPath('codigo', 'eliminacion_bloqueada')->assertJsonPath('motivos', ['servicio_en_curso', 'pilotos_vinculados']);

    expect(DB::table('sec_eliminaciones')->count())->toBe(0);
    comoDispositivo($token)->getJson('/api/auth/sesion')->assertOk();
});

it('con la contraseña: solicita, cierra todas las sesiones, deshace lo abierto y da 7 días de gracia', function () {
    $token = entrarParaEliminar();

    $respuesta = comoDispositivo($token)->postJson('/api/cuenta/eliminacion', ['password' => 'abcd'])->assertStatus(202);

    expect($respuesta->json('eliminacion.estado'))->toBe('solicitada');
    expect(now()->diffInDays($respuesta->json('eliminacion.ejecutar_desde'), false))->toBeGreaterThan(6.9)->toBeLessThan(7.1);
    expect(ParticipanteDePrueba::$llamadas)->toBe([sprintf('solicitar:%d', DB::table('sec_user')->value('id'))]);
    comoDispositivo($token)->getJson('/api/auth/sesion')->assertStatus(401);
});

it('con el código por WhatsApp en lugar de la contraseña', function () {
    $token = entrarParaEliminar();

    comoDispositivo($token)->postJson('/api/cuenta/eliminacion/codigo', ['canal' => 'whatsapp'])->assertStatus(202)->assertJsonStructure(['destino']);
    $codigo = WhatsappDePruebaEliminacion::$codigos[0];

    comoDispositivo($token)->postJson('/api/cuenta/eliminacion', ['codigo' => $codigo === '111111' ? '222222' : '111111'])->assertStatus(422)->assertJsonPath('codigo', 'codigo_invalido');
    comoDispositivo($token)->postJson('/api/cuenta/eliminacion', ['codigo' => $codigo])->assertStatus(202);
});

it('la sesión de una cuenta en eliminación solo ve el estado, cancela y cierra sesión', function () {
    comoDispositivo(entrarParaEliminar())->postJson('/api/cuenta/eliminacion', ['password' => 'abcd'])->assertStatus(202);
    $token = entrarParaEliminar();

    comoDispositivo($token)->getJson('/api/auth/sesion')->assertOk();
    comoDispositivo($token)->getJson('/api/cuenta/eliminacion')->assertOk()->assertJsonPath('eliminacion.estado', 'solicitada');
    comoDispositivo($token)->getJson('/api/dispositivos')->assertStatus(403)->assertJsonPath('codigo', 'cuenta_en_eliminacion');
    comoDispositivo($token)->postJson('/api/cuenta/contrasena/codigo', ['canal' => 'whatsapp'])->assertStatus(403)->assertJsonPath('codigo', 'cuenta_en_eliminacion');
    comoDispositivo($token)->deleteJson('/api/auth/token')->assertNoContent();
});

it('dentro de la gracia la persona conserva su cuenta y vuelve a operar', function () {
    comoDispositivo(entrarParaEliminar())->postJson('/api/cuenta/eliminacion', ['password' => 'abcd']);
    $token = entrarParaEliminar();

    comoDispositivo($token)->postJson('/api/cuenta/eliminacion/cancelar')->assertOk()->assertJsonPath('eliminacion.estado', 'cancelada');
    comoDispositivo($token)->getJson('/api/dispositivos')->assertOk();
    comoDispositivo($token)->postJson('/api/cuenta/eliminacion/cancelar')->assertStatus(404);

    $this->artisan('cuentas:ejecutar-eliminaciones')->assertSuccessful();
    expect(DB::table('sec_user')->where('username', 'luis.antelo')->exists())->toBeTrue();
});

it('pedirla otra vez no crea otra solicitud', function () {
    comoDispositivo(entrarParaEliminar())->postJson('/api/cuenta/eliminacion', ['password' => 'abcd'])->assertStatus(202);
    $token = entrarParaEliminar();

    expect(fn () => comoDispositivo($token)->postJson('/api/cuenta/eliminacion', ['password' => 'abcd']))->not->toThrow(Throwable::class);
    expect(DB::table('sec_eliminaciones')->count())->toBe(1);
});

it('vencida la gracia, la tarea diaria anonimiza la cuenta, libera el teléfono y el correo y es idempotente', function () {
    $token = entrarParaEliminar();
    comoDispositivo($token)->postJson('/api/cuenta/eliminacion', ['password' => 'abcd'])->assertStatus(202);
    $id = DB::table('sec_user')->value('id');

    $this->artisan('cuentas:ejecutar-eliminaciones')->assertSuccessful();
    expect(DB::table('sec_eliminaciones')->value('estado'))->toBe('solicitada');

    $this->travel(8)->days();
    $this->artisan('cuentas:ejecutar-eliminaciones')->assertSuccessful();

    $cuenta = DB::table('sec_user')->where('id', $id)->first();
    expect($cuenta->name)->toBe('Cuenta eliminada')
        ->and($cuenta->username)->toBe("eliminada-{$id}")
        ->and($cuenta->email)->toBeNull()
        ->and($cuenta->telefono)->toBeNull()
        ->and((bool) $cuenta->state)->toBeFalse();
    expect(DB::table('sec_eliminaciones')->value('estado'))->toBe('ejecutada')
        ->and(DB::table('sec_token_dispositivo')->where('user_id', $id)->count())->toBe(0)
        ->and(in_array("anonimizar:{$id}", ParticipanteDePrueba::$llamadas, true))->toBeTrue();

    $this->postJson('/api/auth/token', ['username' => 'luis.antelo', 'password' => 'abcd', 'uuid_dispositivo' => UUID_ELIM, 'app' => 'cliente'])->assertStatus(422);
    $this->postJson('/api/clientes/registro', ['tipo' => 'persona', 'nombre' => 'Luis Otra Vez', 'username' => 'luis.nuevo', 'password' => 'abcd', 'telefono' => '70012345', 'email' => 'luis@correo.test'])->assertCreated();

    ParticipanteDePrueba::$llamadas = [];
    $this->artisan('cuentas:ejecutar-eliminaciones')->assertSuccessful();
    expect(ParticipanteDePrueba::$llamadas)->toBe([]);
});

it('si algo la impide al vencer la gracia queda pospuesta y se ejecuta cuando se libera', function () {
    comoDispositivo(entrarParaEliminar())->postJson('/api/cuenta/eliminacion', ['password' => 'abcd'])->assertStatus(202);
    ParticipanteDePrueba::$impedimentos = ['reclamo_abierto'];

    $this->travel(8)->days();
    $this->artisan('cuentas:ejecutar-eliminaciones')->assertSuccessful();

    expect(DB::table('sec_eliminaciones')->value('estado'))->toBe('pospuesta')
        ->and(DB::table('sec_eliminaciones')->value('motivo_posposicion'))->toBe('reclamo_abierto')
        ->and(DB::table('sec_user')->where('username', 'luis.antelo')->exists())->toBeTrue();

    ParticipanteDePrueba::$impedimentos = [];
    $this->artisan('cuentas:ejecutar-eliminaciones')->assertSuccessful();

    expect(DB::table('sec_eliminaciones')->value('estado'))->toBe('ejecutada');
});

it('eliminar la cuenta exige sesión', function () {
    $this->getJson('/api/cuenta/eliminacion')->assertStatus(401);
    $this->postJson('/api/cuenta/eliminacion', ['password' => 'abcd'])->assertStatus(401);
    $this->postJson('/api/cuenta/eliminacion/cancelar')->assertStatus(401);
});

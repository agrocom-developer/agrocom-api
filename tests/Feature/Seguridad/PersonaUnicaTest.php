<?php

use App\Dominios\Proveedores\Contratos\Eventos\ProveedorRegistrado;
use App\Dominios\Seguridad\Contratos\DatosDePersona;
use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Seguridad\Dominio\TipoDocumento;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Soporte\Esquema;

/*
 * Persona única (ADR 0046, puntos 7 a 9; plan de cuentas, fase A3): una persona real es una fila de
 * `sec_personas`, identificada por su documento normalizado, y tiene a lo sumo UNA cuenta de cliente
 * y UNA de proveedor. Cambiar de teléfono no abre otra cuenta del mismo tipo. El `409` no revela
 * datos de nadie. Contra el esquema real (SQLite en memoria) y las rutas reales del registro.
 */

beforeEach(function () {
    RateLimiter::clear('registro-publico');
    Cache::flush();
    Event::fake([ProveedorRegistrado::class]);

    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'agrega_foto_perfil_a_sec_user',
        'agrega_tipo_cuenta_a_sec_user',
        'crea_sec_personas_y_telefono_de_cuenta',
        'create_sec_role_table',
        'create_sec_user_role_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'agrega_reputacion_a_sol_clientes',
        'agrega_motivo_estado_a_sol_clientes',
        'crea_sol_cliente_contactos',
        'create_prv_proveedores_table',
        'create_prv_pilotos_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
    ]);

    foreach (['prv_pilotos', 'prv_proveedores', 'sol_cliente_contactos', 'sol_clientes', 'sec_user_role', 'sec_user', 'sec_personas', 'sec_role'] as $tabla) {
        DB::table($tabla)->delete();
    }

    foreach (['cliente', 'proveedor', 'piloto'] as $rol) {
        DB::table('sec_role')->insert(['name' => $rol, 'description' => $rol, 'state' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
});

/** @param  array<string, mixed>  $cambios */
function datosDeCliente(string $username, string $telefono, array $cambios = []): array
{
    return [
        'tipo' => 'persona', 'nombre' => 'Rosa Condori Vargas', 'username' => $username, 'password' => 'Clave1234', 'telefono' => $telefono,
        'documento_tipo' => 'ci', 'documento_numero' => '7845123 SC', 'nombres' => 'Rosa', 'apellidos' => 'Condori Vargas', 'fecha_nacimiento' => '1985-03-02',
        ...$cambios,
    ];
}

/** @param  array<string, mixed>  $cambios */
function datosDeProveedor(string $username, string $telefono, array $cambios = []): array
{
    return [
        'tipo' => 'independiente', 'nombre' => 'Rosa Condori Vargas', 'username' => $username, 'password' => 'Clave1234', 'telefono' => $telefono,
        'razon_social' => 'Rosa Condori', 'documento_fiscal' => '5566'.crc32($username) % 1000,
        'nombres' => 'Rosa', 'apellidos' => 'Condori Vargas', 'documento_identidad' => '7845123 SC', 'fecha_nacimiento' => '1985-03-02',
        'licencia_numero' => 'L-100', 'licencia_vence' => now()->addYear()->toDateString(), 'categoria_habilitacion' => 'A',
        ...$cambios,
    ];
}

it('una persona con su documento tiene una sola cuenta de cliente: otro teléfono no abre otra', function () {
    $this->postJson('/api/clientes/registro', datosDeCliente('rosa.uno', '+59170000001'))->assertCreated();

    $segunda = $this->postJson('/api/clientes/registro', datosDeCliente('rosa.dos', '+59170000002'));

    $segunda->assertStatus(409);
    expect($segunda->json('codigo'))->toBe('cuenta_ya_registrada')
        ->and($segunda->json('tipo_cuenta'))->toBe('cliente')
        ->and(DB::table('sec_user')->where('tipo_cuenta', 'cliente')->count())->toBe(1)
        ->and(DB::table('sec_personas')->count())->toBe(1)
        ->and(DB::table('sol_clientes')->count())->toBe(1);
});

it('el documento se compara normalizado: mayúsculas, sin espacios ni puntos', function (string $variante) {
    $this->postJson('/api/clientes/registro', datosDeCliente('rosa.uno', '+59170000001', ['documento_numero' => '7845123 SC']))->assertCreated();

    $this->postJson('/api/clientes/registro', datosDeCliente('rosa.dos', '+59170000002', ['documento_numero' => $variante]))->assertStatus(409);

    expect(DB::table('sec_personas')->count())->toBe(1)
        ->and(DB::table('sec_personas')->value('documento_numero'))->toBe('7845123SC');
})->with(['7845123sc', '7.845.123 SC', ' 7845123  Sc ']);

it('el mismo número con otro tipo de documento es otra persona', function () {
    $this->postJson('/api/clientes/registro', datosDeCliente('rosa.uno', '+59170000001'))->assertCreated();

    $this->postJson('/api/clientes/registro', datosDeCliente('rosa.dos', '+59170000002', ['documento_tipo' => 'pasaporte']))->assertCreated();

    expect(DB::table('sec_personas')->count())->toBe(2);
});

it('una persona que ya es cliente puede registrarse como proveedor: misma persona, otra cuenta, sin sobrescribir sus datos', function () {
    $this->postJson('/api/clientes/registro', datosDeCliente('rosa.cliente', '+59170000001'))->assertCreated();

    $this->postJson('/api/proveedores/registro', datosDeProveedor('rosa.piloto', '+59170000009', ['nombres' => 'Rosa María', 'apellidos' => 'Condori']))->assertCreated();

    $cuentas = DB::table('sec_user')->orderBy('id')->get(['tipo_cuenta', 'persona_id', 'persona_datos_difieren']);

    expect(DB::table('sec_personas')->count())->toBe(1)
        ->and($cuentas->pluck('tipo_cuenta')->all())->toBe(['cliente', 'proveedor'])
        ->and($cuentas[0]->persona_id)->toBe($cuentas[1]->persona_id)
        // Los datos de la persona son los del primer registro: el segundo no los pisa…
        ->and(DB::table('sec_personas')->value('nombres'))->toBe('Rosa')
        ->and(DB::table('sec_personas')->value('apellidos'))->toBe('Condori Vargas')
        // …y la cuenta cuyos datos no coinciden queda marcada para quien revisa.
        ->and((bool) $cuentas[0]->persona_datos_difieren)->toBeFalse()
        ->and((bool) $cuentas[1]->persona_datos_difieren)->toBeTrue();
});

it('una persona con una cuenta de proveedor no abre otra, pero sí una de cliente', function () {
    $this->postJson('/api/proveedores/registro', datosDeProveedor('rosa.uno', '+59170000001'))->assertCreated();

    $this->postJson('/api/proveedores/registro', datosDeProveedor('rosa.dos', '+59170000002'))->assertStatus(409)->assertJsonPath('tipo_cuenta', 'proveedor');
    $this->postJson('/api/clientes/registro', datosDeCliente('rosa.cliente', '+59170000003'))->assertCreated();

    expect(DB::table('sec_user')->where('tipo_cuenta', 'proveedor')->count())->toBe(1)
        ->and(DB::table('sec_personas')->count())->toBe(1);
});

it('el 409 no revela datos de nadie ni cuál de los dos datos chocó', function (string $quien) {
    $this->postJson('/api/clientes/registro', datosDeCliente('rosa.uno', '+59170000001'))->assertCreated();

    $cambios = $quien === 'telefono'
        ? ['documento_numero' => '9999999']
        : [];
    $telefono = $quien === 'telefono' ? '+59170000001' : '+59170000077';

    $respuesta = $this->postJson('/api/clientes/registro', datosDeCliente('otra.cuenta', $telefono, $cambios))->assertStatus(409);
    $cuerpo = $respuesta->json();

    expect(array_keys($cuerpo))->toEqualCanonicalizing(['message', 'codigo', 'tipo_cuenta'])
        ->and(json_encode($cuerpo))->not->toContain('Rosa')->not->toContain('7845123')->not->toContain('70000001')->not->toContain('rosa.uno');
})->with(['teléfono', 'documento']);

it('el mismo mensaje responde la persona repetida y el teléfono repetido', function () {
    $this->postJson('/api/clientes/registro', datosDeCliente('rosa.uno', '+59170000001'))->assertCreated();

    $porTelefono = $this->postJson('/api/clientes/registro', datosDeCliente('a.b', '+59170000001', ['documento_numero' => '1111111']));
    $porDocumento = $this->postJson('/api/clientes/registro', datosDeCliente('c.d', '+59170000055'));

    expect($porTelefono->json())->toBe($porDocumento->json());
});

it('mientras la app del cliente no envía documento, la cuenta se crea sin persona (transitorio)', function () {
    $datos = datosDeCliente('rosa.uno', '+59170000001');
    unset($datos['documento_tipo'], $datos['documento_numero'], $datos['nombres'], $datos['apellidos'], $datos['fecha_nacimiento']);

    $this->postJson('/api/clientes/registro', $datos)->assertCreated();

    expect(DB::table('sec_user')->value('persona_id'))->toBeNull()
        ->and(DB::table('sec_personas')->count())->toBe(0);
});

it('con documento, el nombre y los apellidos son obligatorios', function () {
    $datos = datosDeCliente('rosa.uno', '+59170000001');
    unset($datos['nombres'], $datos['apellidos']);

    $this->postJson('/api/clientes/registro', $datos)->assertStatus(422)->assertJsonValidationErrors(['nombres', 'apellidos']);
});

it('el índice único de la base es la última defensa: la misma persona no tiene dos cuentas del mismo tipo', function () {
    $cuentas = app(RegistroCuentas::class);
    $persona = new DatosDePersona('ci', '123456', 'Luis', 'Antelo');

    $cuentas->crear('Luis', 'luis.uno', null, 'Clave1234', TipoCuenta::Cliente, ['cliente'], '+59170000001', $persona);
    $personaId = (int) DB::table('sec_personas')->value('id');

    // Saltando las comprobaciones del caso de uso, el índice sigue frenando la segunda.
    expect(fn () => DB::table('sec_user')->insert([
        'name' => 'Otro', 'username' => 'luis.dos', 'password' => 'x', 'type' => 'interno', 'tipo_cuenta' => 'cliente',
        'persona_id' => $personaId, 'state' => true, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // La del otro tipo sí entra.
    DB::table('sec_user')->insert([
        'name' => 'Luis', 'username' => 'luis.piloto', 'password' => 'x', 'type' => 'interno', 'tipo_cuenta' => 'proveedor',
        'persona_id' => $personaId, 'state' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(DB::table('sec_user')->where('persona_id', $personaId)->count())->toBe(2);
});

it('una cuenta dada de baja libera su lugar en el índice pero la persona sigue siendo la misma', function () {
    $cuentas = app(RegistroCuentas::class);
    $persona = new DatosDePersona('ci', '123456', 'Luis', 'Antelo');

    $uno = $cuentas->crear('Luis', 'luis.uno', null, 'Clave1234', TipoCuenta::Cliente, ['cliente'], '+59170000001', $persona);
    DB::table('sec_user')->where('id', $uno)->update(['deleted_at' => now()]);

    $dos = $cuentas->crear('Luis', 'luis.dos', null, 'Clave1234', TipoCuenta::Cliente, ['cliente'], '+59170000001', $persona);

    expect($dos)->not->toBe($uno)
        ->and(DB::table('sec_personas')->count())->toBe(1);
});

it('el número de documento se normaliza igual en un solo lugar', function () {
    expect(TipoDocumento::normalizarNumero('  7.845.123 sc '))->toBe('7845123SC')
        ->and(TipoDocumento::normalizarNumero('ab-12'))->toBe('AB-12')
        ->and(TipoDocumento::normalizarNombre('  Rosa   MARÍA '))->toBe('rosa maría');
});

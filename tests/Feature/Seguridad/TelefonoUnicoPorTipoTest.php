<?php

use App\Dominios\Proveedores\Contratos\Eventos\ProveedorRegistrado;
use App\Dominios\Seguridad\Dominio\NumeroDeTelefono;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Soporte\Esquema;

/*
 * Teléfono y correo únicos POR TIPO de cuenta (ADR 0046, puntos 24 a 26 y 29; plan de cuentas,
 * fase A3). El mismo teléfono puede estar en una cuenta de cliente y en una de proveedor, nunca
 * en dos del mismo tipo; los formatos equivalentes del mismo número colisionan; el `409` tiene un
 * tope por IP, teléfono y documento; el login acepta el teléfono. El código de verificación por
 * SMS o WhatsApp NO se construye acá (no hay proveedor): el teléfono no se presenta como verificado.
 */

beforeEach(function () {
    // Acá se prueba el tope del `409`, no el límite general del registro (que tiene su propia prueba).
    RateLimiter::for('registro-publico', fn () => Limit::none());
    RateLimiter::clear('login-campo');
    Cache::flush();
    Event::fake([ProveedorRegistrado::class]);

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
        'agrega_motivo_estado_a_sol_clientes',
        'crea_sol_cliente_contactos',
        'create_prv_proveedores_table',
        'create_prv_pilotos_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
    ]);

    foreach (['sec_token_dispositivo', 'prv_pilotos', 'prv_proveedores', 'sol_cliente_contactos', 'sol_clientes', 'sec_user_role', 'sec_user', 'sec_personas', 'sec_role'] as $tabla) {
        DB::table($tabla)->delete();
    }

    foreach (['cliente', 'proveedor', 'piloto'] as $rol) {
        DB::table('sec_role')->insert(['name' => $rol, 'description' => $rol, 'state' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
});

/** @param  array<string, mixed>  $cambios */
function clienteSimple(string $username, string $telefono, array $cambios = []): array
{
    return ['tipo' => 'persona', 'nombre' => 'Luis Antelo', 'username' => $username, 'password' => 'Clave1234', 'telefono' => $telefono, ...$cambios];
}

/** @param  array<string, mixed>  $cambios */
function proveedorSimple(string $username, string $telefono, array $cambios = []): array
{
    static $secuencia = 0;
    $secuencia++;

    return [
        'tipo' => 'independiente', 'nombre' => 'Luis Antelo', 'username' => $username, 'password' => 'Clave1234', 'telefono' => $telefono,
        'razon_social' => 'Luis Antelo', 'documento_fiscal' => "NIT{$secuencia}", 'nombres' => 'Luis', 'apellidos' => 'Antelo',
        'documento_identidad' => "DOC{$secuencia}", 'fecha_nacimiento' => '1985-03-02',
        'licencia_numero' => 'L-100', 'licencia_vence' => now()->addYear()->toDateString(), 'categoria_habilitacion' => 'A',
        ...$cambios,
    ];
}

// --- Formato: E.164, Bolivia por defecto ------------------------------------------------------

it('normaliza el teléfono a E.164, con Bolivia por defecto', function (string $crudo, ?string $esperado) {
    expect(NumeroDeTelefono::normalizar($crudo))->toBe($esperado);
})->with([
    'celular nacional' => ['70012345', '+59170012345'],
    'con espacios y guion' => ['700 123-45', '+59170012345'],
    'con el prefijo y el más' => ['+591 70012345', '+59170012345'],
    'con 00' => ['00591 70012345', '+59170012345'],
    'con 591 sin más' => ['59170012345', '+59170012345'],
    'con paréntesis' => ['(591) 700-12345', '+59170012345'],
    'otro país' => ['+54 9 11 5555 1234', '+5491155551234'],
    'otro país con 00' => ['0054 9 11 5555 1234', '+5491155551234'],
    'vacío' => ['', null],
    'letras' => ['70012345abc', null],
    'muy corto' => ['7001', null],
    'nacional que no es celular' => ['20012345', null],
    'Bolivia con un dígito de más' => ['+591 700123456', null],
    'sin dígitos' => ['+++', null],
]);

it('un teléfono mal escrito se rechaza con un mensaje claro', function () {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.uno', '12'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['telefono']);
});

// --- Unicidad por tipo ---------------------------------------------------------------------

it('dos clientes con el mismo teléfono: el segundo recibe 409 cuenta_ya_registrada', function () {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.uno', '70012345'))->assertCreated();

    $this->postJson('/api/clientes/registro', clienteSimple('luis.dos', '70012345'))
        ->assertStatus(409)
        ->assertJsonPath('codigo', 'cuenta_ya_registrada')
        ->assertJsonPath('tipo_cuenta', 'cliente');

    expect(DB::table('sec_user')->count())->toBe(1);
});

it('un cliente y un proveedor pueden compartir el teléfono', function () {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.cliente', '70012345'))->assertCreated();
    $this->postJson('/api/proveedores/registro', proveedorSimple('luis.piloto', '70012345'))->assertCreated();

    expect(DB::table('sec_user')->where('telefono', '+59170012345')->pluck('tipo_cuenta')->sort()->values()->all())->toBe(['cliente', 'proveedor']);
});

it('dos proveedores con el mismo teléfono: el segundo recibe 409', function () {
    $this->postJson('/api/proveedores/registro', proveedorSimple('luis.uno', '70012345'))->assertCreated();

    $this->postJson('/api/proveedores/registro', proveedorSimple('luis.dos', '70012345'))
        ->assertStatus(409)
        ->assertJsonPath('tipo_cuenta', 'proveedor');
});

it('los formatos equivalentes del mismo número colisionan', function (string $variante) {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.uno', '70012345'))->assertCreated();

    $this->postJson('/api/clientes/registro', clienteSimple('luis.dos', $variante))->assertStatus(409);

    expect(DB::table('sec_user')->count())->toBe(1);
})->with(['+591 70012345', '591-700-12345', '00591 700 12345', '(591) 70012345']);

it('el teléfono queda guardado en E.164 y no se presenta como verificado', function () {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.uno', '700 123-45'))->assertCreated();

    $cuenta = DB::table('sec_user')->first();

    expect($cuenta->telefono)->toBe('+59170012345')
        ->and($cuenta->telefono_verificado_en)->toBeNull()
        ->and($cuenta->telefono_verificado_por)->toBeNull();
});

it('una cuenta dada de baja libera su teléfono', function () {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.uno', '70012345'))->assertCreated();
    DB::table('sec_user')->update(['deleted_at' => now()]);

    $this->postJson('/api/clientes/registro', clienteSimple('luis.dos', '70012345'))->assertCreated();
});

it('el índice único de la base frena el mismo teléfono en dos cuentas del mismo tipo', function () {
    $fila = fn (string $username, string $tipo) => [
        'name' => $username, 'username' => $username, 'password' => 'x', 'type' => 'interno', 'tipo_cuenta' => $tipo,
        'telefono' => '+59170012345', 'state' => true, 'created_at' => now(), 'updated_at' => now(),
    ];

    DB::table('sec_user')->insert($fila('uno', 'cliente'));
    DB::table('sec_user')->insert($fila('dos', 'proveedor'));

    expect(fn () => DB::table('sec_user')->insert($fila('tres', 'cliente')))->toThrow(QueryException::class);
});

// --- Correo de respaldo por tipo --------------------------------------------------------------

it('el correo es único por tipo: se comparte entre una cuenta de cliente y una de proveedor', function () {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.uno', '70012345', ['email' => 'luis@correo.test']))->assertCreated();

    $this->postJson('/api/clientes/registro', clienteSimple('luis.dos', '70012346', ['email' => 'luis@correo.test']))
        ->assertStatus(422)->assertJsonValidationErrors(['email']);

    $this->postJson('/api/proveedores/registro', proveedorSimple('luis.piloto', '70012347', ['email' => 'luis@correo.test']))->assertCreated();
});

// --- Tope del 409 (sondeo) --------------------------------------------------------------------

it('tras cinco 409 por hora desde una IP, el registro responde 429 aunque el dato sea nuevo', function () {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.uno', '70012345'))->assertCreated();

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/clientes/registro', clienteSimple("sonda.{$i}", '70012345'))->assertStatus(409);
    }

    // Un teléfono que no existe: igual 429, así el sondeo no distingue nada.
    $this->postJson('/api/clientes/registro', clienteSimple('sonda.9', '70099999'))->assertStatus(429);
});

it('un teléfono consultado de más es 429 aunque las consultas vengan de IP distintas', function () {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.uno', '70012345'))->assertCreated();

    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$i}"])->postJson('/api/clientes/registro', clienteSimple("sonda.{$i}", '70012345'))->assertStatus(409);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])->postJson('/api/clientes/registro', clienteSimple('sonda.9', '70012345'))->assertStatus(429);

    // Otro teléfono, desde otra IP: sigue funcionando.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.98'])->postJson('/api/clientes/registro', clienteSimple('luis.libre', '70055555'))->assertCreated();
});

it('un registro que sale bien o falla por validación no consume el tope', function () {
    foreach (range(1, 4) as $i) {
        $this->postJson('/api/clientes/registro', clienteSimple("ok.{$i}", '7001234'.$i))->assertCreated();
    }

    $this->postJson('/api/clientes/registro', ['tipo' => 'persona'])->assertStatus(422);
});

// --- Login por teléfono -------------------------------------------------------------------------

it('el login acepta el teléfono, en cualquier formato', function (string $formato) {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.uno', '70012345'))->assertCreated();

    $this->postJson('/api/auth/token', ['username' => $formato, 'password' => 'Clave1234', 'uuid_dispositivo' => '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31', 'app' => 'cliente'])
        ->assertCreated();
})->with(['70012345', '+591 700-12345', '00591 70012345']);

it('con el mismo teléfono en un cliente y un proveedor, la app decide a cuál entra', function () {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.cliente', '70012345'))->assertCreated();
    $this->postJson('/api/proveedores/registro', proveedorSimple('luis.piloto', '70012345'))->assertCreated();
    // El proveedor independiente lleva dos roles de app (transitorio hasta A5): se elige `proveedor`.
    $rolProveedor = (int) DB::table('sec_role')->where('name', 'proveedor')->value('id');
    $entrar = fn (?string $app) => $this->postJson('/api/auth/token', array_filter([
        'username' => '70012345', 'password' => 'Clave1234', 'uuid_dispositivo' => '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31', 'app' => $app,
        'role_id' => $app === 'piloto' ? $rolProveedor : null,
    ]));

    expect($entrar('cliente')->assertCreated()->json('rol.name'))->toBe('cliente')
        ->and($entrar('piloto')->assertCreated()->json('rol.name'))->toBe('proveedor');

    // Sin `app` no se adivina de cuál es: mismo rechazo que una credencial inválida.
    $entrar(null)->assertStatus(422);
});

it('el login por teléfono comparte el límite con el mismo usuario en otro formato', function () {
    $this->postJson('/api/clientes/registro', clienteSimple('luis.uno', '70012345'))->assertCreated();

    foreach (['70012345', '+591 70012345', '591-700-12345', '00591 70012345', '(591) 70012345', '700 123 45'] as $formato) {
        $this->postJson('/api/auth/token', ['username' => $formato, 'password' => 'mal', 'uuid_dispositivo' => '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31', 'app' => 'cliente'])->assertStatus(422);
    }

    $this->postJson('/api/auth/token', ['username' => '+59170012345', 'password' => 'Clave1234', 'uuid_dispositivo' => '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31', 'app' => 'cliente'])->assertStatus(429);
});

// --- La migración ---------------------------------------------------------------------------------

it('la migración copia los teléfonos de las fichas a la cuenta, normalizados, y conserva el vínculo con el personal anterior', function () {
    $migracion = require database_path('migrations/2026_12_12_100000_crea_sec_personas_y_telefono_de_cuenta.php');
    $migracion->down();

    $ahora = now();
    $cuenta = fn (string $username, string $tipo, ?int $persona = null) => DB::table('sec_user')->insertGetId([
        'name' => $username, 'username' => $username, 'password' => 'x', 'type' => 'interno', 'tipo_cuenta' => $tipo,
        'persona_id' => $persona, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    $clienteUno = $cuenta('c.uno', 'cliente');
    $clienteDos = $cuenta('c.dos', 'cliente');
    $proveedor = $cuenta('p.uno', 'proveedor');
    $panel = $cuenta('admin', 'panel', 77);

    foreach ([[$clienteUno, '700 123-45'], [$clienteDos, '+591 70012345'], [$proveedor, '70012345']] as [$usuario, $telefono]) {
        if ($usuario === $proveedor) {
            continue;
        }

        DB::table('sol_clientes')->insert([
            'usuario_id' => $usuario, 'tipo' => 'persona', 'razon_social' => 'x'.$usuario, 'telefono' => $telefono,
            'estado' => 'activo', 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
    }

    DB::table('prv_proveedores')->insert([
        'usuario_titular_id' => $proveedor, 'tipo' => 'independiente', 'razon_social' => 'P', 'telefono' => '70012345',
        'estado' => 'pendiente', 'radio_operacion_km' => '50', 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    $migracion->up();

    $telefono = fn (int $id) => DB::table('sec_user')->where('id', $id)->value('telefono');

    // Dos clientes con el mismo teléfono: gana el primero; el otro queda sin él en vez de romper el índice.
    expect($telefono($clienteUno))->toBe('+59170012345')
        ->and($telefono($clienteDos))->toBeNull()
        // El proveedor comparte el teléfono con un cliente: otro tipo, entra.
        ->and($telefono($proveedor))->toBe('+59170012345')
        // El vínculo viejo del personal pasa a `persona_legada_id` y `persona_id` queda libre para `sec_personas`.
        ->and(DB::table('sec_user')->where('id', $panel)->value('persona_id'))->toBeNull()
        ->and((int) DB::table('sec_user')->where('id', $panel)->value('persona_legada_id'))->toBe(77);

    $migracion->down();

    expect((int) DB::table('sec_user')->where('id', $panel)->value('persona_id'))->toBe(77);
});

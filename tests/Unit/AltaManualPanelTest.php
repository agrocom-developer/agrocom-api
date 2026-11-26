<?php

use App\Dominios\Compartido\Aplicacion\SeparacionDeFunciones;
use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\AprobacionPropia;
use App\Dominios\Proveedores\Aplicacion\ResolverAprobacion;
use App\Dominios\Proveedores\Contratos\Eventos\ProveedorRegistrado;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * Alta a mano en el panel (clientes, proveedores, pilotos y drones): reutiliza los casos de uso del
 * registro de la app, deja al proveedor, piloto y dron `pendientes`, guarda a quien los cargó y esa
 * persona no puede aprobarlos. Contra el ESQUEMA REAL (SQLite en memoria); la autorización del
 * panel se reemplaza por una que concede los permisos de la prueba.
 */

uses(TestCase::class);

const ADMIN_OFICINA = 900;

/** @var list<string> permisos que concede la autorización de la prueba */
function permisosDeLaPrueba(?array $nuevos = null): array
{
    static $permisos = [];

    if ($nuevos !== null) {
        $permisos = $nuevos;
    }

    return $permisos;
}

beforeEach(function () {
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'agrega_tipo_cuenta_a_sec_user',
        'crea_sec_personas_y_telefono_de_cuenta',
        'create_sec_user_preferencia_table',
        'create_sec_role_table',
        'create_sec_user_role_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'agrega_reputacion_a_sol_clientes',
        'lleva_sol_clientes_al_ciclo_de_aprobacion',
        'crea_sol_cliente_contactos',
        'create_prv_proveedores_table',
        'create_prv_pilotos_table',
        'create_flo_drones_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
    ]);

    foreach (['flo_drones', 'prv_pilotos', 'prv_proveedores', 'sol_cliente_contactos', 'sol_clientes', 'sec_user_role', 'sec_user', 'sec_role'] as $tabla) {
        DB::table($tabla)->delete();
    }
    foreach (['cliente', 'proveedor', 'piloto'] as $rol) {
        DB::table('sec_role')->insert(['name' => $rol, 'description' => $rol, 'state' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    Event::fake([ProveedorRegistrado::class]);

    app()->instance(AutorizacionPanelWeb::class, new class implements AutorizacionPanelWeb
    {
        public function tienePermiso(Request $request, string $codigoPermiso): bool
        {
            return in_array($codigoPermiso, permisosDeLaPrueba(), true);
        }

        public function cascara(Request $request): array
        {
            return [];
        }

        public function personaId(Request $request): ?int
        {
            return null;
        }

        public function primerDestinoVisible(Request $request): string
        {
            return '/panel';
        }
    });

    permisosDeLaPrueba(['solicitudes.cliente.crear', 'proveedores.proveedor.crear', 'proveedores.piloto.crear', 'flota.dron.crear']);

    $this->withoutMiddleware();
    $this->actingAs((new SecUser)->forceFill(['id' => ADMIN_OFICINA, 'name' => 'Oficina']), 'interno');
});

/** @return array<string, mixed> */
function clienteDelPanel(array $cambios = []): array
{
    return [
        'tipo' => 'persona', 'nombre' => 'Luis Antelo', 'username' => 'luis.antelo', 'password' => 'Clave1234',
        'telefono' => '72288410', ...$cambios,
    ];
}

/** @return array<string, mixed> */
function proveedorDelPanel(array $cambios = []): array
{
    return [
        'tipo' => 'independiente', 'nombre' => 'Rodrigo Suárez', 'username' => 'rodrigo.suarez', 'password' => 'Clave1234',
        'telefono' => '70011223', 'razon_social' => 'Rodrigo Suárez', 'documento_fiscal' => '5566',
        'nombres' => 'Rodrigo', 'apellidos' => 'Suárez', 'documento_identidad' => '123456', 'fecha_nacimiento' => '1990-05-01',
        'licencia_numero' => 'L-100', 'licencia_vence' => now()->addYear()->toDateString(), 'categoria_habilitacion' => 'A',
        ...$cambios,
    ];
}

test('registrar una empresa guarda sus contactos, el principal elegido y a quien la cargó', function () {
    $respuesta = $this->post('/panel/clientes', clienteDelPanel([
        'tipo' => 'empresa', 'razon_social' => 'San Rafael SRL', 'documento_fiscal' => '1029',
        'contactos' => [
            ['nombre' => 'Rosa Condori', 'telefono' => '76543210', 'cargo' => 'Gerente'],
            ['nombre' => '', 'telefono' => '', 'cargo' => ''],
            ['nombre' => 'Mario Suárez', 'telefono' => '71234567', 'cargo' => ''],
        ],
        'contacto_principal' => '2',
    ]));

    $cliente = DB::table('sol_clientes')->first();
    $respuesta->assertRedirect(route('panel.clientes.show', $cliente->id));

    expect($cliente->tipo)->toBe('empresa')
        ->and($cliente->estado)->toBe('pendiente')
        ->and((int) $cliente->created_by)->toBe(ADMIN_OFICINA)
        ->and(DB::table('sol_cliente_contactos')->count())->toBe(2)
        ->and(DB::table('sol_cliente_contactos')->where('es_principal', true)->value('nombre'))->toBe('Mario Suárez');
});

test('una empresa sin contactos se rechaza y no crea nada', function () {
    $this->postJson('/panel/clientes', clienteDelPanel(['tipo' => 'empresa', 'razon_social' => 'San Rafael SRL', 'documento_fiscal' => '1029']))
        ->assertJsonValidationErrors('contactos');

    expect(DB::table('sol_clientes')->count())->toBe(0)->and(DB::table('sec_user')->count())->toBe(0);
});

test('un cliente persona se registra sin contactos y comparte las reglas de cuenta del registro público', function () {
    $this->post('/panel/clientes', clienteDelPanel())->assertRedirect();
    expect(DB::table('sol_clientes')->count())->toBe(1)->and(DB::table('sol_cliente_contactos')->count())->toBe(0);

    $this->postJson('/panel/clientes', clienteDelPanel(['username' => 'otro', 'password' => 'corta']))->assertJsonValidationErrors('password');
});

test('sin el permiso de crear, el alta a mano responde 403', function () {
    permisosDeLaPrueba(['solicitudes.cliente.ver']);

    $this->post('/panel/clientes', clienteDelPanel())->assertForbidden();
    $this->post('/panel/proveedores', proveedorDelPanel())->assertForbidden();
    $this->post('/panel/pilotos', [])->assertForbidden();
    $this->post('/panel/drones-flota', [])->assertForbidden();

    expect(DB::table('sol_clientes')->count())->toBe(0);
});

test('un proveedor independiente queda pendiente con su piloto, igual que desde la app, y quien lo cargó no lo aprueba', function () {
    $this->post('/panel/proveedores', proveedorDelPanel())->assertRedirect();

    $proveedor = DB::table('prv_proveedores')->first();
    $piloto = DB::table('prv_pilotos')->first();

    expect($proveedor->estado)->toBe('pendiente')
        ->and((int) $proveedor->created_by)->toBe(ADMIN_OFICINA)
        ->and($piloto->estado)->toBe('pendiente')
        ->and((int) $piloto->created_by)->toBe(ADMIN_OFICINA)
        ->and(DB::table('sec_user_role')->count())->toBe(2);

    expect(fn () => app(ResolverAprobacion::class)->ejecutar(TipoSujeto::Proveedor, $proveedor->id, AccionAprobacion::Aprobar, ADMIN_OFICINA))
        ->toThrow(AprobacionPropia::class);
    expect(DB::table('prv_proveedores')->value('estado'))->toBe('pendiente');
});

test('la separación de funciones solo frena a quien registró y se puede apagar', function () {
    expect(fn () => SeparacionDeFunciones::exigir(AccionAprobacion::Aprobar, 5, 5))->toThrow(AprobacionPropia::class);

    SeparacionDeFunciones::exigir(AccionAprobacion::Aprobar, 5, 6);
    SeparacionDeFunciones::exigir(AccionAprobacion::Rechazar, 5, 5);
    SeparacionDeFunciones::exigir(AccionAprobacion::Aprobar, null, 5);

    config(['proveedores.separar_funciones' => false]);
    SeparacionDeFunciones::exigir(AccionAprobacion::Aprobar, 5, 5);
    expect(true)->toBeTrue();
});

test('un proveedor independiente exige su licencia, como en el registro de la app', function () {
    $this->postJson('/panel/proveedores', proveedorDelPanel(['licencia_numero' => '']))->assertJsonValidationErrors('licencia_numero');

    expect(DB::table('prv_proveedores')->count())->toBe(0);
});

test('el piloto se suma a un proveedor tercero y no a un independiente', function () {
    $this->post('/panel/proveedores', proveedorDelPanel(['tipo' => 'tercero', 'username' => 'tercero', 'documento_fiscal' => '7788', 'licencia_numero' => '']))->assertRedirect();
    $this->post('/panel/proveedores', proveedorDelPanel(['username' => 'indep', 'documento_fiscal' => '9900']))->assertRedirect();
    $tercero = DB::table('prv_proveedores')->where('tipo', 'tercero')->value('id');
    $independiente = DB::table('prv_proveedores')->where('tipo', 'independiente')->value('id');

    $piloto = ['nombre' => 'Carla Rojas', 'username' => 'carla', 'password' => 'Clave1234', 'licencia_numero' => 'L-2', 'licencia_vence' => now()->addYear()->toDateString(), 'categoria_habilitacion' => 'A'];

    $this->postJson('/panel/pilotos', [...$piloto, 'proveedor_id' => $independiente])->assertJsonValidationErrors('proveedor_id');
    $this->post('/panel/pilotos', [...$piloto, 'proveedor_id' => $tercero])->assertRedirect();

    $registrado = DB::table('prv_pilotos')->where('proveedor_id', $tercero)->first();
    expect($registrado->estado)->toBe('pendiente')->and((int) $registrado->created_by)->toBe(ADMIN_OFICINA);
});

test('el dron se registra pendiente para un proveedor y no admite una serie repetida', function () {
    $this->post('/panel/proveedores', proveedorDelPanel())->assertRedirect();
    $proveedorId = DB::table('prv_proveedores')->value('id');
    $dron = ['proveedor_id' => $proveedorId, 'marca' => 'DJI', 'modelo' => 'T40', 'numero_serie' => 'SN-001', 'capacidad_tanque_l' => '40', 'ancho_pasada_m' => '9'];

    $this->post('/panel/drones-flota', $dron)->assertRedirect();
    $fila = DB::table('flo_drones')->first();
    expect($fila->estado)->toBe('pendiente')
        ->and((int) $fila->created_by)->toBe(ADMIN_OFICINA)
        ->and((int) $fila->dueno_proveedor_id)->toBe((int) $proveedorId);

    $this->post('/panel/drones-flota', $dron)->assertRedirect();
    expect(DB::table('flo_drones')->count())->toBe(1);
});

<?php

use App\Dominios\Seguridad\Contratos\RegistroCuentas;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Solicitudes\Aplicacion\CrearSolicitud;
use App\Dominios\Solicitudes\Aplicacion\GestionarContactos;
use App\Dominios\Solicitudes\Aplicacion\RegistrarCliente;
use App\Dominios\Solicitudes\Contratos\LecturaServiciosAsignados;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\ClienteContacto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * Contactos de referencia del cliente (especificación §4.1): una empresa siempre tiene al menos
 * uno vigente, hay un solo principal, la baja es lógica, un contacto ajeno no existe (invariante 5)
 * y el proveedor recibe a la persona —no a la empresa— al adjudicar. Contra el ESQUEMA REAL
 * (las migraciones, en SQLite en memoria) y los casos de uso reales.
 */

uses(TestCase::class);

function contactosEsquema(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_terreno_fotos_table',
        'create_sol_precios_referencia_table',
        'create_sol_solicitudes_servicio_table',
        'create_sol_solicitud_historial_table',
        'agrega_reputacion_a_sol_clientes',
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'crea_sol_cliente_contactos',
    ]);
}

/** Un cliente `$tipo` con su terreno. */
function clienteDeTipo(string $tipo, int $usuarioId): Cliente
{
    $id = DB::table('sol_clientes')->insertGetId([
        'usuario_id' => $usuarioId,
        'tipo' => $tipo,
        'razon_social' => $tipo === 'empresa' ? 'Agropecuaria San Rafael SRL' : "Persona {$usuarioId}",
        'documento_fiscal' => $tipo === 'empresa' ? "NIT-{$usuarioId}" : null,
        'telefono' => '70012345',
        'estado' => 'activo',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sol_terrenos')->insert([
        'cliente_id' => $id,
        'nombre' => "Terreno {$id}",
        'hectareas' => '100.0000',
        'acceso_dificil' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Cliente::query()->findOrFail($id);
}

/** @return array<string, mixed> */
function solicitudDe(Cliente $cliente, array $cambios = []): array
{
    return [
        'uuid_cliente' => (string) Str::uuid(),
        'terreno_id' => (int) DB::table('sol_terrenos')->where('cliente_id', $cliente->id)->value('id'),
        'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '80',
        'producto_indicado' => 'Glifosato 480 SL, 2 L/ha',
        'ventana_desde' => '2026-12-05',
        'ventana_hasta' => '2026-12-15',
        'precio_ha_propuesto' => '125',
        ...$cambios,
    ];
}

/** @return array{nombre: string, telefono: string} */
function datosContacto(string $nombre = 'Rosa Condori', string $telefono = '76543210'): array
{
    return ['nombre' => $nombre, 'telefono' => $telefono];
}

beforeEach(function () {
    contactosEsquema();
    foreach (['sol_solicitud_historial', 'sol_solicitudes_servicio', 'sol_cliente_contactos', 'sol_terrenos', 'sol_clientes'] as $tabla) {
        DB::table($tabla)->delete();
    }
    Carbon::setTestNow('2026-12-02 10:00:00');
    Event::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('el primer contacto queda como principal y marcar otro releva al anterior', function () {
    $cliente = clienteDeTipo('empresa', 201);
    $gestion = app(GestionarContactos::class);

    $primero = $gestion->agregar($cliente, datosContacto('Rosa'));
    $segundo = $gestion->agregar($cliente, datosContacto('Beto') + ['es_principal' => true]);

    expect($primero->es_principal)->toBeTrue()
        ->and($segundo->es_principal)->toBeTrue()
        ->and($primero->refresh()->es_principal)->toBeFalse()
        ->and($gestion->listar($cliente)->pluck('nombre')->all())->toBe(['Beto', 'Rosa']);
});

test('un contacto que no se marca principal no releva al principal', function () {
    $cliente = clienteDeTipo('empresa', 202);
    $gestion = app(GestionarContactos::class);
    $principal = $gestion->agregar($cliente, datosContacto('Rosa'));

    $otro = $gestion->agregar($cliente, datosContacto('Beto') + ['es_principal' => false]);

    expect($otro->es_principal)->toBeFalse()
        ->and($principal->refresh()->es_principal)->toBeTrue();
});

test('actualizar corrige los datos y desmarcar al principal no deja la empresa sin principal', function () {
    $cliente = clienteDeTipo('empresa', 203);
    $gestion = app(GestionarContactos::class);
    $principal = $gestion->agregar($cliente, datosContacto('Rosa'));

    $editado = $gestion->actualizar($cliente, $principal->id, ['nombre' => ' Rosa C. ', 'cargo' => 'Encargada', 'es_principal' => false]);

    expect($editado->nombre)->toBe('Rosa C.')
        ->and($editado->cargo)->toBe('Encargada')
        ->and($editado->es_principal)->toBeTrue();
});

test('una empresa no puede dar de baja a su último contacto vigente', function () {
    $cliente = clienteDeTipo('empresa', 204);
    $gestion = app(GestionarContactos::class);
    $unico = $gestion->agregar($cliente, datosContacto());

    expect(fn () => $gestion->darDeBaja($cliente, $unico->id))->toThrow(SolicitudInvalida::class)
        ->and($gestion->listar($cliente))->toHaveCount(1);
});

test('dar de baja al principal es lógico y lo releva el contacto más antiguo que queda', function () {
    $cliente = clienteDeTipo('empresa', 205);
    $gestion = app(GestionarContactos::class);
    $principal = $gestion->agregar($cliente, datosContacto('Rosa'));
    $segundo = $gestion->agregar($cliente, datosContacto('Beto'));
    $gestion->agregar($cliente, datosContacto('Carla'));

    $gestion->darDeBaja($cliente, $principal->id);

    expect(ClienteContacto::withTrashed()->find($principal->id)->trashed())->toBeTrue()
        ->and($gestion->listar($cliente)->pluck('nombre')->all())->toBe(['Beto', 'Carla'])
        ->and($segundo->refresh()->es_principal)->toBeTrue();
});

test('una persona puede quedarse sin contactos', function () {
    $cliente = clienteDeTipo('persona', 206);
    $gestion = app(GestionarContactos::class);
    $unico = $gestion->agregar($cliente, datosContacto());

    $gestion->darDeBaja($cliente, $unico->id);

    expect($gestion->listar($cliente))->toHaveCount(0);
});

test('un contacto de otro cliente responde como si no existiera', function () {
    $ana = clienteDeTipo('empresa', 207);
    $beto = clienteDeTipo('empresa', 208);
    $gestion = app(GestionarContactos::class);
    $deBeto = $gestion->agregar($beto, datosContacto('Contacto de Beto'));
    $gestion->agregar($ana, datosContacto('Contacto de Ana'));

    foreach ([
        fn () => $gestion->obtener($ana, $deBeto->id),
        fn () => $gestion->actualizar($ana, $deBeto->id, ['nombre' => 'Hackeado']),
        fn () => $gestion->darDeBaja($ana, $deBeto->id),
    ] as $intento) {
        expect($intento)->toThrow(SolicitudInvalida::class);
    }

    expect($deBeto->refresh()->nombre)->toBe('Contacto de Beto')
        ->and($gestion->listar($ana)->pluck('nombre')->all())->toBe(['Contacto de Ana']);
});

test('registrar contactos iniciales marca un solo principal y una empresa sin ninguno se rechaza', function () {
    $empresa = clienteDeTipo('empresa', 209);
    $gestion = app(GestionarContactos::class);

    $gestion->registrarIniciales($empresa, [datosContacto('Rosa'), datosContacto('Beto') + ['es_principal' => true], datosContacto('Carla')]);

    expect($gestion->listar($empresa)->pluck('nombre')->all())->toBe(['Beto', 'Rosa', 'Carla'])
        ->and($gestion->listar($empresa)->where('es_principal', true))->toHaveCount(1);

    $otra = clienteDeTipo('empresa', 210);
    expect(fn () => $gestion->registrarIniciales($otra, []))->toThrow(SolicitudInvalida::class);
    $persona = clienteDeTipo('persona', 211);
    $gestion->registrarIniciales($persona, []);
    expect($gestion->listar($persona))->toHaveCount(0);
});

test('la solicitud de una empresa usa el contacto elegido o, sin elegir, el principal', function () {
    $cliente = clienteDeTipo('empresa', 212);
    $gestion = app(GestionarContactos::class);
    $principal = $gestion->agregar($cliente, datosContacto('Rosa'));
    $otro = $gestion->agregar($cliente, datosContacto('Beto'));

    [$sinElegir] = app(CrearSolicitud::class)->ejecutar(212, solicitudDe($cliente));
    [$elegido] = app(CrearSolicitud::class)->ejecutar(212, solicitudDe($cliente, ['contacto_id' => $otro->id]));

    expect($sinElegir->contacto_id)->toBe($principal->id)
        ->and($elegido->contacto_id)->toBe($otro->id);
});

test('una empresa sin contactos vigentes no puede crear solicitudes', function () {
    $cliente = clienteDeTipo('empresa', 213);

    expect(fn () => app(CrearSolicitud::class)->ejecutar(213, solicitudDe($cliente)))->toThrow(SolicitudInvalida::class)
        ->and(DB::table('sol_solicitudes_servicio')->count())->toBe(0);
});

test('el contacto de una solicitud debe ser vigente y del propio cliente', function () {
    $ana = clienteDeTipo('empresa', 214);
    $beto = clienteDeTipo('persona', 215);
    $gestion = app(GestionarContactos::class);
    $gestion->agregar($ana, datosContacto('Rosa'));
    $ajeno = $gestion->agregar($beto, datosContacto('Contacto de Beto'));
    $dadoDeBaja = $gestion->agregar($ana, datosContacto('Carla'));
    $gestion->darDeBaja($ana, $dadoDeBaja->id);

    foreach ([$ajeno->id, $dadoDeBaja->id, 99999] as $contactoId) {
        expect(fn () => app(CrearSolicitud::class)->ejecutar(214, solicitudDe($ana, ['contacto_id' => $contactoId])))
            ->toThrow(SolicitudInvalida::class);
    }

    expect(DB::table('sol_solicitudes_servicio')->count())->toBe(0);
});

test('un cliente persona puede crear solicitudes sin contacto', function () {
    $cliente = clienteDeTipo('persona', 216);

    [$solicitud] = app(CrearSolicitud::class)->ejecutar(216, solicitudDe($cliente));

    expect($solicitud->contacto_id)->toBeNull();
});

test('el proveedor recibe a la persona de contacto tras adjudicar y, sin contacto elegido, al cliente', function () {
    $empresa = clienteDeTipo('empresa', 217);
    $persona = clienteDeTipo('persona', 218);
    $gestion = app(GestionarContactos::class);
    $rosa = $gestion->agregar($empresa, datosContacto('Rosa Condori', '76543210') + ['cargo' => 'Encargada']);

    [$deEmpresa] = app(CrearSolicitud::class)->ejecutar(217, solicitudDe($empresa));
    [$dePersona] = app(CrearSolicitud::class)->ejecutar(218, solicitudDe($persona));
    DB::table('sol_solicitudes_servicio')->whereIn('id', [$deEmpresa->id, $dePersona->id])->update(['estado' => 'adjudicada', 'equipo_id' => 7]);
    // Una vez adjudicada, dar de baja a la persona no cambia lo que se entregó.
    $gestion->agregar($empresa, datosContacto('Beto'));
    $gestion->darDeBaja($empresa, $rosa->id);

    $asignados = collect(app(LecturaServiciosAsignados::class)->asignadosDesde(null, null, 10, [7]))->keyBy('id');

    expect($asignados[$deEmpresa->id]->contacto)->toBe(['nombre' => 'Rosa Condori', 'telefono' => '76543210', 'cargo' => 'Encargada', 'empresa' => 'Agropecuaria San Rafael SRL'])
        ->and($asignados[$dePersona->id]->contacto)->toBe(['nombre' => 'Persona 218', 'telefono' => '70012345', 'cargo' => null, 'empresa' => null]);
});

test('registrar una empresa crea sus contactos en la misma transacción y la rechaza sin ninguno', function () {
    app()->instance(RegistroCuentas::class, new class implements RegistroCuentas
    {
        private int $siguiente = 300;

        public function crear(string $nombre, string $username, ?string $email, string $password, TipoCuenta $tipoCuenta, array $roles): int
        {
            return ++$this->siguiente;
        }

        public function agregarRol(int $usuarioId, string $rol): void {}

        public function quitarRol(int $usuarioId, string $rol): void {}
    });

    $base = ['nombre' => 'Rosa Condori', 'username' => 'rosa', 'email' => null, 'password' => 'Clave1234', 'tipo' => 'empresa', 'razon_social' => 'San Rafael SRL', 'documento_fiscal' => '123', 'telefono' => '76543210'];

    $cliente = app(RegistrarCliente::class)->ejecutar([...$base, 'contactos' => [datosContacto('Rosa'), datosContacto('Beto')]]);

    expect($cliente->contactos)->toHaveCount(2)
        ->and($cliente->contactos->first()->es_principal)->toBeTrue();

    $antes = DB::table('sol_clientes')->count();
    expect(fn () => app(RegistrarCliente::class)->ejecutar([...$base, 'username' => 'otra', 'documento_fiscal' => '456']))->toThrow(SolicitudInvalida::class)
        ->and(DB::table('sol_clientes')->count())->toBe($antes);
});

<?php

use App\Dominios\Asignacion\Aplicacion\CancelarComoProveedor;
use App\Dominios\Asignacion\Aplicacion\ForzarAsignacion;
use App\Dominios\Asignacion\Aplicacion\IniciarAsignacion;
use App\Dominios\Asignacion\Aplicacion\LevantarSancion;
use App\Dominios\Asignacion\Aplicacion\ListarOfertasDelProveedor;
use App\Dominios\Asignacion\Aplicacion\ResponderOferta;
use App\Dominios\Asignacion\Aplicacion\RetirarOfertas;
use App\Dominios\Asignacion\Aplicacion\VencerOfertas;
use App\Dominios\Asignacion\Contratos\Eventos\OfertaCreada;
use App\Dominios\Asignacion\Contratos\Eventos\OfertaRetirada;
use App\Dominios\Asignacion\Contratos\Eventos\OfertaVencida;
use App\Dominios\Asignacion\Contratos\Eventos\ProveedorSancionado;
use App\Dominios\Asignacion\Contratos\Eventos\SolicitudAsignada;
use App\Dominios\Asignacion\Contratos\Eventos\SolicitudReasignada;
use App\Dominios\Asignacion\Contratos\Eventos\SolicitudSinProveedor;
use App\Dominios\Asignacion\Dominio\CausaCancelacion;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Infraestructura\Eloquent\CancelacionDeProveedor;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Ronda;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Sancion;
use App\Dominios\Asignacion\Infraestructura\Http\Resources\OfertaResource;
use App\Dominios\Flota\Contratos\DatosDisponibilidad;
use App\Dominios\Flota\Contratos\DatosEquipoApto;
use App\Dominios\Flota\Contratos\LecturaDisponibilidad;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Proveedores\Contratos\DatosAuxiliar;
use App\Dominios\Proveedores\Contratos\DatosPiloto;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Proveedores\Contratos\ReputacionDeProveedores;
use App\Dominios\Solicitudes\Contratos\LecturaZonas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
 * El motor de asignación de punta a punta (especificación del marketplace §5.3 y
 * §6, ADR 0030 y 0038): propios antes que terceros, expiración, escalado por
 * radios, aceptación atómica, idempotencia, cancelación del proveedor y
 * sanciones. Contra el ESQUEMA REAL de solicitudes y de asignación (las
 * migraciones, en SQLite en memoria), con los casos de uso reales y con los
 * contratos de `Proveedores`, `Flota` y `Solicitudes\LecturaZonas` reemplazados
 * por datos controlados por la prueba.
 *
 * Mundo de la prueba (zona 1 = Warnes, el terreno; zona 2 = Santa Cruz a unos
 * 30 km; zona 3 = Cabezas a unos 140 km):
 *   1 Agrocom (propio, zona 2)        titular 501, equipo 11
 *   2 Independiente Beto (zona 2)     titular 502, equipo 12
 *   3 Tercero Cruz (zona 2)           titular 503, equipo 13
 *   4 Tercero Lejano (zona 3)         titular 504, equipo 14
 */

uses(TestCase::class);

const ZONA_TERRENO = 1;
const ZONA_CERCA = 2;
const ZONA_LEJOS = 3;

function motorEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'create_sol_solicitud_historial_table',
        'create_asg_rondas_table',
        'create_asg_ofertas_table',
        'create_asg_cancelaciones_proveedor_table',
        'create_asg_sanciones_table',
    ];

    static $instancias = [];

    foreach ($migraciones as $nombre) {
        $archivos = glob(database_path("migrations/*_{$nombre}.php")) ?: [];

        if (count($archivos) !== 1) {
            throw new RuntimeException("Se esperaba exactamente una migración {$nombre}.");
        }

        $instancias[$archivos[0]] ??= require $archivos[0];

        try {
            $instancias[$archivos[0]]->up();
        } catch (QueryException $excepcion) {
            if (! str_contains($excepcion->getMessage(), 'already exists') && ! str_contains($excepcion->getMessage(), 'USING btree')) {
                throw $excepcion;
            }
        }
    }
}

/**
 * Registra el mundo: zonas, cliente, terreno y los contratos de otros módulos.
 *
 * @param  array<int, array<string, mixed>>  $cambiosProveedor  cambios por id de proveedor.
 * @param  array<int, list<int>>  $equiposPorProveedor  equipos aptos por proveedor (id de equipo).
 * @param  list<int>  $enLinea  equipos en línea.
 * @return array<string, mixed>
 */
function motorMundo(array $cambiosProveedor = [], ?array $equiposPorProveedor = null, array $enLinea = [11, 12, 13, 14]): array
{
    DB::table('sol_zonas')->insert([
        ['id' => ZONA_TERRENO, 'nombre' => 'Warnes', 'centroide_lat' => '-17.5141000', 'centroide_lng' => '-63.1668000', 'radio_km' => '25', 'created_at' => now(), 'updated_at' => now()],
        ['id' => ZONA_CERCA, 'nombre' => 'Santa Cruz de la Sierra', 'centroide_lat' => '-17.7833000', 'centroide_lng' => '-63.1821000', 'radio_km' => '40', 'created_at' => now(), 'updated_at' => now()],
        ['id' => ZONA_LEJOS, 'nombre' => 'Cabezas', 'centroide_lat' => '-18.7833000', 'centroide_lng' => '-63.4167000', 'radio_km' => '40', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $clienteId = DB::table('sol_clientes')->insertGetId([
        'usuario_id' => 900, 'tipo' => 'persona', 'razon_social' => 'Cliente Ana', 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $terrenoId = DB::table('sol_terrenos')->insertGetId([
        'cliente_id' => $clienteId, 'nombre' => 'Terreno Ana', 'hectareas' => '100.0000', 'acceso_dificil' => false, 'zona_id' => ZONA_TERRENO, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $base = [
        1 => ['tipo' => 'propio', 'razon' => 'Agrocom', 'titular' => 501, 'zona' => ZONA_CERCA, 'radio' => '200.0'],
        2 => ['tipo' => 'independiente', 'razon' => 'Beto', 'titular' => 502, 'zona' => ZONA_CERCA, 'radio' => '200.0'],
        3 => ['tipo' => 'tercero', 'razon' => 'Cruz', 'titular' => 503, 'zona' => ZONA_CERCA, 'radio' => '200.0'],
        4 => ['tipo' => 'tercero', 'razon' => 'Lejano', 'titular' => 504, 'zona' => ZONA_LEJOS, 'radio' => '200.0'],
    ];

    $proveedores = [];
    foreach ($base as $id => $datos) {
        $datos = [...$datos, ...($cambiosProveedor[$id] ?? [])];
        $proveedores[$id] = new DatosProveedor(
            $id, $datos['titular'], $datos['tipo'], $datos['razon'], $datos['estado'] ?? 'aprobado', $datos['zona'], $datos['radio'],
            $datos['calificacion'] ?? '4.50', $datos['conteo'] ?? 20, $datos['aceptacion'] ?? '1.0000', $datos['cancelacion'] ?? '0.0000',
        );
    }

    $equiposPorProveedor ??= [1 => [11], 2 => [12], 3 => [13], 4 => [14]];

    $equipos = [];
    foreach ($equiposPorProveedor as $proveedorId => $ids) {
        foreach ($ids as $equipoId) {
            $equipos[$proveedorId][] = new DatosEquipoApto($equipoId, $proveedorId, 100 + $equipoId, 200 + $equipoId, null, '30.0', '8.0');
        }
    }

    app()->instance(LecturaProveedores::class, new class($proveedores) implements LecturaProveedores
    {
        /** @param  array<int, DatosProveedor>  $proveedores */
        public function __construct(private array $proveedores) {}

        public function proveedor(int $id): ?DatosProveedor
        {
            return $this->proveedores[$id] ?? null;
        }

        public function proveedorDelTitular(int $usuarioId): ?DatosProveedor
        {
            return $this->proveedorDeLaCuenta($usuarioId);
        }

        public function proveedorPropio(): ?DatosProveedor
        {
            return $this->proveedores[1];
        }

        public function piloto(int $id): ?DatosPiloto
        {
            return null;
        }

        public function auxiliar(int $id): ?DatosAuxiliar
        {
            return null;
        }

        public function registrosDeCuenta(int $usuarioId): array
        {
            return ['pilotos' => [], 'auxiliares' => []];
        }

        public function pilotosDe(int $proveedorId): array
        {
            return [];
        }

        public function aprobadosDelGrupo(string $grupo): array
        {
            return array_values(array_filter(
                $this->proveedores,
                fn (DatosProveedor $p): bool => $p->estaAprobado() && (($grupo === 'propio') === ($p->tipo === 'propio')),
            ));
        }

        public function usuariosDeAviso(int $proveedorId): array
        {
            return [$this->proveedores[$proveedorId]->usuarioTitularId];
        }

        public function proveedorDeLaCuenta(int $usuarioId): ?DatosProveedor
        {
            foreach ($this->proveedores as $proveedor) {
                if ($proveedor->usuarioTitularId === $usuarioId) {
                    return $proveedor;
                }
            }

            return null;
        }
    });

    app()->instance(LecturaEquipos::class, new class($equipos, $proveedores) implements LecturaEquipos
    {
        /**
         * @param  array<int, list<DatosEquipoApto>>  $equipos
         * @param  array<int, DatosProveedor>  $proveedores
         */
        public function __construct(private array $equipos, private array $proveedores) {}

        public function aptosDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return ($this->proveedores[$proveedorId] ?? null)?->estaAprobado() ? ($this->equipos[$proveedorId] ?? []) : [];
        }

        public function equiposDeCuenta(int $usuarioId, bool $soloVigentes = true, ?DateTimeImmutable $hoy = null): array
        {
            return [];
        }

        public function tieneEquipoApto(int $proveedorId, ?DateTimeImmutable $hoy = null): bool
        {
            return $this->aptosDe($proveedorId) !== [];
        }

        public function vigentesDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return array_map(fn (DatosEquipoApto $e): int => $e->equipoId, $this->equipos[$proveedorId] ?? []);
        }
    });

    app()->instance(LecturaDisponibilidad::class, new class($enLinea) implements LecturaDisponibilidad
    {
        /** @param  list<int>  $enLinea */
        public function __construct(private array $enLinea) {}

        public function porEquipos(array $equipoIds): array
        {
            $resultado = [];
            foreach ($equipoIds as $id) {
                $resultado[$id] = new DatosDisponibilidad($id, in_array($id, $this->enLinea, true), null, null, now()->toIso8601String());
            }

            return $resultado;
        }
    });

    app()->instance(LecturaZonas::class, new class implements LecturaZonas
    {
        public function nombre(int $id): ?string
        {
            return DB::table('sol_zonas')->where('id', $id)->value('nombre');
        }

        public function centroide(int $id): ?array
        {
            $zona = DB::table('sol_zonas')->where('id', $id)->first();

            return $zona === null ? null : ['lat' => (float) $zona->centroide_lat, 'lng' => (float) $zona->centroide_lng];
        }
    });

    $tasas = new class implements ReputacionDeProveedores
    {
        /** @var array<int, array{string, string}> */
        public array $ultimas = [];

        public function actualizarTasas(int $proveedorId, string $tasaAceptacion, string $tasaCancelacion): void
        {
            $this->ultimas[$proveedorId] = [$tasaAceptacion, $tasaCancelacion];
        }
    };
    app()->instance(ReputacionDeProveedores::class, $tasas);

    return ['cliente' => $clienteId, 'terreno' => $terrenoId, 'tasas' => $tasas];
}

/** Una solicitud `publicada` lista para que el motor la tome. */
function motorSolicitud(array $mundo, array $cambios = []): int
{
    $id = DB::table('sol_solicitudes_servicio')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(),
        'cliente_id' => $mundo['cliente'],
        'terreno_id' => $mundo['terreno'],
        'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '80.0000',
        'producto_indicado' => 'Glifosato',
        'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-10',
        'ventana_hasta' => '2026-12-15',
        'urgente' => false,
        'tarifa_hectarea' => '120.0000',
        'monto_estimado' => '9600.00',
        'estado' => 'publicada',
        'created_at' => now(),
        'updated_at' => now(),
        ...$cambios,
    ]);

    return $id;
}

function motorEstado(int $solicitudId): string
{
    return (string) DB::table('sol_solicitudes_servicio')->where('id', $solicitudId)->value('estado');
}

/** @return list<int> proveedores con oferta en la ronda. */
function motorProveedoresDeRonda(int $solicitudId, int $ronda, int $ciclo = 1): array
{
    return array_map('intval', Oferta::query()->where('solicitud_id', $solicitudId)->where('ciclo', $ciclo)->where('ronda', $ronda)->orderBy('proveedor_id')->pluck('proveedor_id')->all());
}

/** Avanza el reloj lo que haga falta y corre el proceso que vence ofertas. */
function motorPasaElTiempo(string $hasta): int
{
    Carbon::setTestNow($hasta);

    return app(VencerOfertas::class)->ejecutar();
}

beforeEach(function () {
    motorEsquema();
    Carbon::setTestNow('2026-12-02 10:00:00');
    Event::fake([
        OfertaCreada::class, OfertaVencida::class, OfertaRetirada::class, SolicitudAsignada::class,
        SolicitudReasignada::class, SolicitudSinProveedor::class, ProveedorSancionado::class,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

// ── Propios antes que terceros ───────────────────────────────────────────────

test('al tomar una solicitud publicada se ofrece primero y solo a los propios', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);

    expect(app(IniciarAsignacion::class)->ejecutar($solicitud))->toBeTrue()
        ->and(motorEstado($solicitud))->toBe('en_asignacion')
        ->and(motorProveedoresDeRonda($solicitud, 1))->toBe([1])
        ->and(Oferta::query()->count())->toBe(1);

    $oferta = Oferta::query()->first();

    expect($oferta->grupo->value)->toBe('propio')
        ->and($oferta->estado->value)->toBe('ofrecida')
        ->and($oferta->origen->value)->toBe('motor')
        ->and($oferta->pago_estimado_proveedor)->toBe('9600.00')
        ->and($oferta->distancia_km)->toBe('29.98');

    Event::assertDispatched(OfertaCreada::class, fn (OfertaCreada $e): bool => $e->proveedorId === 1 && $e->usuariosDestinatarios === [501] && $e->grupo === 'propio');
});

test('la ventana de la oferta es de 2 horas si es programada y de 10 minutos si es urgente', function () {
    $mundo = motorMundo();
    $programada = motorSolicitud($mundo);
    $urgente = motorSolicitud($mundo, ['urgente' => true]);

    app(IniciarAsignacion::class)->ejecutar($programada);
    app(IniciarAsignacion::class)->ejecutar($urgente);

    expect(Oferta::query()->where('solicitud_id', $programada)->first()->vence_en->toDateTimeString())->toBe('2026-12-02 12:00:00')
        ->and(Oferta::query()->where('solicitud_id', $urgente)->first()->vence_en->toDateTimeString())->toBe('2026-12-02 10:10:00');
});

test('el pago estimado de un tercero descuenta la comisión; el de un propio no', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    motorPasaElTiempo('2026-12-02 12:00:01');

    expect(Oferta::query()->where('proveedor_id', 2)->first()->pago_estimado_proveedor)->toBe('8160.00');
});

test('si los propios no tienen equipo libre, la solicitud pasa a terceros en el acto', function () {
    $mundo = motorMundo(equiposPorProveedor: [1 => [], 2 => [12], 3 => [13], 4 => [14]]);
    $solicitud = motorSolicitud($mundo);

    app(IniciarAsignacion::class)->ejecutar($solicitud);

    expect(motorProveedoresDeRonda($solicitud, 1))->toBe([])
        ->and(motorProveedoresDeRonda($solicitud, 2))->toBe([2, 3])
        ->and(Ronda::query()->where('ronda', 1)->first()->proveedores_ofrecidos)->toBe(0)
        ->and(Ronda::query()->where('ronda', 1)->first()->cerrada_en)->not->toBeNull()
        ->and(motorEstado($solicitud))->toBe('en_asignacion');
});

test('una solicitud urgente solo se ofrece a quien está en línea; una programada también a quien no', function () {
    $mundo = motorMundo(enLinea: []);
    $urgente = motorSolicitud($mundo, ['urgente' => true]);
    $programada = motorSolicitud($mundo);

    app(IniciarAsignacion::class)->ejecutar($urgente);
    app(IniciarAsignacion::class)->ejecutar($programada);

    expect(Oferta::query()->where('solicitud_id', $urgente)->count())->toBe(0)
        ->and(motorEstado($urgente))->toBe('sin_proveedor')
        ->and(motorProveedoresDeRonda($programada, 1))->toBe([1]);
});

test('un equipo con otro servicio en la misma ventana no recibe ofertas', function () {
    $mundo = motorMundo();
    DB::table('sol_solicitudes_servicio')->insert([
        'uuid_cliente' => (string) Str::uuid(), 'cliente_id' => $mundo['cliente'], 'terreno_id' => $mundo['terreno'], 'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '10', 'producto_indicado' => 'X', 'aporta_producto' => 'cliente', 'ventana_desde' => '2026-12-12', 'ventana_hasta' => '2026-12-13',
        'estado' => 'asignada', 'proveedor_id' => 1, 'equipo_id' => 11, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $solicitud = motorSolicitud($mundo);

    app(IniciarAsignacion::class)->ejecutar($solicitud);

    expect(motorProveedoresDeRonda($solicitud, 1))->toBe([])
        ->and(motorProveedoresDeRonda($solicitud, 2))->toBe([2, 3]);
});

test('un proveedor suspendido, sin equipo apto o con calificación bajo el umbral no recibe ofertas', function () {
    $mundo = motorMundo([2 => ['estado' => 'suspendido'], 3 => ['calificacion' => '3.10', 'conteo' => 15]], [1 => [11], 2 => [12], 3 => [13], 4 => []]);
    $solicitud = motorSolicitud($mundo);

    app(IniciarAsignacion::class)->ejecutar($solicitud);
    motorPasaElTiempo('2026-12-02 12:00:01');

    expect(Oferta::query()->where('solicitud_id', $solicitud)->pluck('proveedor_id')->all())->toBe([1])
        ->and(motorEstado($solicitud))->toBe('sin_proveedor');
});

test('quien tiene una restricción de ofertas vigente no recibe ofertas; al levantarla vuelve a recibirlas', function () {
    $mundo = motorMundo();
    $sancion = Sancion::query()->create(['proveedor_id' => 1, 'tipo' => 'restriccion_ofertas', 'causa' => 'cancelaciones', 'motivo' => 'x', 'desde' => now(), 'hasta' => now()->addDays(7)]);

    $primera = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($primera);

    expect(motorProveedoresDeRonda($primera, 1))->toBe([]);

    app(LevantarSancion::class)->ejecutar(77, $sancion->id, 'Apelación aceptada');
    $segunda = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($segunda);

    expect(motorProveedoresDeRonda($segunda, 1))->toBe([1])
        ->and($sancion->fresh()->levantada_por)->toBe(77);
});

// ── Expiración y escalado ────────────────────────────────────────────────────

test('si el propio no responde, la oferta vence y se ofrece a terceros dentro del radio de 50 km', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);

    expect(motorPasaElTiempo('2026-12-02 11:59:00'))->toBe(0)
        ->and(Oferta::query()->first()->estado->value)->toBe('ofrecida');

    expect(motorPasaElTiempo('2026-12-02 12:00:01'))->toBe(1)
        ->and(Oferta::query()->where('proveedor_id', 1)->first()->estado->value)->toBe('vencida')
        ->and(motorProveedoresDeRonda($solicitud, 2))->toBe([2, 3])
        ->and(Oferta::query()->where('ronda', 2)->pluck('grupo')->map(fn ($g) => $g->value)->unique()->all())->toBe(['tercero'])
        ->and(motorEstado($solicitud))->toBe('en_asignacion');

    Event::assertDispatched(OfertaVencida::class, fn (OfertaVencida $e): bool => $e->proveedorId === 1);
});

test('la ronda siguiente espera a que venza o responda la última oferta viva', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    motorPasaElTiempo('2026-12-02 12:00:01');

    app(ResponderOferta::class)->rechazar(502, Oferta::query()->where('proveedor_id', 2)->first()->id, 'Sin equipo esa semana');

    expect(motorProveedoresDeRonda($solicitud, 3))->toBe([])
        ->and(Ronda::query()->where('ronda', 3)->exists())->toBeFalse();
});

test('sin aceptación el radio crece: 50, 100 y 150 km, y los lejanos entran en la última ronda', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);

    motorPasaElTiempo('2026-12-02 12:00:01'); // vence propios → ronda 2 (50 km)
    motorPasaElTiempo('2026-12-02 14:00:02'); // vencen terceros cercanos → ronda 3 (100 km, vacía) → ronda 4 (150 km)

    expect(motorProveedoresDeRonda($solicitud, 3))->toBe([])
        ->and(Ronda::query()->where('ronda', 3)->first()->radio_km)->toBe('100.00')
        ->and(Ronda::query()->where('ronda', 3)->first()->proveedores_ofrecidos)->toBe(0)
        ->and(motorProveedoresDeRonda($solicitud, 4))->toBe([4])
        ->and(Ronda::query()->where('ronda', 4)->first()->radio_km)->toBe('150.00')
        ->and(motorEstado($solicitud))->toBe('en_asignacion');
});

test('agotadas las rondas la solicitud pasa a sin proveedor y se avisa al cliente', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);

    motorPasaElTiempo('2026-12-02 12:00:01');
    motorPasaElTiempo('2026-12-02 14:00:02');
    motorPasaElTiempo('2026-12-02 16:00:03');

    expect(motorEstado($solicitud))->toBe('sin_proveedor')
        ->and(Ronda::query()->where('solicitud_id', $solicitud)->count())->toBe(4)
        ->and(Ronda::query()->whereNull('cerrada_en')->count())->toBe(0)
        ->and(Oferta::query()->where('estado', 'ofrecida')->count())->toBe(0)
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitud)->where('estado_nuevo', 'sin_proveedor')->value('origen'))->toBe('sistema');

    Event::assertDispatched(SolicitudSinProveedor::class, fn (SolicitudSinProveedor $e): bool => $e->solicitudId === $solicitud && $e->clienteUsuarioId === 900);
});

test('los terceros se ordenan por puntuación y se ofrece a un máximo por ronda', function () {
    config(['asignacion.max_proveedores_por_ronda' => 1]);
    $mundo = motorMundo([2 => ['calificacion' => '4.00'], 3 => ['calificacion' => '4.90']]);
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    motorPasaElTiempo('2026-12-02 12:00:01');

    expect(motorProveedoresDeRonda($solicitud, 2))->toBe([3])
        ->and(Oferta::query()->where('proveedor_id', 3)->first()->puntuacion)->not->toBeNull();
});

test('quien tiene la tasa de aceptación bajo el umbral va después en el orden de terceros', function () {
    config(['asignacion.max_proveedores_por_ronda' => 1]);
    $mundo = motorMundo([2 => ['calificacion' => '4.90', 'aceptacion' => '0.4000'], 3 => ['calificacion' => '4.00']]);
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    motorPasaElTiempo('2026-12-02 12:00:01');

    expect(motorProveedoresDeRonda($solicitud, 2))->toBe([3]);
});

// ── Idempotencia ─────────────────────────────────────────────────────────────

test('tomar dos veces la misma solicitud no duplica ofertas ni rondas', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);

    expect(app(IniciarAsignacion::class)->ejecutar($solicitud))->toBeTrue()
        ->and(app(IniciarAsignacion::class)->ejecutar($solicitud))->toBeFalse()
        ->and(Oferta::query()->count())->toBe(1)
        ->and(Ronda::query()->count())->toBe(1);

    Event::assertDispatchedTimes(OfertaCreada::class, 1);
});

test('correr el proceso de vencimientos varias veces deja el mismo estado final', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);

    motorPasaElTiempo('2026-12-02 12:00:01');
    $despuesDeUna = [Oferta::query()->count(), Ronda::query()->count(), DB::table('sol_solicitud_historial')->count()];

    foreach (range(1, 3) as $_) {
        expect(app(VencerOfertas::class)->ejecutar())->toBe(0);
    }

    expect([Oferta::query()->count(), Ronda::query()->count(), DB::table('sol_solicitud_historial')->count()])->toBe($despuesDeUna);
    Event::assertDispatchedTimes(OfertaVencida::class, 1);
});

test('un proveedor recibe a lo sumo una oferta por solicitud y ciclo, aunque se fuerce otra vuelta', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);

    expect(fn () => Oferta::query()->create([
        'solicitud_id' => $solicitud, 'proveedor_id' => 1, 'ciclo' => 1, 'ronda' => 1, 'grupo' => 'propio', 'origen' => 'motor',
        'ofrecida_en' => now(), 'vence_en' => now()->addHour(),
    ]))->toThrow(QueryException::class);
});

// ── Aceptar y rechazar ───────────────────────────────────────────────────────

test('aceptar deja la solicitud asignada con el proveedor y el equipo, retira las demás ofertas y avisa', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    motorPasaElTiempo('2026-12-02 12:00:01');

    $ofertaBeto = Oferta::query()->where('proveedor_id', 2)->first();
    $aceptada = app(ResponderOferta::class)->aceptar(502, $ofertaBeto->id);

    expect($aceptada->estado->value)->toBe('aceptada')
        ->and($aceptada->equipo_id)->toBe(12)
        ->and(motorEstado($solicitud))->toBe('asignada')
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('proveedor_id'))->toBe(2)
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('equipo_id'))->toBe(12)
        ->and(Oferta::query()->where('proveedor_id', 3)->first()->estado->value)->toBe('retirada')
        ->and(Oferta::query()->where('proveedor_id', 3)->first()->motivo_retiro->value)->toBe('otro_acepto')
        ->and(Ronda::query()->whereNull('cerrada_en')->count())->toBe(0)
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitud)->where('estado_nuevo', 'asignada')->value('origen'))->toBe('proveedor');

    Event::assertDispatched(SolicitudAsignada::class, fn (SolicitudAsignada $e): bool => $e->clienteUsuarioId === 900 && $e->proveedorId === 2 && $e->equipoId === 12 && $e->usuariosDestinatarios === [502]);
    Event::assertDispatched(OfertaRetirada::class, fn (OfertaRetirada $e): bool => $e->proveedorId === 3 && $e->motivo === 'otro_acepto');
});

test('el segundo proveedor que acepta recibe conflicto y la solicitud sigue con el primero', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    motorPasaElTiempo('2026-12-02 12:00:01');
    $ofertaCruz = Oferta::query()->where('proveedor_id', 3)->first();

    app(ResponderOferta::class)->aceptar(502, Oferta::query()->where('proveedor_id', 2)->first()->id);

    try {
        app(ResponderOferta::class)->aceptar(503, $ofertaCruz->id);
        $this->fail('Debió rechazar el segundo aceptar.');
    } catch (AsignacionInvalida $excepcion) {
        expect($excepcion->esConflicto)->toBeTrue();
    }

    expect(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('proveedor_id'))->toBe(2)
        ->and(Oferta::query()->where('estado', 'aceptada')->count())->toBe(1);
});

test('aceptar dos veces la misma oferta es inofensivo: misma oferta, un solo aviso', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    $oferta = Oferta::query()->first();

    $primera = app(ResponderOferta::class)->aceptar(501, $oferta->id);
    $segunda = app(ResponderOferta::class)->aceptar(501, $oferta->id);

    expect($segunda->id)->toBe($primera->id)
        ->and($segunda->estado->value)->toBe('aceptada')
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitud)->where('estado_nuevo', 'asignada')->count())->toBe(1);
    Event::assertDispatchedTimes(SolicitudAsignada::class, 1);
});

test('una oferta vencida, retirada o ya rechazada no se acepta', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    $ofertaPropia = Oferta::query()->first();

    Carbon::setTestNow('2026-12-02 12:00:01');
    expect(fn () => app(ResponderOferta::class)->aceptar(501, $ofertaPropia->id))->toThrow(AsignacionInvalida::class);

    motorPasaElTiempo('2026-12-02 12:00:02');
    $vencida = fn () => app(ResponderOferta::class)->aceptar(501, $ofertaPropia->id);
    expect($vencida)->toThrow(AsignacionInvalida::class);

    $ofertaBeto = Oferta::query()->where('proveedor_id', 2)->first();
    app(ResponderOferta::class)->rechazar(502, $ofertaBeto->id, 'No me conviene');
    expect(fn () => app(ResponderOferta::class)->aceptar(502, $ofertaBeto->id))->toThrow(AsignacionInvalida::class);
});

test('la oferta de otro proveedor responde como si no existiera', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    $oferta = Oferta::query()->first();

    foreach ([
        fn () => app(ResponderOferta::class)->aceptar(502, $oferta->id),
        fn () => app(ResponderOferta::class)->rechazar(502, $oferta->id, 'No es mía'),
        fn () => app(ListarOfertasDelProveedor::class)->una(502, $oferta->id),
    ] as $intento) {
        try {
            $intento();
            $this->fail('Debió responder como oferta ajena.');
        } catch (AsignacionInvalida $excepcion) {
            expect($excepcion->esAjeno)->toBeTrue();
        }
    }

    expect(app(ListarOfertasDelProveedor::class)->ejecutar(502))->toBe([])
        ->and($oferta->fresh()->estado->value)->toBe('ofrecida');
});

test('una cuenta que no es de ningún proveedor no ve ni responde ofertas', function () {
    motorMundo();

    expect(fn () => app(ListarOfertasDelProveedor::class)->ejecutar(999))->toThrow(AsignacionInvalida::class);
});

test('rechazar exige motivo; rechazar dos veces devuelve la misma oferta', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    $oferta = Oferta::query()->first();

    expect(fn () => app(ResponderOferta::class)->rechazar(501, $oferta->id, '  '))->toThrow(AsignacionInvalida::class);

    $primera = app(ResponderOferta::class)->rechazar(501, $oferta->id, 'Equipo en mantenimiento');
    $segunda = app(ResponderOferta::class)->rechazar(501, $oferta->id, 'Equipo en mantenimiento');

    expect($primera->estado->value)->toBe('rechazada')
        ->and($primera->motivo_rechazo)->toBe('Equipo en mantenimiento')
        ->and($segunda->id)->toBe($primera->id)
        ->and(motorProveedoresDeRonda($solicitud, 2))->toBe([2, 3]);
});

test('rechazar a todos escala sin esperar al vencimiento', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);

    app(ResponderOferta::class)->rechazar(501, Oferta::query()->where('proveedor_id', 1)->first()->id, 'No hay pilotos');

    expect(motorProveedoresDeRonda($solicitud, 2))->toBe([2, 3]);
});

test('el rechazo no penaliza hasta bajar el umbral de aceptación: la tasa se recalcula', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);

    app(ResponderOferta::class)->rechazar(501, Oferta::query()->first()->id, 'No hay pilotos');

    expect($mundo['tasas']->ultimas[1])->toBe(['0.0000', '0.0000'])
        ->and(Sancion::query()->count())->toBe(0);
});

test('elegir un equipo ocupado o ajeno se rechaza; sin indicación se elige uno en línea', function () {
    $mundo = motorMundo(equiposPorProveedor: [1 => [11, 15], 2 => [12], 3 => [13], 4 => [14]], enLinea: [15, 12, 13, 14]);
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    $oferta = Oferta::query()->first();

    expect(fn () => app(ResponderOferta::class)->aceptar(501, $oferta->id, 99))->toThrow(AsignacionInvalida::class);

    $aceptada = app(ResponderOferta::class)->aceptar(501, $oferta->id);

    expect($aceptada->equipo_id)->toBe(15);
});

// ── Cancelación del proveedor y reasignación ─────────────────────────────────

function motorAsignada(array $mundo, int $titular = 501, array $cambiosSolicitud = []): array
{
    $solicitud = motorSolicitud($mundo, $cambiosSolicitud);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    $oferta = Oferta::query()->where('solicitud_id', $solicitud)->where('estado', 'ofrecida')->first();
    app(ResponderOferta::class)->aceptar($titular, $oferta->id);

    return [$solicitud, $oferta->id];
}

test('si el proveedor cancela, la solicitud vuelve a asignación con un ciclo nuevo y sin ofrecérsela a quien canceló', function () {
    $mundo = motorMundo();
    [$solicitud] = motorAsignada($mundo);

    $cancelacion = app(CancelarComoProveedor::class)->ejecutar(501, $solicitud, 'Se averió el dron');

    expect(motorEstado($solicitud))->toBe('en_asignacion')
        ->and($cancelacion->peso)->toBe(1)
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('proveedor_id'))->toBeNull()
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('equipo_id'))->toBe(11)
        ->and(motorProveedoresDeRonda($solicitud, 1, 2))->toBe([])
        ->and(motorProveedoresDeRonda($solicitud, 2, 2))->toBe([2, 3])
        ->and(Ronda::query()->where('solicitud_id', $solicitud)->max('ciclo'))->toBe(2);

    Event::assertDispatched(SolicitudReasignada::class, fn (SolicitudReasignada $e): bool => $e->clienteUsuarioId === 900 && $e->proveedorId === 1);
});

test('solo cancela el proveedor al que está asignada, y solo mientras está asignada o en camino', function () {
    $mundo = motorMundo();
    [$solicitud] = motorAsignada($mundo);

    try {
        app(CancelarComoProveedor::class)->ejecutar(502, $solicitud, 'No es mía');
        $this->fail('Debió responder como solicitud ajena.');
    } catch (AsignacionInvalida $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }

    expect(fn () => app(CancelarComoProveedor::class)->ejecutar(501, $solicitud, ' '))->toThrow(AsignacionInvalida::class);

    DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->update(['estado' => 'en_ejecucion']);
    expect(fn () => app(CancelarComoProveedor::class)->ejecutar(501, $solicitud, 'Tarde'))->toThrow(AsignacionInvalida::class);
});

test('cancelar con menos de 12 horas pesa el doble; una emergencia no pesa', function () {
    $mundo = motorMundo();
    [$cercana] = motorAsignada($mundo, cambiosSolicitud: ['ventana_desde' => '2026-12-02', 'ventana_hasta' => '2026-12-04']);
    Carbon::setTestNow('2026-12-02 00:00:00');
    DB::table('sol_solicitudes_servicio')->where('id', $cercana)->update(['ventana_desde' => '2026-12-02 10:00:00']);

    $tardia = app(CancelarComoProveedor::class)->ejecutar(501, $cercana, 'Imprevisto');

    [$lejana] = motorAsignada($mundo);
    $emergencia = app(CancelarComoProveedor::class)->ejecutar(501, $lejana, 'Accidente', CausaCancelacion::Emergencia);

    expect($tardia->peso)->toBe(1 + 1)
        ->and($emergencia->peso)->toBe(0)
        ->and(CancelacionDeProveedor::query()->count())->toBe(2);
});

test('tres cancelaciones en 30 días son una advertencia; cinco, una restricción de ofertas', function () {
    $mundo = motorMundo();

    foreach (range(1, 3) as $_) {
        [$solicitud] = motorAsignada($mundo);
        app(CancelarComoProveedor::class)->ejecutar(501, $solicitud, 'Imprevisto');
        DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->update(['estado' => 'cancelada', 'motivo_cancelacion' => 'x', 'cancelada_por' => 'cliente']);
        Oferta::query()->where('solicitud_id', $solicitud)->where('estado', 'ofrecida')->get()->each(fn (Oferta $o) => $o->forceFill(['estado' => 'retirada'])->save());
    }

    expect(Sancion::query()->where('proveedor_id', 1)->pluck('tipo')->map(fn ($t) => $t->value)->all())->toBe(['advertencia'])
        ->and($mundo['tasas']->ultimas[1][1])->not->toBe('0.0000');

    foreach (range(1, 2) as $_) {
        [$solicitud] = motorAsignada($mundo);
        app(CancelarComoProveedor::class)->ejecutar(501, $solicitud, 'Imprevisto');
        DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->update(['estado' => 'cancelada', 'motivo_cancelacion' => 'x', 'cancelada_por' => 'cliente']);
        Oferta::query()->where('solicitud_id', $solicitud)->where('estado', 'ofrecida')->get()->each(fn (Oferta $o) => $o->forceFill(['estado' => 'retirada'])->save());
    }

    $restriccion = Sancion::query()->where('proveedor_id', 1)->where('tipo', 'restriccion_ofertas')->first();

    expect($restriccion)->not->toBeNull()
        ->and($restriccion->estaVigente())->toBeTrue()
        ->and($restriccion->hasta->toDateString())->toBe('2026-12-09');

    Event::assertDispatched(ProveedorSancionado::class, fn (ProveedorSancionado $e): bool => $e->tipo === 'restriccion_ofertas' && $e->causa === 'cancelaciones');

    $nueva = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($nueva);

    expect(motorProveedoresDeRonda($nueva, 1))->toBe([]);
});

// ── Cancelación del cliente ──────────────────────────────────────────────────

test('si el cliente cancela, las ofertas vivas se retiran sin contar contra los proveedores', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    motorPasaElTiempo('2026-12-02 12:00:01');

    DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->update(['estado' => 'cancelada', 'motivo_cancelacion' => 'x', 'cancelada_por' => 'cliente']);
    $retiradas = app(RetirarOfertas::class)->porCancelacion($solicitud);

    expect($retiradas)->toBe(2)
        ->and(Oferta::query()->where('estado', 'ofrecida')->count())->toBe(0)
        ->and(Oferta::query()->where('estado', 'retirada')->pluck('motivo_retiro')->map(fn ($m) => $m->value)->unique()->all())->toBe(['cancelada'])
        ->and(app(RetirarOfertas::class)->porCancelacion($solicitud))->toBe(0);

    // Con la solicitud cancelada, el proceso de vencimientos no abre otra ronda.
    motorPasaElTiempo('2026-12-02 20:00:00');
    expect(Ronda::query()->where('ronda', 3)->exists())->toBeFalse();
});

// ── Asignación a mano ────────────────────────────────────────────────────────

test('un administrador asigna a mano una solicitud sin proveedor, con motivo, y queda en el historial', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    motorPasaElTiempo('2026-12-02 12:00:01');
    motorPasaElTiempo('2026-12-02 14:00:02');
    motorPasaElTiempo('2026-12-02 16:00:03');
    expect(motorEstado($solicitud))->toBe('sin_proveedor');

    $oferta = app(ForzarAsignacion::class)->ejecutar(77, $solicitud, 3, null, 'Llamé a Cruz y aceptó por teléfono');

    expect(motorEstado($solicitud))->toBe('asignada')
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('proveedor_id'))->toBe(3)
        ->and($oferta->origen->value)->toBe('administrador')
        ->and($oferta->estado->value)->toBe('aceptada')
        ->and($oferta->ciclo)->toBe(2);

    $paso = DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitud)->where('estado_nuevo', 'asignada')->first();

    expect($paso->origen)->toBe('administrador')
        ->and($paso->actor_usuario_id)->toBe(77)
        ->and($paso->motivo)->toBe('Llamé a Cruz y aceptó por teléfono');

    Event::assertDispatched(SolicitudAsignada::class, fn (SolicitudAsignada $e): bool => $e->origen === 'administrador' && $e->proveedorId === 3);
});

test('reasignar una solicitud ya asignada retira al proveedor anterior sin penalidad', function () {
    $mundo = motorMundo();
    [$solicitud] = motorAsignada($mundo);

    app(ForzarAsignacion::class)->ejecutar(77, $solicitud, 2, null, 'El equipo de Agrocom no llega');

    expect(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('proveedor_id'))->toBe(2)
        ->and(Oferta::query()->where('proveedor_id', 1)->first()->estado->value)->toBe('retirada')
        ->and(Oferta::query()->where('proveedor_id', 1)->first()->motivo_retiro->value)->toBe('reasignada')
        ->and(CancelacionDeProveedor::query()->count())->toBe(0);
});

test('asignar a mano exige motivo, un proveedor aprobado y un equipo apto y libre', function () {
    $mundo = motorMundo([4 => ['estado' => 'suspendido']], [1 => [11], 2 => [], 3 => [13], 4 => [14]]);
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);

    expect(fn () => app(ForzarAsignacion::class)->ejecutar(77, $solicitud, 3, null, ' '))->toThrow(AsignacionInvalida::class)
        ->and(fn () => app(ForzarAsignacion::class)->ejecutar(77, $solicitud, 4, null, 'x1'))->toThrow(AsignacionInvalida::class)
        ->and(fn () => app(ForzarAsignacion::class)->ejecutar(77, $solicitud, 2, null, 'Sin equipo'))->toThrow(AsignacionInvalida::class)
        ->and(fn () => app(ForzarAsignacion::class)->ejecutar(77, $solicitud, 3, 99, 'Equipo ajeno'))->toThrow(AsignacionInvalida::class)
        ->and(motorEstado($solicitud))->toBe('en_asignacion');
});

test('una solicitud ya ejecutada no se asigna a mano', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo, ['estado' => 'ejecutada', 'proveedor_id' => 1, 'equipo_id' => 11]);

    expect(fn () => app(ForzarAsignacion::class)->ejecutar(77, $solicitud, 2, null, 'Tarde'))->toThrow(AsignacionInvalida::class);
});

// ── Lo que ve el proveedor ───────────────────────────────────────────────────

test('la oferta muestra la zona, un punto aproximado, las hectáreas y el pago; nunca el nombre del terreno ni datos del cliente', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);

    $oferta = app(ListarOfertasDelProveedor::class)->ejecutar(501)[0];

    expect($oferta->segundosRestantes)->toBe(7200)
        ->and($oferta->solicitud->zonaNombre)->toBe('Warnes')
        ->and($oferta->ubicacionAproximada)->toBe(['lat' => '-17.51', 'lng' => '-63.17'])
        ->and($oferta->oferta->pago_estimado_proveedor)->toBe('9600.00');

    $json = (new OfertaResource($oferta))->resolve(request());

    expect($json['servicio'])->not->toHaveKeys(['terreno', 'terreno_nombre', 'cliente', 'telefono'])
        ->and(json_encode($json))->not->toContain('Terreno Ana')->not->toContain('Cliente Ana');
});

test('la lista de vivas deja las que esperan y el historial, las resueltas', function () {
    $mundo = motorMundo();
    $solicitud = motorSolicitud($mundo);
    app(IniciarAsignacion::class)->ejecutar($solicitud);
    $oferta = Oferta::query()->first();

    expect(app(ListarOfertasDelProveedor::class)->ejecutar(501))->toHaveCount(1)
        ->and(app(ListarOfertasDelProveedor::class)->ejecutar(501, 'historial'))->toHaveCount(0);

    app(ResponderOferta::class)->rechazar(501, $oferta->id, 'No puedo');

    expect(app(ListarOfertasDelProveedor::class)->ejecutar(501))->toHaveCount(0)
        ->and(app(ListarOfertasDelProveedor::class)->ejecutar(501, 'historial'))->toHaveCount(1);
});

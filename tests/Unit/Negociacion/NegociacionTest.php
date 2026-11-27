<?php

use App\Dominios\Compartido\Contratos\Eventos\EstadoDeAprobacionCambiado;
use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\CuentaNoAprobada;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Dominio\MontoDelServicio;
use App\Dominios\Flota\Contratos\DatosDron;
use App\Dominios\Flota\Contratos\DatosEquipoApto;
use App\Dominios\Flota\Contratos\DatosIntegrantesEquipo;
use App\Dominios\Flota\Contratos\LecturaDrones;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Negociacion\Aplicacion\CancelarComoProveedor;
use App\Dominios\Negociacion\Aplicacion\ConfirmarPago;
use App\Dominios\Negociacion\Aplicacion\ConfirmarPrecioFinal;
use App\Dominios\Negociacion\Aplicacion\ConsultarOfertas;
use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\SolicitudParaAviso;
use App\Dominios\Negociacion\Aplicacion\Mensajeria;
use App\Dominios\Negociacion\Aplicacion\MercadoDeSolicitudes;
use App\Dominios\Negociacion\Aplicacion\Negociar;
use App\Dominios\Negociacion\Aplicacion\PreferenciasDeAviso;
use App\Dominios\Negociacion\Aplicacion\Presentacion;
use App\Dominios\Negociacion\Aplicacion\ProcesarNegociacion;
use App\Dominios\Negociacion\Aplicacion\Seguimiento;
use App\Dominios\Negociacion\Aplicacion\UbicacionDelPiloto;
use App\Dominios\Negociacion\Contratos\Eventos\AvisoDeNegociacion;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\CompararOfertasRequest;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\ContraofertarRequest;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\OfertarRequest;
use App\Dominios\Negociacion\Infraestructura\Oyentes\RegistrarEventoEnElChat;
use App\Dominios\Proveedores\Contratos\DatosPiloto;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\FotosDePerfil;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Solicitudes\Aplicacion\CambiarEstadoDeCliente;
use App\Dominios\Solicitudes\Aplicacion\CambiarPrecioPropuesto;
use App\Dominios\Solicitudes\Aplicacion\CancelarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\CrearSolicitud;
use App\Dominios\Solicitudes\Aplicacion\GestionarFotosDeTerreno;
use App\Dominios\Solicitudes\Aplicacion\PublicarSolicitud;
use App\Dominios\Solicitudes\Contratos\Eventos\PrecioPropuestoCambiado;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudAbierta;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCancelada;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CambiarPrecioRequest;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CrearSolicitudRequest;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\Soporte\CuentasFalsas;
use Tests\Soporte\Esquema;
use Tests\Soporte\ProveedoresFalsos;
use Tests\Soporte\Ubicaciones;
use Tests\TestCase;

/*
 * El motor de negociación de punta a punta (`negociacion-contrato.md`): oferta, contraoferta por
 * rondas, adjudicación, seguimiento con fotos, chat, ubicación y mapa; permisos y aislamiento. Contra
 * el ESQUEMA REAL de solicitudes y de negociación (las migraciones, en SQLite en memoria), con los
 * casos de uso reales y con los contratos de `Proveedores` y `Flota` reemplazados por datos controlados.
 *
 * Mundo: cliente 900 (cliente 1) y 901 (cliente 2); proveedor 7 (titular 701, piloto 711, equipo 70) y
 * proveedor 8 (titular 702, equipo 80); un terreno de 100 ha en Santa Cruz (departamento y provincia del catálogo).
 */

uses(TestCase::class);

const NEG_CLIENTE = 900;
const NEG_OTRO_CLIENTE = 901;
const NEG_TITULAR_7 = 701;
const NEG_PILOTO_7 = 711;
const NEG_TITULAR_8 = 702;

function negMundo(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'create_sol_solicitud_historial_table',
        'agrega_reputacion_a_sol_clientes',
        'agrega_motivo_estado_a_sol_clientes',
        'lleva_sol_clientes_al_ciclo_de_aprobacion',
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'crea_sol_cliente_contactos',
        'create_neg_tablas_de_negociacion',
        'agrega_disponibilidad_y_filtros_de_proveedor',
        'agrega_adjuntos_y_eventos_a_neg_mensajes',
        'create_neg_pagos_confirmados_table',
        'create_neg_precios_finales_confirmados_table',
        'create_sol_terreno_fotos_table',
        'create_sol_precios_referencia_table',
        'agrega_ubicacion_a_sol_terrenos',
        'agrega_ubicacion_a_sol_solicitudes_servicio',
        'retira_zonas',
        'hace_opcional_la_fecha_deseada_de_la_solicitud',
        'agrega_justificacion_de_precio',
    ]);
    Ubicaciones::sembrar();

    foreach (['sol_cliente_historial_estados', 'neg_precios_finales_confirmados', 'neg_pagos_confirmados', 'sol_terreno_fotos', 'neg_preferencias', 'neg_ubicaciones', 'neg_evidencias', 'neg_avances', 'neg_mensajes', 'neg_cancelaciones', 'neg_ofertas', 'sol_solicitud_historial', 'sol_solicitudes_servicio', 'sol_terrenos', 'sol_clientes'] as $tabla) {
        DB::table($tabla)->delete();
    }

    Event::fake([AvisoDeNegociacion::class, SolicitudAbierta::class, SolicitudCancelada::class, PrecioPropuestoCambiado::class]);
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-12-10 09:00:00'));

    foreach ([1 => [NEG_CLIENTE, 'Cliente Ana', '70011122'], 2 => [NEG_OTRO_CLIENTE, 'Cliente Beto', '70033344']] as $id => [$usuario, $nombre, $telefono]) {
        DB::table('sol_clientes')->insert(['id' => $id, 'usuario_id' => $usuario, 'tipo' => 'persona', 'razon_social' => $nombre, 'telefono' => $telefono, 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);
    }

    DB::table('sol_terrenos')->insert([
        'id' => 1, 'cliente_id' => 1, 'nombre' => 'Lote Norte', 'hectareas' => '100.0000', 'cultivo' => 'Soya', 'acceso_dificil' => false, 'departamento_codigo' => Ubicaciones::DEPARTAMENTO, 'provincia_codigo' => Ubicaciones::PROVINCIA,
        'geometria' => json_encode(['type' => 'Polygon', 'coordinates' => [[[-60.8, -17.8], [-60.7, -17.8], [-60.7, -17.9], [-60.8, -17.9], [-60.8, -17.8]]]]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app()->instance(LecturaProveedores::class, new ProveedoresFalsos(
        [
            7 => new DatosProveedor(7, NEG_TITULAR_7, 'independiente', 'Proveedor Siete', 'aprobado', calificacionPromedio: '4.60', calificacionesConteo: 12),
            8 => new DatosProveedor(8, NEG_TITULAR_8, 'tercero', 'Proveedor Ocho', 'aprobado'),
        ],
        [71 => new DatosPiloto(71, 7, NEG_PILOTO_7, 'Piloto Siete', 'aprobado')],
    ));

    // Las cuentas: ninguna comparte persona con otra, salvo que una prueba lo pida (autocontratación).
    app()->instance(LecturaCuentas::class, new CuentasFalsas);

    app()->instance(LecturaEquipos::class, new class implements LecturaEquipos
    {
        public function aptosDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return [new DatosEquipoApto($proveedorId * 10, $proveedorId, $proveedorId * 10 + 1, $proveedorId * 100, '40.00', '11.00')];
        }

        public function equiposDeCuenta(int $usuarioId, bool $soloVigentes = true, ?DateTimeImmutable $hoy = null): array
        {
            return $usuarioId === NEG_PILOTO_7 ? [70] : [];
        }

        public function tieneEquipoApto(int $proveedorId, ?DateTimeImmutable $hoy = null): bool
        {
            return true;
        }

        public function vigentesDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return [];
        }
    });

    app()->instance(LecturaIntegrantesDeEquipo::class, new class implements LecturaIntegrantesDeEquipo
    {
        public function integrantes(int $equipoId): ?DatosIntegrantesEquipo
        {
            return new DatosIntegrantesEquipo($equipoId, intdiv($equipoId, 10), $equipoId + 1, intdiv($equipoId, 10) * 100, 'externo');
        }
    });

    app()->instance(FotosDePerfil::class, new class implements FotosDePerfil
    {
        public function urlDe(int $usuarioId): ?string
        {
            return $usuarioId === NEG_PILOTO_7 ? "/api/personas/{$usuarioId}/foto?firma=prueba" : null;
        }
    });

    app()->instance(LecturaDrones::class, new class implements LecturaDrones
    {
        public function dron(int $id): ?DatosDron
        {
            return new DatosDron($id, intdiv($id, 100), intdiv($id, 100), 'DJI', 'Agras T40', "SN{$id}", 'aprobado', 2024, '40.00');
        }

        public function fotosAprobadas(int $dronId): array
        {
            return [['id' => 1, 'tipo' => 'frontal']];
        }
    });
}

/** Crea y abre una solicitud del cliente 1 sobre el terreno 1 (80 ha a 125 por hectárea). */
function negAbierta(string $precio = '125', string $hectareas = '80', int $usuario = NEG_CLIENTE, int $terreno = 1, bool $conFecha = true): int
{
    [$solicitud] = app(CrearSolicitud::class)->ejecutar($usuario, [
        'uuid_cliente' => (string) Str::uuid(), 'terreno_id' => $terreno, 'tipo_aplicacion' => 'herbicida', 'hectareas_solicitadas' => $hectareas,
        'producto_indicado' => 'Glifosato', 'precio_ha_propuesto' => $precio,
        ...($conFecha ? ['ventana_desde' => '2026-12-12', 'ventana_hasta' => '2026-12-20'] : []),
    ]);

    return app(PublicarSolicitud::class)->ejecutar($usuario, $solicitud->id)->id;
}

function negEstado(int $solicitudId): string
{
    return (string) DB::table('sol_solicitudes_servicio')->where('id', $solicitudId)->value('estado');
}

function negOfertar(int $usuario, int $solicitudId, string $tipo = 'acepta_propuesta', ?string $precio = null, ?string $uuid = null): Oferta
{
    return app(Negociar::class)->ofertar($usuario, $solicitudId, ['uuid_cliente' => $uuid ?? (string) Str::uuid(), 'tipo' => $tipo, 'precio_ha' => $precio])[0];
}

function negCodigo(callable $accion): string
{
    try {
        $accion();
    } catch (NegociacionInvalida $excepcion) {
        return $excepcion->codigo.':'.$excepcion->estatus;
    }

    return 'sin_error';
}

/** Adjudica la solicitud a la oferta del proveedor 7 y deja el servicio listo para ejecutarse. */
function negAdjudicada(): array
{
    $solicitud = negAbierta();
    $oferta = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '118');
    app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $oferta->id);

    return [$solicitud, $oferta];
}

test('abrir una solicitud exige precio propuesto y teléfono, y la deja visible sin vigencia propia', function () {
    negMundo();

    [$sinPrecio] = app(CrearSolicitud::class)->ejecutar(NEG_CLIENTE, [
        'uuid_cliente' => (string) Str::uuid(), 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida', 'hectareas_solicitadas' => '80',
        'producto_indicado' => 'Glifosato', 'ventana_desde' => '2026-12-12', 'ventana_hasta' => '2026-12-20',
    ]);

    expect(fn () => app(PublicarSolicitud::class)->ejecutar(NEG_CLIENTE, $sinPrecio->id))->toThrow(SolicitudInvalida::class);

    $abierta = negAbierta();
    $fila = DB::table('sol_solicitudes_servicio')->find($abierta);

    expect(negEstado($abierta))->toBe('abierta')
        ->and($fila->precio_ha_propuesto)->toEqual('125.0000')
        ->and($fila->monto_estimado)->toEqual('10000.00')
        ->and($fila->vence_en)->toBeNull();
    Event::assertDispatched(SolicitudAbierta::class, fn (SolicitudAbierta $e): bool => $e->solicitudId === $abierta && $e->hectareas === '80.0000');
});

test('el mapa muestra lotes, precio y estado sin ningún dato privado del cliente', function () {
    negMundo();
    $solicitud = negAbierta();

    $lista = app(MercadoDeSolicitudes::class)->listar(NEG_TITULAR_7, []);
    $detalle = app(MercadoDeSolicitudes::class)->detalle(NEG_TITULAR_7, $solicitud);
    $publico = json_encode([$lista, $detalle], JSON_THROW_ON_ERROR);

    expect($lista['items'])->toHaveCount(1)
        ->and($lista['items'][0]['precio_ha_propuesto'])->toBe('125.0000')
        ->and($lista['items'][0]['departamento_codigo'])->toBe(Ubicaciones::DEPARTAMENTO)
        ->and($lista['items'][0]['provincia_codigo'])->toBe(Ubicaciones::PROVINCIA)
        ->and($lista['items'][0])->not->toHaveKeys(['zona', 'zona_id', 'macrorregion'])
        ->and($lista['items'][0]['centro'])->not->toBeNull()
        ->and($detalle['terreno']['geometria']['type'])->toBe('Polygon')
        ->and($publico)->not->toContain('Cliente Ana')->not->toContain('70011122')->not->toContain('usuario_id');
});

test('el mapa filtra por hectáreas y precio, y ordena por distancia', function () {
    negMundo();
    negAbierta('125', '80');
    negAbierta('90', '20');
    $mercado = app(MercadoDeSolicitudes::class);

    expect($mercado->listar(NEG_TITULAR_7, ['hectareas_min' => '50'])['items'])->toHaveCount(1)
        ->and($mercado->listar(NEG_TITULAR_7, ['precio_ha_max' => '100'])['items'])->toHaveCount(1)
        ->and($mercado->listar(NEG_TITULAR_7, ['bbox' => '-61,-18.5,-60,-17'])['items'])->toHaveCount(2)
        ->and($mercado->listar(NEG_TITULAR_7, ['bbox' => '-70,-30,-65,-25'])['items'])->toHaveCount(0)
        ->and($mercado->listar(NEG_TITULAR_7, ['orden' => 'distancia', 'lat' => -17.8, 'lon' => -60.7])['items'][0]['distancia_km'])->not->toBeNull()
        ->and(fn () => $mercado->listar(NEG_TITULAR_7, ['bbox' => 'x']))->toThrow(NegociacionInvalida::class);
});

test('quien no es un proveedor habilitado no ve el mapa', function () {
    negMundo();
    negAbierta();

    expect(negCodigo(fn () => app(MercadoDeSolicitudes::class)->listar(NEG_CLIENTE, [])))->toBe('proveedor_no_habilitado:403');
});

test('un proveedor oferta el precio propuesto y reintentar el mismo uuid devuelve la misma oferta', function () {
    negMundo();
    $solicitud = negAbierta();
    $uuid = (string) Str::uuid();

    [$oferta, $creada] = app(Negociar::class)->ofertar(NEG_TITULAR_7, $solicitud, ['uuid_cliente' => $uuid, 'tipo' => 'acepta_propuesta']);
    [$misma, $creadaOtraVez] = app(Negociar::class)->ofertar(NEG_TITULAR_7, $solicitud, ['uuid_cliente' => $uuid, 'tipo' => 'acepta_propuesta']);

    expect($creada)->toBeTrue()->and($creadaOtraVez)->toBeFalse()
        ->and($misma->id)->toBe($oferta->id)
        ->and($oferta->numero)->toBe(1)->and($oferta->autor)->toBe('proveedor')->and($oferta->tipo)->toBe('acepta_propuesta')
        ->and($oferta->precio_ha)->toBe('125.0000')->and($oferta->monto_estimado)->toBe('10000.00')
        ->and($oferta->estado->value)->toBe('vigente')
        ->and(negEstado($solicitud))->toBe('con_ofertas')
        ->and(Oferta::query()->count())->toBe(1);
    Event::assertDispatched(AvisoDeNegociacion::class, fn (AvisoDeNegociacion $e): bool => $e->tipo === 'oferta_recibida' && $e->usuarios === [NEG_CLIENTE]);
});

test('el piloto de un proveedor oferta en nombre de su proveedor y no hay dos negociaciones vigentes', function () {
    negMundo();
    $solicitud = negAbierta();

    $oferta = negOfertar(NEG_PILOTO_7, $solicitud);

    expect($oferta->proveedor_id)->toBe(7)
        ->and(negCodigo(fn () => negOfertar(NEG_TITULAR_7, $solicitud)))->toBe('negociacion_vigente:409');
});

test('una contraoferta del proveedor debe tener un precio distinto del propuesto y razonable', function () {
    negMundo();
    $solicitud = negAbierta();

    expect(negCodigo(fn () => negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '125')))->toBe('precio_igual_propuesto:422')
        ->and(negCodigo(fn () => negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '0')))->toBe('precio_invalido:422')
        ->and(negCodigo(fn () => negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '99999')))->toBe('precio_excede_maximo:422');

    $oferta = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '110.5');
    expect($oferta->tipo)->toBe('contraoferta')->and($oferta->precio_ha)->toBe('110.5000')->and($oferta->monto_estimado)->toBe('8840.00');
});

test('un proveedor no oferta sobre su propia cuenta de cliente ni sobre una solicitud ya cerrada', function () {
    negMundo();
    DB::table('sol_clientes')->where('id', 2)->update(['usuario_id' => NEG_TITULAR_8]);
    DB::table('sol_terrenos')->insert(['id' => 2, 'cliente_id' => 2, 'nombre' => 'Lote Sur', 'hectareas' => '50.0000', 'acceso_dificil' => false, 'departamento_codigo' => Ubicaciones::DEPARTAMENTO, 'provincia_codigo' => Ubicaciones::PROVINCIA, 'created_at' => now(), 'updated_at' => now()]);
    $propia = negAbierta('125', '10', NEG_TITULAR_8, 2);
    $cancelada = negAbierta();
    app(CancelarSolicitud::class)->porCliente(NEG_CLIENTE, $cancelada, 'Cambié de idea');

    expect(negCodigo(fn () => negOfertar(NEG_TITULAR_8, $propia)))->toBe('propia_cuenta:422')
        ->and(negCodigo(fn () => negOfertar(NEG_TITULAR_7, $cancelada)))->toBe('solicitud_no_abierta:409')
        ->and(app(MercadoDeSolicitudes::class)->listar(NEG_TITULAR_8, [])['items'])->toHaveCount(0);
});

test('la negociación alterna las partes por rondas y la quinta oferta es definitiva', function () {
    negMundo();
    $solicitud = negAbierta();
    $negociar = app(Negociar::class);

    $o1 = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '120');
    [$o2] = $negociar->contraofertar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $o1->id, '115', 'Mi presupuesto', null);
    [$o3] = $negociar->contraofertar(Negociar::LADO_PROVEEDOR, NEG_TITULAR_7, $o2->id, '117', null, null);
    [$o4] = $negociar->contraofertar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $o3->id, '116', null, null);
    [$o5] = $negociar->contraofertar(Negociar::LADO_PROVEEDOR, NEG_TITULAR_7, $o4->id, '116.5', null, null);

    expect([$o2->autor, $o2->numero])->toBe(['cliente', 2])
        ->and([$o3->autor, $o3->numero])->toBe(['proveedor', 3])
        ->and($o5->numero)->toBe(5)->and($o5->es_definitiva)->toBeTrue()
        ->and($o1->refresh()->estado->value)->toBe('reemplazada')
        ->and($o5->estado->value)->toBe('vigente')
        ->and(Oferta::query()->where('proveedor_id', 7)->where('estado', 'vigente')->count())->toBe(1)
        ->and(negCodigo(fn () => $negociar->contraofertar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $o5->id, '110', null, null)))->toBe('oferta_definitiva:409')
        ->and(negCodigo(fn () => $negociar->contraofertar(Negociar::LADO_PROVEEDOR, NEG_TITULAR_7, $o5->id, '120', null, null)))->toBe('no_es_su_turno:409');
});

test('cada parte solo responde la oferta vigente de la otra', function () {
    negMundo();
    $solicitud = negAbierta();
    $oferta = negOfertar(NEG_TITULAR_7, $solicitud);
    $negociar = app(Negociar::class);

    expect(negCodigo(fn () => $negociar->aceptar(Negociar::LADO_PROVEEDOR, NEG_TITULAR_7, $oferta->id)))->toBe('no_es_su_turno:409')
        ->and(negCodigo(fn () => $negociar->rechazar(Negociar::LADO_PROVEEDOR, NEG_TITULAR_7, $oferta->id)))->toBe('no_es_su_turno:409')
        ->and(negCodigo(fn () => $negociar->retirar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $oferta->id)))->toBe('no_es_su_turno:409');
});

test('aceptar una oferta adjudica la solicitud con el precio acordado y cierra las demás', function () {
    negMundo();
    $solicitud = negAbierta();
    $de7 = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '118');
    $de8 = negOfertar(NEG_TITULAR_8, $solicitud);

    $aceptada = app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $de7->id);
    $fila = DB::table('sol_solicitudes_servicio')->find($solicitud);

    expect($aceptada->estado->value)->toBe('aceptada')
        ->and($de8->refresh()->estado->value)->toBe('cerrada')
        ->and($fila->estado)->toBe('adjudicada')
        ->and($fila->proveedor_id)->toBe(7)->and($fila->equipo_id)->toBe(70)->and($fila->oferta_adjudicada_id)->toBe($de7->id)
        ->and($fila->precio_ha_acordado)->toEqual('118.0000')
        ->and($fila->monto_estimado)->toEqual('9440.00')
        ->and(app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $de7->id)->id)->toBe($de7->id)
        ->and(negCodigo(fn () => app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $de8->id)))->toBe('solicitud_ya_adjudicada:409');
    Event::assertDispatched(AvisoDeNegociacion::class, fn (AvisoDeNegociacion $e): bool => $e->tipo === 'solicitud_adjudicada' && in_array(NEG_CLIENTE, $e->usuarios, true) && in_array(NEG_TITULAR_7, $e->usuarios, true));
});

test('el proveedor acepta la contraoferta del cliente y esa oferta adjudica', function () {
    negMundo();
    $solicitud = negAbierta();
    $o1 = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '120');
    [$o2] = app(Negociar::class)->contraofertar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $o1->id, '112.5', null, null);

    app(Negociar::class)->aceptar(Negociar::LADO_PROVEEDOR, NEG_PILOTO_7, $o2->id);

    expect(negEstado($solicitud))->toBe('adjudicada')
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('precio_ha_acordado'))->toEqual('112.5000');
});

test('el mismo equipo no se adjudica a dos solicitudes con fechas que se cruzan', function () {
    negMundo();
    negAdjudicada();
    $segunda = negAbierta();
    $oferta = negOfertar(NEG_TITULAR_7, $segunda);

    expect(negCodigo(fn () => app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $oferta->id)))->toBe('equipo_no_disponible:409')
        ->and($oferta->refresh()->estado->value)->toBe('vigente')
        ->and(negEstado($segunda))->toBe('con_ofertas');
});

test('rechazar la última oferta reabre la solicitud y el proveedor no vuelve a ofertar; retirar sí lo permite', function () {
    negMundo();
    $solicitud = negAbierta();
    $oferta = negOfertar(NEG_TITULAR_7, $solicitud);

    app(Negociar::class)->rechazar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $oferta->id);

    expect(negEstado($solicitud))->toBe('abierta')
        ->and(negCodigo(fn () => negOfertar(NEG_TITULAR_7, $solicitud)))->toBe('negociacion_cerrada:409');

    $otra = negOfertar(NEG_TITULAR_8, $solicitud);
    app(Negociar::class)->retirar(Negociar::LADO_PROVEEDOR, NEG_TITULAR_8, $otra->id);
    $nueva = negOfertar(NEG_TITULAR_8, $solicitud, 'contraoferta', '100');

    expect($nueva->numero)->toBe(3)->and(negEstado($solicitud))->toBe('con_ofertas');
});

test('una oferta vencida ya no se responde y el proceso programado reabre la solicitud, que no expira nunca', function () {
    negMundo();
    $solicitud = negAbierta();
    $oferta = negOfertar(NEG_TITULAR_7, $solicitud);

    Carbon::setTestNow(Carbon::parse('2026-12-12 10:00:00'));
    expect(negCodigo(fn () => app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $oferta->id)))->toBe('oferta_no_vigente:409');

    $resultado = app(ProcesarNegociacion::class)->ejecutar();
    $repetido = app(ProcesarNegociacion::class)->ejecutar();

    expect($resultado['ofertas_vencidas'])->toBe(1)
        ->and($oferta->refresh()->estado->value)->toBe('vencida')
        ->and(negEstado($solicitud))->toBe('abierta')
        ->and($repetido)->toBe(['ofertas_vencidas' => 0]);

    // Un año después la solicitud sin trato sigue abierta: solo se adjudica o la cancela el cliente.
    Carbon::setTestNow(Carbon::parse('2027-12-12 10:00:00'));
    $cierre = app(ProcesarNegociacion::class)->ejecutar();

    expect($cierre)->toBe(['ofertas_vencidas' => 0])
        ->and(negEstado($solicitud))->toBe('abierta');
});

test('la oferta vence por su propio parámetro y la solicitud no la acota', function () {
    negMundo();
    $solicitud = negAbierta();
    config(['negociacion.vigencia_oferta_horas' => 720]);

    $oferta = negOfertar(NEG_TITULAR_7, $solicitud);

    expect($oferta->refresh()->vence_en->equalTo(Carbon::now()->addHours(720)))->toBeTrue();
});

test('una solicitud sin fecha deseada se ofrece, se negocia y se adjudica igual', function () {
    negMundo();
    $solicitud = negAbierta(conFecha: false);
    $oferta = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '118');

    app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $oferta->id);

    expect(negEstado($solicitud))->toBe('adjudicada')
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('ventana_desde'))->toBeNull();
});

test('cambiar el precio propuesto no toca las ofertas vigentes y avisa a los proveedores', function () {
    negMundo();
    $solicitud = negAbierta();
    $oferta = negOfertar(NEG_TITULAR_7, $solicitud);

    $cambiada = app(CambiarPrecioPropuesto::class)->ejecutar(NEG_CLIENTE, $solicitud, '140');

    expect($cambiada->precio_ha_propuesto)->toBe('140.0000')->and($cambiada->monto_estimado)->toBe('11200.00')
        ->and($oferta->refresh()->precio_ha)->toBe('125.0000');
    Event::assertDispatched(PrecioPropuestoCambiado::class);
});

test('un proveedor solo ve sus ofertas y el cliente solo las de sus solicitudes (aislamiento)', function () {
    negMundo();
    $solicitud = negAbierta();
    $de7 = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '118');
    negOfertar(NEG_TITULAR_8, $solicitud, 'contraoferta', '99');
    $consultar = app(ConsultarOfertas::class);

    $propias = $consultar->delProveedor(NEG_TITULAR_7);
    $comparador = $consultar->delCliente(NEG_CLIENTE, $solicitud);

    expect($propias)->toHaveCount(1)->and($propias[0]['precio_ha'])->toBe('118.0000')
        ->and(json_encode($propias))->not->toContain('99.0000')
        ->and(negCodigo(fn () => $consultar->una(Negociar::LADO_PROVEEDOR, NEG_TITULAR_8, $de7->id)))->toBe('oferta_ajena:404')
        ->and(negCodigo(fn () => $consultar->una(Negociar::LADO_CLIENTE, NEG_OTRO_CLIENTE, $de7->id)))->toBe('oferta_ajena:404')
        ->and(negCodigo(fn () => $consultar->delCliente(NEG_OTRO_CLIENTE, $solicitud)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_OTRO_CLIENTE, $de7->id)))->toBe('oferta_ajena:404')
        ->and($comparador)->toHaveCount(2)
        ->and($comparador[0]['proveedor']['dron']['modelo'])->toBe('Agras T40')
        ->and($comparador[0]['proveedor']['dron']['fotos'][0]['tipo'])->toBe('frontal')
        ->and($comparador[0]['proveedor']['calificacion_promedio'])->not->toBeNull();
});

test('las ofertas del proveedor traen el departamento y el lote solo cuando la solicitud quedó adjudicada a él', function () {
    negMundo();
    $solicitud = negAbierta();
    $de7 = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '118');
    $consultar = app(ConsultarOfertas::class);

    $antes = $consultar->delProveedor(NEG_TITULAR_7)[0];
    app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $de7->id);
    $despues = $consultar->delProveedor(NEG_TITULAR_7)[0];
    $detalle = $consultar->una(Negociar::LADO_PROVEEDOR, NEG_TITULAR_7, $de7->id);

    expect($antes)->toHaveKeys(['departamento_codigo', 'lote'])->not->toHaveKeys(['zona', 'zona_id'])
        ->and($antes['departamento_codigo'])->toBe(Ubicaciones::DEPARTAMENTO)
        ->and($antes['lote'])->toBeNull()
        ->and($despues['lote'])->toBe(DB::table('sol_terrenos')->where('id', 1)->value('nombre'))
        ->and($despues['departamento_codigo'])->toBe($antes['departamento_codigo'])
        ->and($detalle['lote'])->toBe($despues['lote'])
        ->and(json_encode($consultar->delCliente(NEG_CLIENTE, $solicitud)))->not->toContain('"lote"');
});

test('el contacto del cliente no aparece antes de adjudicar y la solicitud ajena responde 404 en seguimiento', function () {
    negMundo();
    $solicitud = negAbierta();
    negOfertar(NEG_TITULAR_7, $solicitud);

    expect(negCodigo(fn () => app(Seguimiento::class)->consultar(NEG_TITULAR_7, $solicitud)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => app(Mensajeria::class)->listar(NEG_TITULAR_7, $solicitud, null, 50)))->toBe('solicitud_ajena:404');
});

test('el seguimiento es idempotente: aplicar el mismo registro varias veces deja la base idéntica', function () {
    negMundo();
    [$solicitud] = negAdjudicada();
    $seguimiento = app(Seguimiento::class);
    $inicio = (string) Str::uuid();
    $avance = (string) Str::uuid();
    $cierre = (string) Str::uuid();

    foreach ([1, 2, 3] as $vez) {
        $seguimiento->iniciar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => $inicio]);
        $seguimiento->avanzar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => $avance, 'hectareas_ejecutadas' => '40']);
        $seguimiento->cerrar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => $cierre, 'hectareas_ejecutadas' => '78.5']);
    }

    $fila = DB::table('sol_solicitudes_servicio')->find($solicitud);

    expect(DB::table('neg_avances')->where('solicitud_id', $solicitud)->count())->toBe(3)
        ->and($fila->estado)->toBe('en_ejecucion')
        ->and($fila->hectareas_ejecutadas)->toEqual('78.5000')
        ->and($fila->cierre_solicitado_en)->not->toBeNull()
        ->and($fila->monto_final)->toBeNull();
});

test('el cierre exige la foto mínima y finaliza al llegar, en cualquier orden, con el monto exacto', function () {
    negMundo();
    [$solicitud] = negAdjudicada();
    $seguimiento = app(Seguimiento::class);
    $seguimiento->iniciar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid()]);
    $avance = (string) Str::uuid();
    $seguimiento->avanzar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => $avance, 'porcentaje' => '50']);

    expect(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('hectareas_ejecutadas'))->toEqual('40.0000');

    $seguimiento->cerrar(NEG_TITULAR_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'hectareas_ejecutadas' => '78.5']);
    expect(negEstado($solicitud))->toBe('en_ejecucion');

    $uuidFoto = (string) Str::uuid();
    [$evidencia, $creada] = $seguimiento->subirEvidencia(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => $uuidFoto, 'tipo' => 'foto_avance', 'avance_uuid_cliente' => $avance], UploadedFile::fake()->image('lote.jpg')->size(120));
    [$mismaFoto, $creadaOtraVez] = $seguimiento->subirEvidencia(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => $uuidFoto, 'tipo' => 'foto_avance'], UploadedFile::fake()->image('lote.jpg')->size(120));
    $fila = DB::table('sol_solicitudes_servicio')->find($solicitud);

    expect($creada)->toBeTrue()->and($creadaOtraVez)->toBeFalse()->and($mismaFoto->id)->toBe($evidencia->id)
        ->and($fila->estado)->toBe('finalizada')
        ->and($fila->hectareas_ejecutadas)->toEqual('78.5000')
        ->and($fila->monto_final)->toEqual('9263.00')
        ->and(DB::table('neg_evidencias')->count())->toBe(1);
    Storage::disk('local')->assertExists($evidencia->archivo);
});

test('el avance respeta la tolerancia y exige motivo para bajar; fotos de 300 KB o más se rechazan', function () {
    negMundo();
    [$solicitud] = negAdjudicada();
    $seguimiento = app(Seguimiento::class);
    $seguimiento->iniciar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid()]);
    $seguimiento->avanzar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'hectareas_ejecutadas' => '60']);

    expect(negCodigo(fn () => $seguimiento->avanzar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'hectareas_ejecutadas' => '85'])))->toBe('hectareas_exceden_tolerancia:422')
        ->and(negCodigo(fn () => $seguimiento->avanzar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'hectareas_ejecutadas' => '50'])))->toBe('correccion_sin_motivo:422')
        ->and(negCodigo(fn () => $seguimiento->avanzar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'hectareas_ejecutadas' => '50', 'motivo_correccion' => 'Me equivoqué al marcar'])))->toBe('sin_error')
        ->and(negCodigo(fn () => $seguimiento->avanzar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'hectareas_ejecutadas' => '10', 'porcentaje' => '10'])))->toBe('avance_requiere_uno:422')
        ->and(negCodigo(fn () => $seguimiento->subirEvidencia(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'tipo' => 'foto_avance'], UploadedFile::fake()->image('g.jpg')->size(301))))->toBe('evidencia_muy_grande:422');
});

test('solo la cuenta del equipo adjudicado registra seguimiento', function () {
    negMundo();
    [$solicitud] = negAdjudicada();

    expect(negCodigo(fn () => app(Seguimiento::class)->iniciar(NEG_TITULAR_8, $solicitud, ['uuid_cliente' => (string) Str::uuid()])))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => app(Seguimiento::class)->iniciar(NEG_CLIENTE, $solicitud, ['uuid_cliente' => (string) Str::uuid()])))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => app(Seguimiento::class)->avanzar(NEG_TITULAR_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'porcentaje' => '10'])))->toBe('estado_invalido:409');
});

test('la ubicación solo se acepta en ejecución, la ve solo el cliente adjudicado y se elimina al finalizar', function () {
    negMundo();
    [$solicitud] = negAdjudicada();
    $ubicacion = app(UbicacionDelPiloto::class);

    expect(negCodigo(fn () => $ubicacion->registrar(NEG_PILOTO_7, $solicitud, -17.8, -60.7, 12.0, null)))->toBe('ubicacion_no_permitida:409')
        ->and($ubicacion->delCliente(NEG_CLIENTE, $solicitud))->toBeNull();

    $seguimiento = app(Seguimiento::class);
    $seguimiento->iniciar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid()]);
    $ubicacion->registrar(NEG_PILOTO_7, $solicitud, -17.8, -60.7, 12.0, null);
    $ubicacion->registrar(NEG_PILOTO_7, $solicitud, -17.81, -60.71, 9.0, null);

    $vista = $ubicacion->delCliente(NEG_CLIENTE, $solicitud);

    expect(DB::table('neg_ubicaciones')->count())->toBe(1)
        ->and($vista['latitud'])->toBe(-17.81)->and($vista['vigente'])->toBeTrue()
        ->and(negCodigo(fn () => $ubicacion->delCliente(NEG_OTRO_CLIENTE, $solicitud)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => $ubicacion->delCliente(NEG_TITULAR_7, $solicitud)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => $ubicacion->registrar(NEG_TITULAR_8, $solicitud, 1.0, 1.0, null, null)))->toBe('solicitud_ajena:404');

    Carbon::setTestNow(Carbon::now()->addMinutes(6));
    expect($ubicacion->delCliente(NEG_CLIENTE, $solicitud)['vigente'])->toBeFalse();

    $seguimiento->cerrar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'hectareas_ejecutadas' => '80']);
    $seguimiento->subirEvidencia(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'tipo' => 'foto_final'], UploadedFile::fake()->image('f.jpg')->size(90));

    expect(negEstado($solicitud))->toBe('finalizada')
        ->and(DB::table('neg_ubicaciones')->count())->toBe(0)
        ->and($ubicacion->delCliente(NEG_CLIENTE, $solicitud))->toBeNull();
});

test('el chat entrega por cursor, es idempotente y solo lo usan las dos partes', function () {
    negMundo();
    [$solicitud] = negAdjudicada();
    $chat = app(Mensajeria::class);
    $uuid = (string) Str::uuid();

    [$m1, $creado] = $chat->enviar(NEG_CLIENTE, $solicitud, $uuid, 'Hola, ¿cuándo llegan?');
    [$mismo, $reintento] = $chat->enviar(NEG_CLIENTE, $solicitud, $uuid, 'Hola, ¿cuándo llegan?');
    [$m2] = $chat->enviar(NEG_PILOTO_7, $solicitud, (string) Str::uuid(), 'El lunes temprano.');

    $todo = $chat->listar(NEG_CLIENTE, $solicitud, null, 50);
    $nuevos = $chat->listar(NEG_CLIENTE, $solicitud, $m1->id, 50);

    expect($creado)->toBeTrue()->and($reintento)->toBeFalse()->and($mismo->id)->toBe($m1->id)
        ->and($todo['mensajes'])->toHaveCount(2)->and($todo['cursor'])->toBe($m2->id)
        ->and($nuevos['mensajes'])->toHaveCount(1)->and($nuevos['mensajes'][0]->texto)->toBe('El lunes temprano.')
        ->and($chat->marcarLeidos(NEG_CLIENTE, $solicitud, $m2->id))->toBe(1)
        ->and(negCodigo(fn () => $chat->enviar(NEG_CLIENTE, $solicitud, (string) Str::uuid(), str_repeat('x', 1001))))->toBe('mensaje_invalido:422')
        ->and(negCodigo(fn () => $chat->enviar(NEG_TITULAR_8, $solicitud, (string) Str::uuid(), 'Intruso')))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => $chat->enviar(NEG_OTRO_CLIENTE, $solicitud, (string) Str::uuid(), 'Intruso')))->toBe('solicitud_ajena:404');
    Event::assertDispatched(AvisoDeNegociacion::class, fn (AvisoDeNegociacion $e): bool => $e->tipo === 'mensaje_nuevo' && $e->usuarios === [NEG_TITULAR_7, NEG_PILOTO_7]);
});

test('el chat admite fotos con las reglas de las evidencias: peso máximo, disco privado, idempotencia y solo las dos partes', function () {
    negMundo();
    [$solicitud] = negAdjudicada();
    $chat = app(Mensajeria::class);
    $uuid = (string) Str::uuid();

    [$foto, $creada] = $chat->enviarFoto(NEG_CLIENTE, $solicitud, $uuid, UploadedFile::fake()->image('lote.jpg')->size(120), '  Así está el lote ');
    [$mismo, $reintento] = $chat->enviarFoto(NEG_CLIENTE, $solicitud, $uuid, UploadedFile::fake()->image('lote.jpg')->size(120), null);

    expect($creada)->toBeTrue()->and($reintento)->toBeFalse()->and($mismo->id)->toBe($foto->id)
        ->and($foto->tipo)->toBe('foto')->and($foto->texto)->toBe('Así está el lote')->and($foto->bytes)->toBeGreaterThan(0)
        ->and($foto->hash)->toHaveLength(64)
        ->and($foto->archivo)->toStartWith("mensajes/{$solicitud}/");
    Storage::disk('local')->assertExists($foto->archivo);

    $presentado = Presentacion::mensaje($foto, NEG_CLIENTE);
    expect($presentado['tipo'])->toBe('foto')->and($presentado['url'])->toBe("/api/mensajes/{$foto->id}/archivo")->and($presentado['propio'])->toBeTrue()
        ->and(Presentacion::mensaje($foto, NEG_TITULAR_7)['propio'])->toBeFalse()
        ->and($chat->archivoDeFoto(NEG_PILOTO_7, $foto->id)[1])->toContain($foto->archivo)
        ->and(negCodigo(fn () => $chat->archivoDeFoto(NEG_TITULAR_8, $foto->id)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => $chat->archivoDeFoto(NEG_CLIENTE, 99999)))->toBe('evidencia_ajena:404')
        ->and(negCodigo(fn () => $chat->archivoDeFoto(NEG_OTRO_CLIENTE, $foto->id)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => $chat->enviarFoto(NEG_CLIENTE, $solicitud, (string) Str::uuid(), UploadedFile::fake()->image('grande.jpg')->size(301), null)))->toBe('evidencia_muy_grande:422')
        ->and(negCodigo(fn () => $chat->enviarFoto(NEG_CLIENTE, $solicitud, (string) Str::uuid(), UploadedFile::fake()->image('x.jpg')->size(50), str_repeat('x', 281))))->toBe('mensaje_invalido:422')
        ->and(negCodigo(fn () => $chat->enviarFoto(NEG_OTRO_CLIENTE, $solicitud, (string) Str::uuid(), UploadedFile::fake()->image('x.jpg')->size(50), null)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => $chat->enviarFoto(NEG_PILOTO_7, $solicitud, $uuid, UploadedFile::fake()->image('x.jpg')->size(50), null)))->toBe('uuid_en_uso:422')
        ->and($chat->listar(NEG_TITULAR_7, $solicitud, null, 50)['mensajes'])->toHaveCount(1);
    Event::assertDispatched(AvisoDeNegociacion::class, fn (AvisoDeNegociacion $e): bool => $e->tipo === 'mensaje_nuevo' && $e->usuarios === [NEG_TITULAR_7, NEG_PILOTO_7]);
});

test('los hechos de la solicitud dejan una línea de sistema en el chat, una sola vez, sin autor y sin marcarse como leída', function () {
    negMundo();
    [$solicitud] = negAdjudicada();
    $chat = app(Mensajeria::class);
    $oyente = app(RegistrarEventoEnElChat::class);
    $aviso = new AvisoDeNegociacion('ejecucion_iniciada', "ejecucion_iniciada:{$solicitud}", ['solicitud' => $solicitud], $solicitud, [NEG_CLIENTE]);

    $oyente->handle($aviso);
    $oyente->handle($aviso);
    $oyente->handle(new AvisoDeNegociacion('solicitud_finalizada', "solicitud_finalizada:{$solicitud}", ['solicitud' => $solicitud, 'monto' => '9263.00'], $solicitud, [NEG_CLIENTE]));
    $oyente->handle(new AvisoDeNegociacion('mensaje_nuevo', 'mensaje_nuevo:1', ['solicitud' => $solicitud], $solicitud, [NEG_CLIENTE]));
    $chat->enviar(NEG_CLIENTE, $solicitud, (string) Str::uuid(), 'Gracias');

    $pagina = $chat->listar(NEG_CLIENTE, $solicitud, null, 50);
    [$inicio, $fin, $texto] = $pagina['mensajes'];

    expect($pagina['mensajes'])->toHaveCount(3)
        ->and($inicio->tipo)->toBe('sistema')->and($inicio->evento)->toBe('servicio_iniciado')->and($inicio->autor_rol)->toBe('sistema')
        ->and($inicio->autor_usuario_id)->toBeNull()->and($inicio->texto)->toBe('El piloto inició el servicio.')
        ->and($fin->evento)->toBe('servicio_finalizado')->and($fin->texto)->toBe('El piloto finalizó el servicio. Precio final: 9263.00.')
        ->and(Presentacion::mensaje($inicio, NEG_CLIENTE))->toMatchArray(['tipo' => 'sistema', 'evento' => 'servicio_iniciado', 'propio' => false, 'url' => null])
        ->and($texto->tipo)->toBe('texto')
        ->and($chat->marcarLeidos(NEG_TITULAR_7, $solicitud, $texto->id))->toBe(1)
        ->and(DB::table('neg_mensajes')->where('tipo', 'sistema')->whereNotNull('leido_en')->count())->toBe(0);
});

test('el chat pasa a solo lectura al cerrarse el plazo de calificación', function () {
    negMundo();
    [$solicitud] = negAdjudicada();
    DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->update(['estado' => 'finalizada', 'finalizada_en' => Carbon::now()->subDays(8)]);

    expect(app(Mensajeria::class)->listar(NEG_CLIENTE, $solicitud, null, 50)['solo_lectura'])->toBeTrue()
        ->and(negCodigo(fn () => app(Mensajeria::class)->enviar(NEG_CLIENTE, $solicitud, (string) Str::uuid(), 'Tarde')))->toBe('chat_cerrado:409');

    DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->update(['finalizada_en' => Carbon::now()->subDays(2)]);
    expect(app(Mensajeria::class)->listar(NEG_CLIENTE, $solicitud, null, 50)['solo_lectura'])->toBeFalse();
});

test('el proveedor adjudicado devuelve la solicitud con motivo y no vuelve a ofertar; el cliente cancela y queda registrado', function () {
    negMundo();
    [$solicitud, $oferta] = negAdjudicada();

    expect(negCodigo(fn () => app(CancelarComoProveedor::class)->ejecutar(NEG_TITULAR_7, $solicitud, '  ')))->toBe('motivo_requerido:422')
        ->and(negCodigo(fn () => app(CancelarComoProveedor::class)->ejecutar(NEG_TITULAR_8, $solicitud, 'No me toca')))->toBe('solicitud_ajena:404');

    app(CancelarComoProveedor::class)->ejecutar(NEG_TITULAR_7, $solicitud, 'El dron quedó en mantenimiento');
    $fila = DB::table('sol_solicitudes_servicio')->find($solicitud);

    expect($fila->estado)->toBe('abierta')->and($fila->proveedor_id)->toBeNull()->and($fila->precio_ha_acordado)->toBeNull()
        ->and($oferta->refresh()->estado->value)->toBe('cerrada')
        ->and(DB::table('neg_cancelaciones')->where('solicitud_id', $solicitud)->value('parte'))->toBe('proveedor')
        ->and(negCodigo(fn () => negOfertar(NEG_TITULAR_7, $solicitud)))->toBe('negociacion_cerrada:409');

    $otra = negOfertar(NEG_TITULAR_8, $solicitud);
    app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $otra->id);
    expect(negEstado($solicitud))->toBe('adjudicada');
});

test('las preferencias activan, filtran por hectáreas y apagan los avisos de nuevas solicitudes', function () {
    negMundo();
    $preferencias = app(PreferenciasDeAviso::class);
    $solicitud = fn (string $hectareas, ?string $departamento = null, ?string $tipo = null) => new SolicitudParaAviso($hectareas, $departamento, $tipo);

    expect($preferencias->de(NEG_TITULAR_7)['nuevas_solicitudes']['activo'])->toBeTrue()
        ->and($preferencias->coincide(NEG_TITULAR_7, $solicitud('10')))->toBeTrue();

    $preferencias->guardar(NEG_TITULAR_7, ['nuevas_solicitudes' => ['hectareas_minimas' => '50', 'departamentos_excluidos' => ['09']]]);

    expect($preferencias->coincide(NEG_TITULAR_7, $solicitud('49')))->toBeFalse()
        ->and($preferencias->coincide(NEG_TITULAR_7, $solicitud('50', '07')))->toBeTrue()
        ->and($preferencias->coincide(NEG_TITULAR_7, $solicitud('500', '09')))->toBeFalse()
        ->and($preferencias->de(NEG_TITULAR_7)['nuevas_solicitudes']['hectareas_minimas'])->toBe('50.0000')
        ->and(negCodigo(fn () => $preferencias->guardar(NEG_TITULAR_7, ['nuevas_solicitudes' => ['macrorregiones' => ['valles']]])))->toBe('filtro_desconocido:422');

    $preferencias->guardar(NEG_TITULAR_7, ['nuevas_solicitudes' => ['activo' => false], 'mensajes' => false]);

    expect($preferencias->coincide(NEG_TITULAR_7, $solicitud('500')))->toBeFalse()
        ->and($preferencias->permite(NEG_TITULAR_7, 'mensajes'))->toBeFalse()
        ->and($preferencias->permite(NEG_TITULAR_7, 'ofertas'))->toBeTrue();
});

test('los montos y las hectáreas son decimales exactos con redondeo a 2 decimales mitad hacia arriba', function () {
    expect(MontoDelServicio::monto('78.5000', '118.0000'))->toBe('9263.00')
        ->and(MontoDelServicio::monto('0.3333', '1.5'))->toBe('0.50')
        ->and(MontoDelServicio::monto('33.3333', '10.0050'))->toBe('333.50')
        ->and(MontoDelServicio::monto('0.1', '0.2'))->toBe('0.02')
        ->and(MontoDelServicio::tope('80', '5'))->toBe('84.0000')
        ->and(MontoDelServicio::porcentaje('40', '80'))->toBe('50.00')
        ->and(MontoDelServicio::porcentaje('90', '80'))->toBe('100.00')
        ->and(MontoDelServicio::hectareasDePorcentaje('33.33', '80'))->toBe('26.6640');
});

/** Deja la solicitud adjudicada al proveedor 7 finalizada con 78,5 ha (monto final 9263.00). */
function negFinalizada(): int
{
    [$solicitud] = negAdjudicada();
    $seguimiento = app(Seguimiento::class);
    $seguimiento->iniciar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid()]);
    $seguimiento->cerrar(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'hectareas_ejecutadas' => '78.5']);
    $seguimiento->subirEvidencia(NEG_PILOTO_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'tipo' => 'foto_final'], UploadedFile::fake()->image('f.jpg')->size(90));

    return $solicitud;
}

test('el titular informa el pago de un servicio finalizado: monto del servidor, idempotente y sin cambiar el estado', function () {
    negMundo();
    $solicitud = negFinalizada();
    $pagos = app(ConfirmarPago::class);
    $uuid = (string) Str::uuid();

    [$pago, $creado] = $pagos->ejecutar(NEG_TITULAR_7, $solicitud, ['uuid_cliente' => $uuid, 'metodo' => 'transferencia', 'nota' => 'Llegó a la cuenta']);
    [$mismo, $creadoOtraVez] = $pagos->ejecutar(NEG_TITULAR_7, $solicitud, ['uuid_cliente' => $uuid, 'metodo' => 'efectivo']);

    expect($creado)->toBeTrue()->and($creadoOtraVez)->toBeFalse()->and($mismo->id)->toBe($pago->id)
        ->and($pago->monto)->toBe('9263.00')
        ->and($mismo->metodo)->toBe('transferencia')
        ->and(DB::table('neg_pagos_confirmados')->count())->toBe(1)
        ->and(negEstado($solicitud))->toBe('finalizada')
        ->and(negCodigo(fn () => $pagos->ejecutar(NEG_TITULAR_7, $solicitud, ['uuid_cliente' => (string) Str::uuid()])))->toBe('pago_ya_confirmado:409');
});

test('el pago lo escribe solo el titular del proveedor adjudicado y lo ven las dos partes', function () {
    negMundo();
    $solicitud = negFinalizada();
    $pagos = app(ConfirmarPago::class);
    $datos = ['uuid_cliente' => (string) Str::uuid()];

    expect(negCodigo(fn () => $pagos->ejecutar(NEG_CLIENTE, $solicitud, $datos)))->toBe('pago_solo_proveedor:403')
        ->and(negCodigo(fn () => $pagos->ejecutar(NEG_PILOTO_7, $solicitud, $datos)))->toBe('pago_solo_titular:403')
        ->and(negCodigo(fn () => $pagos->ejecutar(NEG_TITULAR_8, $solicitud, $datos)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => $pagos->ejecutar(NEG_OTRO_CLIENTE, $solicitud, $datos)))->toBe('solicitud_ajena:404')
        ->and($pagos->ver(NEG_CLIENTE, $solicitud))->toBeNull();

    $pagos->ejecutar(NEG_TITULAR_7, $solicitud, $datos);

    expect($pagos->ver(NEG_CLIENTE, $solicitud)->monto)->toBe('9263.00')
        ->and($pagos->ver(NEG_PILOTO_7, $solicitud)->solicitud_id)->toBe($solicitud)
        ->and(negCodigo(fn () => $pagos->ver(NEG_TITULAR_8, $solicitud)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => $pagos->ver(NEG_OTRO_CLIENTE, $solicitud)))->toBe('solicitud_ajena:404');
});

test('el pago no se informa antes de finalizar el servicio', function () {
    negMundo();
    [$solicitud] = negAdjudicada();

    expect(negCodigo(fn () => app(ConfirmarPago::class)->ejecutar(NEG_TITULAR_7, $solicitud, ['uuid_cliente' => (string) Str::uuid()])))->toBe('estado_invalido:409')
        ->and(DB::table('neg_pagos_confirmados')->count())->toBe(0);
});

test('el cliente confirma el precio final tras el cierre del piloto: valores del servidor, idempotente, sin cambiar el estado y con aviso al proveedor', function () {
    negMundo();
    $solicitud = negFinalizada();
    $precios = app(ConfirmarPrecioFinal::class);

    expect($precios->ver(NEG_CLIENTE, $solicitud)['confirmacion'])->toBeNull();

    [$precio, $creado] = $precios->ejecutar(NEG_CLIENTE, $solicitud);
    [$mismo, $creadoOtraVez] = $precios->ejecutar(NEG_CLIENTE, $solicitud);

    expect($creado)->toBeTrue()->and($creadoOtraVez)->toBeFalse()
        ->and($precio['monto_final'])->toBe('9263.00')->and($precio['confirmacion']->monto)->toBe('9263.00')
        ->and($precio['confirmacion']->id)->toBe($mismo['confirmacion']->id)
        ->and((float) $precio['hectareas_ejecutadas'])->toBe(78.5)->and((float) $precio['precio_ha_acordado'])->toBe(118.0)
        ->and(DB::table('neg_precios_finales_confirmados')->count())->toBe(1)
        ->and(negEstado($solicitud))->toBe('finalizada')
        ->and($precios->ver(NEG_TITULAR_7, $solicitud)['confirmacion']->id)->toBe($precio['confirmacion']->id);
    expect(Event::dispatched(AvisoDeNegociacion::class, fn (AvisoDeNegociacion $e): bool => $e->tipo === 'precio_final_confirmado')->count())->toBe(1);
    Event::assertDispatched(AvisoDeNegociacion::class, fn (AvisoDeNegociacion $e): bool => $e->tipo === 'precio_final_confirmado' && $e->usuarios === [NEG_TITULAR_7, NEG_PILOTO_7]);
});

test('el precio final lo confirma solo el cliente dueño y solo con el servicio cerrado por el piloto', function () {
    negMundo();
    $solicitud = negFinalizada();
    $precios = app(ConfirmarPrecioFinal::class);

    expect(negCodigo(fn () => $precios->ejecutar(NEG_TITULAR_7, $solicitud)))->toBe('precio_final_solo_cliente:403')
        ->and(negCodigo(fn () => $precios->ejecutar(NEG_PILOTO_7, $solicitud)))->toBe('precio_final_solo_cliente:403')
        ->and(negCodigo(fn () => $precios->ejecutar(NEG_OTRO_CLIENTE, $solicitud)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => $precios->ejecutar(NEG_TITULAR_8, $solicitud)))->toBe('solicitud_ajena:404')
        ->and(negCodigo(fn () => $precios->ver(NEG_OTRO_CLIENTE, $solicitud)))->toBe('solicitud_ajena:404')
        ->and(DB::table('neg_precios_finales_confirmados')->count())->toBe(0);
});

test('el precio final no se confirma antes de que el piloto cierre el servicio', function () {
    negMundo();
    [$solicitud] = negAdjudicada();
    $precios = app(ConfirmarPrecioFinal::class);

    expect(negCodigo(fn () => $precios->ejecutar(NEG_CLIENTE, $solicitud)))->toBe('estado_invalido:409')
        ->and($precios->ver(NEG_CLIENTE, $solicitud))->toBeNull()
        ->and(DB::table('neg_precios_finales_confirmados')->count())->toBe(0);
});

test('el cliente sube fotos de su terreno, el piloto las ve con URL firmada y un tercero no las toca', function () {
    negMundo();
    $fotos = app(GestionarFotosDeTerreno::class);
    $solicitud = negAbierta();

    $foto = $fotos->subir(NEG_CLIENTE, 1, UploadedFile::fake()->image('borde.jpg')->size(120), '  Borde norte con maleza ');
    $detalle = app(MercadoDeSolicitudes::class)->detalle(NEG_TITULAR_7, $solicitud);

    expect($foto->descripcion)->toBe('Borde norte con maleza')
        ->and($foto->bytes)->toBeGreaterThan(0)
        ->and($detalle['terreno']['fotos'])->toHaveCount(1)
        ->and($detalle['terreno']['fotos'][0]['url'])->toContain("/api/terrenos/fotos/{$foto->id}/archivo")->toContain('signature=')->toContain('expires=')
        ->and($fotos->ruta($foto->id))->not->toBeNull()
        ->and(count($fotos->listar(NEG_CLIENTE, 1)))->toBe(1);
    Storage::disk('local')->assertExists($foto->archivo);

    expect(fn () => $fotos->subir(NEG_OTRO_CLIENTE, 1, UploadedFile::fake()->image('x.jpg')->size(50), null))->toThrow(SolicitudInvalida::class)
        ->and(fn () => $fotos->listar(NEG_OTRO_CLIENTE, 1))->toThrow(SolicitudInvalida::class)
        ->and(fn () => $fotos->borrar(NEG_OTRO_CLIENTE, 1, $foto->id))->toThrow(SolicitudInvalida::class)
        ->and(DB::table('sol_terreno_fotos')->whereNull('deleted_at')->count())->toBe(1);

    $fotos->borrar(NEG_CLIENTE, 1, $foto->id);
    expect(DB::table('sol_terreno_fotos')->whereNull('deleted_at')->count())->toBe(0)
        ->and($fotos->ruta($foto->id))->toBeNull();
});

test('un terreno admite un máximo de fotos', function () {
    negMundo();
    config(['solicitudes.fotos_terreno.maximo_por_terreno' => 2]);
    $fotos = app(GestionarFotosDeTerreno::class);

    $fotos->subir(NEG_CLIENTE, 1, UploadedFile::fake()->image('a.jpg')->size(40), null);
    $fotos->subir(NEG_CLIENTE, 1, UploadedFile::fake()->image('b.jpg')->size(40), null);

    expect(fn () => $fotos->subir(NEG_CLIENTE, 1, UploadedFile::fake()->image('c.jpg')->size(40), null))->toThrow(SolicitudInvalida::class);
});

test('el cliente ve la foto de perfil del piloto del equipo en la tarjeta de la oferta', function () {
    negMundo();
    [$solicitud] = negAdjudicada();
    $comparador = app(ConsultarOfertas::class)->delCliente(NEG_CLIENTE, $solicitud);

    expect($comparador[0]['proveedor']['foto_url'])->toContain('/api/personas/'.NEG_PILOTO_7.'/foto');
});

// ── Compuerta de la cuenta aprobada y sin autocontratación (ADR 0046, puntos 10 y 12) ────────────

/** Deja a un proveedor en el estado indicado (la lectura de proveedores de las pruebas es de mentira). */
function negProveedorEn(string $estado, int $id = 7): void
{
    $base = [
        7 => new DatosProveedor(7, NEG_TITULAR_7, 'independiente', 'Proveedor Siete', $estado, calificacionPromedio: '4.60', calificacionesConteo: 12),
        8 => new DatosProveedor(8, NEG_TITULAR_8, 'tercero', 'Proveedor Ocho', 'aprobado'),
    ];
    $base[$id] = new DatosProveedor($id, $id === 7 ? NEG_TITULAR_7 : NEG_TITULAR_8, $id === 7 ? 'independiente' : 'tercero', 'Proveedor', $estado);

    app()->instance(LecturaProveedores::class, new ProveedoresFalsos($base, [71 => new DatosPiloto(71, 7, NEG_PILOTO_7, 'Piloto Siete', 'aprobado')]));
}

function negClienteEn(string $estado, int $usuario = NEG_CLIENTE): void
{
    DB::table('sol_clientes')->where('usuario_id', $usuario)->update(['estado' => $estado]);
}

/** @return array{codigo: string, estado: string}|null el rechazo de la compuerta, o `null` si la acción pasó */
function negCompuerta(callable $accion): ?array
{
    try {
        $accion();
    } catch (CuentaNoAprobada $excepcion) {
        return ['codigo' => $excepcion->cuerpo()['codigo'], 'estado' => $excepcion->estado->value];
    }

    return null;
}

test('un proveedor que no está aprobado entra pero no ofrece: 403 cuenta_no_aprobada con su estado', function (string $estado) {
    negMundo();
    $solicitud = negAbierta();
    negProveedorEn($estado);

    expect(negCompuerta(fn () => negOfertar(NEG_TITULAR_7, $solicitud)))->toBe(['codigo' => 'cuenta_no_aprobada', 'estado' => $estado])
        ->and(Oferta::query()->count())->toBe(0);
})->with(['pendiente', 'rechazado', 'suspendido', 'baja']);

test('el rechazo de la compuerta sale como 403 con el estado y lo que falta', function () {
    $excepcion = new CuentaNoAprobada(EstadoAprobacion::Pendiente, ['verificacion']);

    $respuesta = app(ExceptionHandler::class)->render(Request::create('/api/solicitudes/1/publicar', 'POST', server: ['HTTP_ACCEPT' => 'application/json']), $excepcion);

    expect($respuesta->getStatusCode())->toBe(403)
        ->and(json_decode($respuesta->getContent(), true))->toMatchArray(['codigo' => 'cuenta_no_aprobada', 'estado' => 'pendiente', 'falta' => ['verificacion']]);
});

test('un cliente pendiente arma sus terrenos y borradores pero no publica: 403 cuenta_no_aprobada', function () {
    negMundo();
    negClienteEn('pendiente');

    [$borrador] = app(CrearSolicitud::class)->ejecutar(NEG_CLIENTE, [
        'uuid_cliente' => (string) Str::uuid(), 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida', 'hectareas_solicitadas' => '80',
        'producto_indicado' => 'Glifosato', 'ventana_desde' => '2026-12-12', 'ventana_hasta' => '2026-12-20', 'precio_ha_propuesto' => '125',
    ]);

    expect(negCompuerta(fn () => app(PublicarSolicitud::class)->ejecutar(NEG_CLIENTE, $borrador->id)))->toBe(['codigo' => 'cuenta_no_aprobada', 'estado' => 'pendiente'])
        ->and(negEstado($borrador->id))->toBe('borrador');

    // Aprobado, publica.
    negClienteEn('aprobado');
    expect(app(PublicarSolicitud::class)->ejecutar(NEG_CLIENTE, $borrador->id)->estado->value)->toBe('abierta');
});

test('un cliente rechazado, suspendido o dado de baja tampoco publica', function (string $estado) {
    negMundo();
    $solicitud = negAbierta();
    negClienteEn('aprobado');
    [$borrador] = app(CrearSolicitud::class)->ejecutar(NEG_CLIENTE, [
        'uuid_cliente' => (string) Str::uuid(), 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida', 'hectareas_solicitadas' => '80',
        'producto_indicado' => 'Glifosato', 'ventana_desde' => '2026-12-12', 'ventana_hasta' => '2026-12-20', 'precio_ha_propuesto' => '125',
    ]);
    negClienteEn($estado);

    expect(negCompuerta(fn () => app(PublicarSolicitud::class)->ejecutar(NEG_CLIENTE, $borrador->id)))->toBe(['codigo' => 'cuenta_no_aprobada', 'estado' => $estado]);
})->with(['rechazado', 'suspendido', 'baja']);

test('contraofertar y aceptar exigen la cuenta aprobada de cada parte; rechazar y retirar no se frenan', function () {
    negMundo();
    $solicitud = negAbierta();
    $oferta = negOfertar(NEG_TITULAR_7, $solicitud);

    // El cliente deja de estar aprobado a mitad de la negociación.
    negClienteEn('suspendido');
    expect(negCompuerta(fn () => app(Negociar::class)->contraofertar('cliente', NEG_CLIENTE, $oferta->id, '118', null, null)))->toBe(['codigo' => 'cuenta_no_aprobada', 'estado' => 'suspendido'])
        ->and(negCompuerta(fn () => app(Negociar::class)->aceptar('cliente', NEG_CLIENTE, $oferta->id)))->toBe(['codigo' => 'cuenta_no_aprobada', 'estado' => 'suspendido'])
        ->and(negEstado($solicitud))->toBe('con_ofertas');

    // Salir de la negociación siempre se puede: rechazar no pasa por la compuerta.
    expect(negCompuerta(fn () => app(Negociar::class)->rechazar('cliente', NEG_CLIENTE, $oferta->id)))->toBeNull();
});

test('un proveedor suspendido a mitad de la negociación no contraoferta ni acepta, pero sí retira su oferta', function () {
    negMundo();
    $solicitud = negAbierta();
    $oferta = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '118');
    $respuesta = app(Negociar::class)->contraofertar('cliente', NEG_CLIENTE, $oferta->id, '121', null, null)[0];

    negProveedorEn('suspendido');

    expect(negCompuerta(fn () => app(Negociar::class)->contraofertar('proveedor', NEG_TITULAR_7, $respuesta->id, '119', null, null)))->toBe(['codigo' => 'cuenta_no_aprobada', 'estado' => 'suspendido'])
        ->and(negCompuerta(fn () => app(Negociar::class)->aceptar('proveedor', NEG_TITULAR_7, $respuesta->id)))->toBe(['codigo' => 'cuenta_no_aprobada', 'estado' => 'suspendido']);
});

test('un proveedor no oferta sobre la solicitud de otra cuenta de su misma persona, y no la ve en el mapa', function () {
    negMundo();
    DB::table('sol_terrenos')->insert(['id' => 2, 'cliente_id' => 2, 'nombre' => 'Lote Sur', 'hectareas' => '100.0000', 'acceso_dificil' => false, 'departamento_codigo' => Ubicaciones::DEPARTAMENTO, 'provincia_codigo' => Ubicaciones::PROVINCIA, 'created_at' => now(), 'updated_at' => now()]);
    $propia = negAbierta();
    $ajena = negAbierta('125', '80', NEG_OTRO_CLIENTE, 2);

    // Las cuentas 900 (cliente) y 701 (titular del proveedor 7) son de la misma persona.
    app()->instance(LecturaCuentas::class, new CuentasFalsas([NEG_CLIENTE => 55, NEG_TITULAR_7 => 55, NEG_OTRO_CLIENTE => 56]));

    expect(negCodigo(fn () => negOfertar(NEG_TITULAR_7, $propia)))->toBe('propia_cuenta:422')
        ->and(negOfertar(NEG_TITULAR_7, $ajena)->solicitud_id)->toBe($ajena)
        ->and(collect(app(MercadoDeSolicitudes::class)->listar(NEG_TITULAR_7, [])['items'])->pluck('id')->all())->toBe([$ajena]);
});

test('también cuenta la persona del piloto con cuenta propia del proveedor', function () {
    negMundo();
    $solicitud = negAbierta();

    app()->instance(LecturaCuentas::class, new CuentasFalsas([NEG_CLIENTE => 77, NEG_PILOTO_7 => 77]));

    expect(negCodigo(fn () => negOfertar(NEG_TITULAR_7, $solicitud)))->toBe('propia_cuenta:422');
});

test('sin persona en común, nadie bloquea a nadie: personas distintas o cuentas sin persona ofertan normal', function () {
    negMundo();
    $solicitud = negAbierta();

    app()->instance(LecturaCuentas::class, new CuentasFalsas([NEG_CLIENTE => 55, NEG_TITULAR_7 => 56]));
    expect(negOfertar(NEG_TITULAR_7, $solicitud)->solicitud_id)->toBe($solicitud);

    app()->instance(LecturaCuentas::class, new CuentasFalsas([NEG_CLIENTE => null, NEG_TITULAR_8 => null]));
    expect(negOfertar(NEG_TITULAR_8, $solicitud)->solicitud_id)->toBe($solicitud);
});

test('el cliente nuevo nace pendiente, la oficina lo aprueba con historial y quien aprobó queda registrado', function () {
    negMundo();
    $cliente = Cliente::query()->where('usuario_id', NEG_CLIENTE)->firstOrFail();
    $cambiar = app(CambiarEstadoDeCliente::class);
    DB::table('sol_clientes')->where('id', $cliente->id)->update(['estado' => 'pendiente']);
    Event::fake([EstadoDeAprobacionCambiado::class]);

    $aprobado = $cambiar->ejecutar($cliente->id, AccionAprobacion::Aprobar, null, 8001);

    expect($aprobado->estado)->toBe(EstadoAprobacion::Aprobado)
        ->and($aprobado->aprobado_por)->toBe(8001)
        ->and($aprobado->aprobado_en)->not->toBeNull();
    Event::assertDispatched(EstadoDeAprobacionCambiado::class, fn (EstadoDeAprobacionCambiado $e): bool => $e->sujetoTipo === 'cliente' && $e->usuariosDestinatarios === [NEG_CLIENTE] && $e->accion === AccionAprobacion::Aprobar);

    // Suspender y reactivar exigen motivo la primera vez y dejan cada paso en el historial.
    expect(fn () => $cambiar->suspender($cliente->id, '', 8001))->toThrow(MotivoRequerido::class);
    $cambiar->suspender($cliente->id, 'Reclamo repetido', 8001);
    $cambiar->reactivar($cliente->id, 8001);

    $pasos = DB::table('sol_cliente_historial_estados')->where('cliente_id', $cliente->id)->orderBy('id')->get(['estado_anterior', 'estado_nuevo', 'accion', 'actor_usuario_id', 'motivo']);

    expect($pasos->map(fn ($p) => "{$p->estado_anterior}>{$p->estado_nuevo}:{$p->accion}")->all())->toBe(['pendiente>aprobado:aprobar', 'aprobado>suspendido:suspender', 'suspendido>aprobado:rehabilitar'])
        ->and($pasos[1]->motivo)->toBe('Reclamo repetido')
        ->and($pasos->pluck('actor_usuario_id')->unique()->map(fn ($id) => (int) $id)->all())->toBe([8001]);
});

test('el cliente sigue el ciclo único: un rechazado reenvía, y de una baja no se vuelve', function () {
    negMundo();
    $cliente = Cliente::query()->where('usuario_id', NEG_CLIENTE)->firstOrFail();
    $cambiar = app(CambiarEstadoDeCliente::class);
    DB::table('sol_clientes')->where('id', $cliente->id)->update(['estado' => 'pendiente']);
    Event::fake([EstadoDeAprobacionCambiado::class]);

    expect(fn () => $cambiar->ejecutar($cliente->id, AccionAprobacion::Rechazar, null, 8001))->toThrow(MotivoRequerido::class)
        ->and(fn () => $cambiar->ejecutar($cliente->id, AccionAprobacion::Rehabilitar, null, 8001))->toThrow(TransicionDeAprobacionInvalida::class);

    $rechazado = $cambiar->ejecutar($cliente->id, AccionAprobacion::Rechazar, 'Documento ilegible', 8001);
    expect($rechazado->estado)->toBe(EstadoAprobacion::Rechazado)->and($rechazado->motivo_estado)->toBe('Documento ilegible');

    $cambiar->ejecutar($cliente->id, AccionAprobacion::DarDeBaja, 'Cuenta falsa', 8001);

    expect(fn () => $cambiar->ejecutar($cliente->id, AccionAprobacion::Aprobar, null, 8001))->toThrow(TransicionDeAprobacionInvalida::class);
});

test('el total estimado lo calcula el servidor con decimal exacto y ninguna petición lo fija', function () {
    negMundo();
    $solicitud = negAbierta('33.3333', '3');
    $oferta = app(Negociar::class)->ofertar(NEG_TITULAR_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'tipo' => 'contraoferta', 'precio_ha' => '0.3333', 'monto_estimado' => '999999'])[0];

    expect(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('monto_estimado'))->toEqual('100.00')
        ->and($oferta->monto_estimado)->toEqual(MontoDelServicio::monto('3.0000', '0.3333'))
        ->and($oferta->monto_estimado)->toEqual('1.00')
        ->and(Presentacion::oferta($oferta)['monto_estimado'])->toEqual('1.00');

    [$propuesta] = app(CrearSolicitud::class)->ejecutar(NEG_CLIENTE, [
        'uuid_cliente' => (string) Str::uuid(), 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida', 'hectareas_solicitadas' => '80',
        'producto_indicado' => 'Glifosato', 'precio_ha_propuesto' => '125', 'monto_estimado' => '1',
    ]);
    expect($propuesta->fresh()->monto_estimado)->toBeNull();

    $cambiada = app(CambiarPrecioPropuesto::class)->ejecutar(NEG_CLIENTE, $propuesta->id, '110.5');

    expect($cambiada->monto_estimado)->toEqual('8840.00');
    foreach ([CrearSolicitudRequest::class, CambiarPrecioRequest::class, OfertarRequest::class, ContraofertarRequest::class] as $request) {
        expect(array_keys((new $request)->rules()))->not->toContain('monto_estimado')->not->toContain('monto_final');
    }
});

test('cada parte justifica su precio en su propio registro y la contraparte la ve sanitizada', function () {
    negMundo();
    [$borrador] = app(CrearSolicitud::class)->ejecutar(NEG_CLIENTE, [
        'uuid_cliente' => (string) Str::uuid(), 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida', 'hectareas_solicitadas' => '80',
        'producto_indicado' => 'Glifosato', 'precio_ha_propuesto' => '125', 'justificacion_precio' => "  <b>Tarifa</b> del año\n\tpasado <script>alert(1)</script>  ",
    ]);
    expect($borrador->justificacion_precio)->toBe('Tarifa del año pasado alert(1)');

    $solicitud = app(PublicarSolicitud::class)->ejecutar(NEG_CLIENTE, $borrador->id)->id;
    $detalle = app(MercadoDeSolicitudes::class)->detalle(NEG_TITULAR_7, $solicitud);
    expect($detalle['justificacion_precio'])->toBe('Tarifa del año pasado alert(1)')
        ->and($detalle['monto_estimado'])->toEqual('10000.00')
        ->and(app(MercadoDeSolicitudes::class)->listar(NEG_TITULAR_7, [])['items'][0]['monto_estimado'])->toEqual('10000.00');

    // El proveedor escribe la suya en la oferta; el cliente, la suya en la contraoferta. Ninguno toca la solicitud ni la fila del otro.
    [$o1] = app(Negociar::class)->ofertar(NEG_TITULAR_7, $solicitud, ['uuid_cliente' => (string) Str::uuid(), 'tipo' => 'contraoferta', 'precio_ha' => '140', 'justificacion_precio' => 'Incluye traslado del dron']);
    [$o2] = app(Negociar::class)->contraofertar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $o1->id, '120', null, null, 'Es lo que pagué el año pasado');
    [$o3] = app(Negociar::class)->contraofertar(Negociar::LADO_PROVEEDOR, NEG_TITULAR_7, $o2->id, '130', null, null, null);

    expect($o1->fresh()->justificacion_precio)->toBe('Incluye traslado del dron')
        ->and($o2->justificacion_precio)->toBe('Es lo que pagué el año pasado')
        ->and($o3->justificacion_precio)->toBeNull()
        ->and(Presentacion::oferta($o2)['justificacion_precio'])->toBe('Es lo que pagué el año pasado')
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('justificacion_precio'))->toBe('Tarifa del año pasado alert(1)');

    // Cambiar el precio sin la clave conserva la justificación; con ella (nula) la quita; con texto la reemplaza.
    $cambiar = app(CambiarPrecioPropuesto::class);
    expect($cambiar->ejecutar(NEG_CLIENTE, $solicitud, '126')->justificacion_precio)->toBe('Tarifa del año pasado alert(1)')
        ->and($cambiar->ejecutar(NEG_CLIENTE, $solicitud, '127', '  Subí por el combustible ', true)->justificacion_precio)->toBe('Subí por el combustible')
        ->and($cambiar->ejecutar(NEG_CLIENTE, $solicitud, '128', '   ', true)->justificacion_precio)->toBeNull();
});

test('la justificación del precio tiene un máximo de 500 caracteres en todas las entradas', function () {
    foreach ([CrearSolicitudRequest::class, CambiarPrecioRequest::class, OfertarRequest::class, ContraofertarRequest::class] as $request) {
        $reglas = (new $request)->rules();
        expect(Validator::make(['justificacion_precio' => str_repeat('a', 500)], ['justificacion_precio' => $reglas['justificacion_precio']])->passes())->toBeTrue()
            ->and(Validator::make(['justificacion_precio' => str_repeat('a', 501)], ['justificacion_precio' => $reglas['justificacion_precio']])->fails())->toBeTrue();
    }
});

test('el comparador ordena y filtra en el servidor sin exponer contacto', function () {
    negMundo();
    app()->instance(LecturaProveedores::class, new ProveedoresFalsos(
        [
            7 => new DatosProveedor(7, NEG_TITULAR_7, 'independiente', 'Proveedor Siete', 'aprobado', calificacionPromedio: '4.60', calificacionesConteo: 12),
            8 => new DatosProveedor(8, NEG_TITULAR_8, 'tercero', 'Proveedor Ocho', 'aprobado', calificacionPromedio: '4.90', calificacionesConteo: 1),
            9 => new DatosProveedor(9, 703, 'propio', 'Agrocom', 'aprobado', calificacionPromedio: '3.00', calificacionesConteo: 40),
        ],
        [],
    ));
    $solicitud = negAbierta();
    $o7 = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '118');
    $o8 = negOfertar(NEG_TITULAR_8, $solicitud, 'contraoferta', '110');
    $o9 = negOfertar(703, $solicitud, 'contraoferta', '124');
    $consultar = app(ConsultarOfertas::class);
    $ids = fn (array $criterios = []): array => array_map(fn (array $fila): int => $fila['proveedor_id'], $consultar->delCliente(NEG_CLIENTE, $solicitud, $criterios));

    // Recomendado (por defecto): propio primero; luego mayor calificación, y quien tiene pocas calificaciones (8) al final de su grupo.
    expect($ids())->toBe([9, 7, 8])
        ->and($ids(['orden' => 'recomendado']))->toBe([9, 7, 8])
        ->and($ids(['orden' => 'precio']))->toBe([8, 7, 9])
        ->and($ids(['orden' => 'calificacion']))->toBe([7, 9, 8])
        ->and($ids(['orden' => 'reciente']))->toBe([9, 8, 7])
        ->and($ids(['precio_ha_min' => '118', 'precio_ha_max' => '124']))->toBe([9, 7])
        ->and($ids(['monto_max' => '9000']))->toBe([8])
        ->and($ids(['calificacion_min' => '4.5']))->toBe([7, 8])
        ->and($ids(['tipo_proveedor' => 'propio']))->toBe([9])
        ->and($ids(['capacidad_tanque_min' => '40']))->toBe([9, 7, 8])
        ->and($ids(['capacidad_tanque_min' => '40.01']))->toBe([])
        ->and($ids(['tipo_proveedor' => 'tercero', 'precio_ha_max' => '100']))->toBe([]);

    $fila = $consultar->delCliente(NEG_CLIENTE, $solicitud)[0];
    $publico = json_encode($consultar->delCliente(NEG_CLIENTE, $solicitud), JSON_THROW_ON_ERROR);
    expect($fila['proveedor']['tipo'])->toBe('propio')
        ->and($fila)->toHaveKeys(['precio_ha', 'monto_estimado', 'justificacion_precio'])
        ->and($fila['proveedor'])->not->toHaveKeys(['telefono', 'email', 'documento_fiscal', 'disponibilidad'])
        ->and($publico)->not->toContain('70011122')->not->toContain('"telefono"')->not->toContain('"disponibilidad"');

    // Las vigentes van primero: la que el cliente ya rechazó baja al final aunque sea la recomendada.
    app(Negociar::class)->rechazar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $o9->id);
    expect($ids())->toBe([7, 8, 9]);
});

test('el orden y los filtros del comparador rechazan valores inválidos', function () {
    $valida = fn (array $consulta): bool => Validator::make($consulta, CompararOfertasRequest::reglas())->passes();

    expect($valida([]))->toBeTrue()
        ->and($valida(['orden' => 'precio', 'precio_ha_min' => '100.5', 'calificacion_min' => '4.5', 'tipo_proveedor' => 'tercero', 'capacidad_tanque_min' => '30', 'monto_max' => '9000']))->toBeTrue()
        ->and($valida(['orden' => 'distancia']))->toBeFalse()
        ->and($valida(['precio_ha_max' => 'barato']))->toBeFalse()
        ->and($valida(['calificacion_min' => '6']))->toBeFalse()
        ->and($valida(['tipo_proveedor' => 'otro']))->toBeFalse()
        ->and($valida(['precio_ha_min' => '-1']))->toBeFalse()
        ->and($valida(['capacidad_tanque_min' => '-3']))->toBeFalse();
});

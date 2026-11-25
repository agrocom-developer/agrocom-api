<?php

use App\Dominios\Compartido\Dominio\MontoDelServicio;
use App\Dominios\Flota\Contratos\DatosDron;
use App\Dominios\Flota\Contratos\DatosEquipoApto;
use App\Dominios\Flota\Contratos\DatosIntegrantesEquipo;
use App\Dominios\Flota\Contratos\LecturaDrones;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Negociacion\Aplicacion\CancelarComoProveedor;
use App\Dominios\Negociacion\Aplicacion\ConfirmarPago;
use App\Dominios\Negociacion\Aplicacion\ConsultarOfertas;
use App\Dominios\Negociacion\Aplicacion\Mensajeria;
use App\Dominios\Negociacion\Aplicacion\MercadoDeSolicitudes;
use App\Dominios\Negociacion\Aplicacion\Negociar;
use App\Dominios\Negociacion\Aplicacion\PreferenciasDeAviso;
use App\Dominios\Negociacion\Aplicacion\ProcesarNegociacion;
use App\Dominios\Negociacion\Aplicacion\Seguimiento;
use App\Dominios\Negociacion\Aplicacion\UbicacionDelPiloto;
use App\Dominios\Negociacion\Contratos\Eventos\AvisoDeNegociacion;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\DatosPiloto;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\FotosDePerfil;
use App\Dominios\Solicitudes\Aplicacion\CambiarPrecioPropuesto;
use App\Dominios\Solicitudes\Aplicacion\CancelarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\CrearSolicitud;
use App\Dominios\Solicitudes\Aplicacion\GestionarFotosDeTerreno;
use App\Dominios\Solicitudes\Aplicacion\PrecioDeReferencia;
use App\Dominios\Solicitudes\Aplicacion\PublicarSolicitud;
use App\Dominios\Solicitudes\Contratos\Eventos\PrecioPropuestoCambiado;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudAbierta;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCancelada;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use Database\Seeders\Catalogo\PreciosReferenciaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Soporte\Esquema;
use Tests\Soporte\ProveedoresFalsos;
use Tests\TestCase;

/*
 * El motor de negociación de punta a punta (`negociacion-contrato.md`): oferta, contraoferta por
 * rondas, adjudicación, seguimiento con fotos, chat, ubicación y mapa; permisos y aislamiento. Contra
 * el ESQUEMA REAL de solicitudes y de negociación (las migraciones, en SQLite en memoria), con los
 * casos de uso reales y con los contratos de `Proveedores` y `Flota` reemplazados por datos controlados.
 *
 * Mundo: cliente 900 (cliente 1) y 901 (cliente 2); proveedor 7 (titular 701, piloto 711, equipo 70) y
 * proveedor 8 (titular 702, equipo 80); un terreno de 100 ha en la zona 1 (Chiquitania).
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
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'crea_sol_cliente_contactos',
        'create_neg_tablas_de_negociacion',
        'create_neg_pagos_confirmados_table',
        'create_sol_terreno_fotos_table',
        'create_sol_precios_referencia_table',
    ]);

    foreach (['neg_pagos_confirmados', 'sol_terreno_fotos', 'sol_precios_referencia', 'neg_preferencias', 'neg_ubicaciones', 'neg_evidencias', 'neg_avances', 'neg_mensajes', 'neg_cancelaciones', 'neg_ofertas', 'sol_solicitud_historial', 'sol_solicitudes_servicio', 'sol_terrenos', 'sol_clientes', 'sol_zonas'] as $tabla) {
        DB::table($tabla)->delete();
    }

    Event::fake([AvisoDeNegociacion::class, SolicitudAbierta::class, SolicitudCancelada::class, PrecioPropuestoCambiado::class]);
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-12-10 09:00:00'));

    DB::table('sol_zonas')->insert(['id' => 1, 'nombre' => 'San José de Chiquitos', 'centroide_lat' => '-17.8450000', 'centroide_lng' => '-60.7390000', 'radio_km' => '60.0', 'macrorregion' => 'chiquitania', 'created_at' => now(), 'updated_at' => now()]);

    foreach ([1 => [NEG_CLIENTE, 'Cliente Ana', '70011122'], 2 => [NEG_OTRO_CLIENTE, 'Cliente Beto', '70033344']] as $id => [$usuario, $nombre, $telefono]) {
        DB::table('sol_clientes')->insert(['id' => $id, 'usuario_id' => $usuario, 'tipo' => 'persona', 'razon_social' => $nombre, 'telefono' => $telefono, 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()]);
    }

    DB::table('sol_terrenos')->insert([
        'id' => 1, 'cliente_id' => 1, 'nombre' => 'Lote Norte', 'hectareas' => '100.0000', 'cultivo' => 'Soya', 'zona_id' => 1, 'acceso_dificil' => false,
        'geometria' => json_encode(['type' => 'Polygon', 'coordinates' => [[[-60.8, -17.8], [-60.7, -17.8], [-60.7, -17.9], [-60.8, -17.9], [-60.8, -17.8]]]]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app()->instance(LecturaProveedores::class, new ProveedoresFalsos(
        [
            7 => new DatosProveedor(7, NEG_TITULAR_7, 'independiente', 'Proveedor Siete', 'aprobado', 1, calificacionPromedio: '4.60', calificacionesConteo: 12),
            8 => new DatosProveedor(8, NEG_TITULAR_8, 'tercero', 'Proveedor Ocho', 'aprobado', 1),
        ],
        [71 => new DatosPiloto(71, 7, NEG_PILOTO_7, 'Piloto Siete', 'aprobado')],
    ));

    app()->instance(LecturaEquipos::class, new class implements LecturaEquipos
    {
        public function aptosDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return [new DatosEquipoApto($proveedorId * 10, $proveedorId, $proveedorId * 10 + 1, $proveedorId * 100, null, '40.00', '11.00')];
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
            return new DatosIntegrantesEquipo($equipoId, intdiv($equipoId, 10), $equipoId + 1, intdiv($equipoId, 10) * 100, null, 'externo');
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
function negAbierta(string $precio = '125', string $hectareas = '80', int $usuario = NEG_CLIENTE, int $terreno = 1): int
{
    [$solicitud] = app(CrearSolicitud::class)->ejecutar($usuario, [
        'uuid_cliente' => (string) Str::uuid(), 'terreno_id' => $terreno, 'tipo_aplicacion' => 'herbicida', 'hectareas_solicitadas' => $hectareas,
        'producto_indicado' => 'Glifosato', 'ventana_desde' => '2026-12-12', 'ventana_hasta' => '2026-12-20', 'precio_ha_propuesto' => $precio,
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

test('abrir una solicitud exige precio propuesto y teléfono, y la deja visible con su vigencia', function () {
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
        ->and(Carbon::parse($fila->vence_en)->toDateString())->toBe('2026-12-17');
    Event::assertDispatched(SolicitudAbierta::class, fn (SolicitudAbierta $e): bool => $e->solicitudId === $abierta && $e->macrorregion === 'chiquitania');
});

test('el mapa muestra lotes, precio y estado sin ningún dato privado del cliente', function () {
    negMundo();
    $solicitud = negAbierta();

    $lista = app(MercadoDeSolicitudes::class)->listar(NEG_TITULAR_7, ['macrorregion' => 'chiquitania']);
    $detalle = app(MercadoDeSolicitudes::class)->detalle(NEG_TITULAR_7, $solicitud);
    $resumen = app(MercadoDeSolicitudes::class)->resumenDeZonas(NEG_TITULAR_7, []);
    $publico = json_encode([$lista, $detalle, $resumen], JSON_THROW_ON_ERROR);

    expect($lista['items'])->toHaveCount(1)
        ->and($lista['items'][0]['precio_ha_propuesto'])->toBe('125.0000')
        ->and($lista['items'][0]['macrorregion'])->toBe('chiquitania')
        ->and($lista['items'][0]['centro'])->not->toBeNull()
        ->and($detalle['terreno']['geometria']['type'])->toBe('Polygon')
        ->and($resumen[0]['solicitudes'])->toBe(1)
        ->and($resumen[0]['hectareas_total'])->toBe('80.0000')
        ->and($publico)->not->toContain('Cliente Ana')->not->toContain('70011122')->not->toContain('usuario_id');
});

test('el mapa filtra por zona, hectáreas y precio, y ordena por distancia', function () {
    negMundo();
    negAbierta('125', '80');
    negAbierta('90', '20');
    $mercado = app(MercadoDeSolicitudes::class);

    expect($mercado->listar(NEG_TITULAR_7, ['hectareas_min' => '50'])['items'])->toHaveCount(1)
        ->and($mercado->listar(NEG_TITULAR_7, ['precio_ha_max' => '100'])['items'])->toHaveCount(1)
        ->and($mercado->listar(NEG_TITULAR_7, ['zona_id' => 99])['items'])->toHaveCount(0)
        ->and($mercado->listar(NEG_TITULAR_7, ['macrorregion' => 'valles'])['items'])->toHaveCount(0)
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
    DB::table('sol_terrenos')->insert(['id' => 2, 'cliente_id' => 2, 'nombre' => 'Lote Sur', 'hectareas' => '50.0000', 'zona_id' => 1, 'acceso_dificil' => false, 'created_at' => now(), 'updated_at' => now()]);
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

test('una oferta vencida ya no se responde y el proceso programado reabre la solicitud y expira las vencidas', function () {
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
        ->and($repetido)->toBe(['ofertas_vencidas' => 0, 'solicitudes_expiradas' => 0]);

    Carbon::setTestNow(Carbon::parse('2026-12-20 10:00:00'));
    $otra = negOfertar(NEG_TITULAR_8, negAbierta());
    Carbon::setTestNow(Carbon::parse('2026-12-30 10:00:00'));
    $cierre = app(ProcesarNegociacion::class)->ejecutar();

    expect($cierre['solicitudes_expiradas'])->toBe(2)
        ->and(negEstado($solicitud))->toBe('expirada')
        ->and($otra->refresh()->estado->value)->toBeIn(['vencida', 'cerrada']);
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

test('las ofertas del proveedor traen la zona y el lote solo cuando la solicitud quedó adjudicada a él', function () {
    negMundo();
    $solicitud = negAbierta();
    $de7 = negOfertar(NEG_TITULAR_7, $solicitud, 'contraoferta', '118');
    $consultar = app(ConsultarOfertas::class);

    $antes = $consultar->delProveedor(NEG_TITULAR_7)[0];
    app(Negociar::class)->aceptar(Negociar::LADO_CLIENTE, NEG_CLIENTE, $de7->id);
    $despues = $consultar->delProveedor(NEG_TITULAR_7)[0];
    $detalle = $consultar->una(Negociar::LADO_PROVEEDOR, NEG_TITULAR_7, $de7->id);

    expect($antes)->toHaveKeys(['zona', 'zona_id', 'lote'])->and($antes['lote'])->toBeNull()
        ->and($despues['lote'])->toBe(DB::table('sol_terrenos')->where('id', 1)->value('nombre'))
        ->and($despues['zona_id'])->toBe($antes['zona_id'])
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

test('las preferencias activan, filtran y apagan los avisos de nuevas solicitudes', function () {
    negMundo();
    $preferencias = app(PreferenciasDeAviso::class);

    expect($preferencias->de(NEG_TITULAR_7)['nuevas_solicitudes']['activo'])->toBeTrue()
        ->and($preferencias->coincide(NEG_TITULAR_7, 1, 'chiquitania', '10'))->toBeTrue();

    $preferencias->guardar(NEG_TITULAR_7, ['nuevas_solicitudes' => ['zonas' => [3, 7], 'macrorregiones' => ['valles'], 'hectareas_minimas' => '50']]);

    expect($preferencias->coincide(NEG_TITULAR_7, 1, 'chiquitania', '80'))->toBeFalse()
        ->and($preferencias->coincide(NEG_TITULAR_7, 3, 'valles', '49'))->toBeFalse()
        ->and($preferencias->coincide(NEG_TITULAR_7, 3, 'valles', '50'))->toBeTrue()
        ->and(negCodigo(fn () => $preferencias->guardar(NEG_TITULAR_7, ['nuevas_solicitudes' => ['macrorregiones' => ['marte']]])))->toBe('macrorregion_invalida:422');

    $preferencias->guardar(NEG_TITULAR_7, ['nuevas_solicitudes' => ['activo' => false], 'mensajes' => false]);

    expect($preferencias->coincide(NEG_TITULAR_7, 3, 'valles', '500'))->toBeFalse()
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

test('el precio de referencia busca de la zona a la macrorregión, el general y el respaldo', function () {
    negMundo();
    $referencia = app(PrecioDeReferencia::class);
    $ahora = now();
    $fila = fn (array $datos): bool => DB::table('sol_precios_referencia')->insert([...$datos, 'created_at' => $ahora, 'updated_at' => $ahora]);

    expect($referencia->para('herbicida'))->toMatchArray(['precio_ha' => '120.00', 'nivel' => 'respaldo']);

    $fila(['tipo_aplicacion' => 'herbicida', 'zona_id' => null, 'macrorregion' => null, 'precio_ha' => '121.0000']);
    $fila(['tipo_aplicacion' => 'herbicida', 'zona_id' => null, 'macrorregion' => 'chiquitania', 'precio_ha' => '132.0000']);
    $fila(['tipo_aplicacion' => 'herbicida', 'zona_id' => 1, 'macrorregion' => null, 'precio_ha' => '140.0000']);

    expect($referencia->para('herbicida'))->toMatchArray(['precio_ha' => '121.0000', 'nivel' => 'general'])
        ->and($referencia->para('herbicida', null, 'chiquitania'))->toMatchArray(['precio_ha' => '132.0000', 'nivel' => 'macrorregion'])
        ->and($referencia->para('herbicida', 1))->toMatchArray(['precio_ha' => '140.0000', 'nivel' => 'zona', 'macrorregion' => 'chiquitania', 'zona_id' => 1])
        ->and($referencia->para('herbicida', null, 'valles'))->toMatchArray(['precio_ha' => '121.0000', 'nivel' => 'general'])
        ->and($referencia->para('fungicida', 1))->toMatchArray(['precio_ha' => '135.00', 'nivel' => 'respaldo']);
});

test('el seeder de precios de referencia es idempotente y respeta lo que el administrador ajustó', function () {
    negMundo();
    $seeder = new PreciosReferenciaSeeder;

    $seeder->run();
    $total = DB::table('sol_precios_referencia')->count();
    DB::table('sol_precios_referencia')->where('tipo_aplicacion', 'herbicida')->where('macrorregion', 'chaco')->update(['precio_ha' => '150.0000']);
    $seeder->run();

    expect($total)->toBe(6 * 6)
        ->and(DB::table('sol_precios_referencia')->count())->toBe($total)
        ->and(app(PrecioDeReferencia::class)->para('herbicida', null, 'chaco')['precio_ha'])->toBe('150.0000')
        ->and(app(PrecioDeReferencia::class)->para('herbicida', null, 'chiquitania')['precio_ha'])->toBe('132.0000');
});

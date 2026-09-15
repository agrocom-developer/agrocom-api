<?php

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Aplicacion\FiltrosInformeAvanceContratos;
use App\Dominios\Comercial\Aplicacion\GuardarSiembraCampania;
use App\Dominios\Comercial\Aplicacion\ObtenerAvanceComercial;
use App\Dominios\Comercial\Aplicacion\ObtenerInformeAvanceContratos;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\Excepciones\FiltroInformeIncompleto;
use App\Dominios\Comercial\Dominio\SaldoContrato;
use App\Dominios\Comercial\Dominio\TramoAvance;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Brick\Math\BigDecimal;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * `ObtenerInformeAvanceContratos` (HU-52, tarea 75, espec §9.1): informe de
 * avance de contratos por cultivo y por cliente. Test "unitario" en el
 * sentido de probar el caso de uso directo (sin HTTP) — mismo criterio que
 * `LecturaCultivoLoteEloquentTest`. Las fixtures crean el acta ya `firmada`
 * por Eloquent directo (no vía las rutas `/api`): lo que se ejercita acá es
 * la cuenta y el agrupamiento del informe, no la máquina de estados del
 * acta (que ya tiene su propio test).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function clienteInforme(string $sufijo): Cliente
{
    return Cliente::create(['razon_social' => "Cliente informe {$sufijo}", 'tipo_persona' => 'juridica']);
}

function campaniaInforme(Cliente $cliente, string $sufijo): Campania
{
    return Campania::create([
        'cliente_id' => $cliente->id,
        'codigo' => "2025-2026-{$sufijo}",
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);
}

function contratoInforme(Cliente $cliente, Campania $campania, string $sufijo, string $hectareasContratadas, ?string $fechaFin = null): Contrato
{
    return Contrato::create([
        'cliente_id' => $cliente->id,
        'campania_id' => $campania->id,
        'hectareas_contratadas' => $hectareasContratadas,
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '0.00',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => $fechaFin,
        'estado' => EstadoContrato::Vigente,
    ]);
}

/** Siembra un lote nuevo del cliente, en esa campaña, con el cultivo dado — devuelve el id del cultivo. */
function siembraInforme(Cliente $cliente, Campania $campania, string $sufijo, string $cultivoNombre, string $hectareasSembradas = '10.00'): int
{
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $lote = $propiedad->lotes()->create(['codigo' => "L-INF-{$sufijo}", 'hectareas' => '100.00']);
    $propiedad->load('lotes');

    $cultivoId = (int) Cultivo::query()->where('nombre', $cultivoNombre)->value('id');

    app(GuardarSiembraCampania::class)->ejecutar($propiedad, $campania->id, [
        ['lote_id' => $lote->id, 'cultivo_id' => $cultivoId, 'hectareas_sembradas' => $hectareasSembradas, 'fecha_siembra' => null, 'fecha_cosecha_estimada' => null],
    ]);

    return $cultivoId;
}

/** Orden + trabajo + acta ya `firmada`, con `hectareasConformadas` = `$hectareas` — hace crecer `hectareasAplicadas` del contrato. */
function actaFirmadaInforme(Contrato $contrato, string $sufijo, string $hectareas): void
{
    $propiedad = Propiedad::create(['cliente_id' => $contrato->cliente_id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $lote = $propiedad->lotes()->create(['codigo' => "L-ACTA-INF-{$sufijo}", 'hectareas' => '999.00']);

    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-01-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '999.00']);

    $trabajo = Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-informe-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-01-02T08:00:00-04:00',
        'fin' => '2026-01-02T12:00:00-04:00',
    ]);

    Acta::create([
        'uuid_cliente' => "uuid-acta-informe-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'hectareas_conformadas' => $hectareas,
        'estado' => EstadoActa::Firmada,
        'fecha_firma' => '2026-01-02T16:00:00-04:00',
    ]);
}

it('requiere al menos un cliente y al menos un cultivo', function () {
    expect(fn () => app(ObtenerInformeAvanceContratos::class)->ejecutar(
        new FiltrosInformeAvanceContratos(clienteIds: [], cultivoIds: [1])
    ))->toThrow(FiltroInformeIncompleto::class);

    expect(fn () => app(ObtenerInformeAvanceContratos::class)->ejecutar(
        new FiltrosInformeAvanceContratos(clienteIds: [1], cultivoIds: [])
    ))->toThrow(FiltroInformeIncompleto::class);
});

it('el avance de un contrato es idéntico al de ObtenerAvanceComercial (misma fórmula)', function () {
    $cliente = clienteInforme('identico');
    $campania = campaniaInforme($cliente, 'identico');
    $soyaId = siembraInforme($cliente, $campania, 'identico', 'Soya');
    $contrato = contratoInforme($cliente, $campania, 'identico', '100.00');
    actaFirmadaInforme($contrato, 'identico-a', '10.50');
    actaFirmadaInforme($contrato, 'identico-b', '20.25');

    $informe = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(
        clienteIds: [$cliente->id],
        cultivoIds: [$soyaId],
    ));

    $filaInforme = $informe->porCultivo[0]->contratos[0];
    $filaAvanceComercial = collect(app(ObtenerAvanceComercial::class)->ejecutar(contratoId: $contrato->id))->first();

    expect($filaInforme->hectareasContratadas)->toBe($filaAvanceComercial['hectareasContratadas'])
        ->and($filaInforme->hectareasAplicadas)->toBe($filaAvanceComercial['hectareasAplicadas'])
        ->and($filaInforme->hectareasAplicadas)->toBe('30.75');
});

it('agrupa por cultivo, y un contrato sin actas firmadas aparece con 0.00 aplicadas', function () {
    $cliente = clienteInforme('vacio');
    $campania = campaniaInforme($cliente, 'vacio');
    $soyaId = siembraInforme($cliente, $campania, 'vacio', 'Soya');
    $contrato = contratoInforme($cliente, $campania, 'vacio', '40.00');

    $informe = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(
        clienteIds: [$cliente->id],
        cultivoIds: [$soyaId],
    ));

    expect($informe->porCultivo)->toHaveCount(1)
        ->and($informe->porCultivo[0]->cultivoNombre)->toBe('Soya')
        ->and($informe->porCultivo[0]->contratos[0]->hectareasAplicadas)->toBe('0.00')
        ->and($informe->porCultivo[0]->contratos[0]->hectareasAAplicar)->toBe('40.00');
});

it('el totalizador de "a aplicar" de un grupo cuadra exacto contra la suma de sus contratos', function () {
    $cliente = clienteInforme('total');
    $campania = campaniaInforme($cliente, 'total');
    $soyaId = siembraInforme($cliente, $campania, 'total', 'Soya');

    $contratoA = contratoInforme($cliente, $campania, 'total-a', '33.33');
    actaFirmadaInforme($contratoA, 'total-a', '10.11');

    $contratoB = contratoInforme($cliente, $campania, 'total-b', '66.67');
    actaFirmadaInforme($contratoB, 'total-b', '50.00');

    $informe = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(
        clienteIds: [$cliente->id],
        cultivoIds: [$soyaId],
    ));

    $grupo = $informe->porCultivo[0];

    $sumaContratos = collect($grupo->contratos)->reduce(
        fn (BigDecimal $acumulado, $fila) => $acumulado->plus($fila->hectareasAAplicar),
        BigDecimal::zero(),
    );

    expect($grupo->hectareasAAplicar)->toBe((string) $sumaContratos->toScale(2))
        ->and($grupo->hectareasAAplicar)->toBe('39.89');
});

it('ordena los contratos de un grupo por vencimiento más cercano, y sin vencimiento va al final', function () {
    $cliente = clienteInforme('orden');
    $campania = campaniaInforme($cliente, 'orden');
    $soyaId = siembraInforme($cliente, $campania, 'orden', 'Soya');

    $sinVencimiento = contratoInforme($cliente, $campania, 'orden-sin-venc', '10.00', null);
    $lejano = contratoInforme($cliente, $campania, 'orden-lejano', '10.00', '2027-01-01');
    $cercano = contratoInforme($cliente, $campania, 'orden-cercano', '10.00', '2026-10-01');

    $informe = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(
        clienteIds: [$cliente->id],
        cultivoIds: [$soyaId],
    ));

    $ordenObtenido = collect($informe->porCultivo[0]->contratos)->pluck('contratoId')->all();

    expect($ordenObtenido)->toBe([$cercano->id, $lejano->id, $sinVencimiento->id]);
});

it('un contrato con 100% y otro con 120% caen en tramos de color distintos', function () {
    $cliente = clienteInforme('tramos');
    $campania = campaniaInforme($cliente, 'tramos');
    $soyaId = siembraInforme($cliente, $campania, 'tramos', 'Soya');

    $cumplido = contratoInforme($cliente, $campania, 'tramos-cumplido', '20.00');
    actaFirmadaInforme($cumplido, 'tramos-cumplido', '20.00');

    $excedido = contratoInforme($cliente, $campania, 'tramos-excedido', '20.00');
    actaFirmadaInforme($excedido, 'tramos-excedido', '24.00');

    $informe = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(
        clienteIds: [$cliente->id],
        cultivoIds: [$soyaId],
    ));

    $filas = collect($informe->porCultivo[0]->contratos)->keyBy('contratoId');

    expect($filas[$cumplido->id]->porcentaje)->toBe(100)
        ->and($filas[$cumplido->id]->tramo)->toBe(TramoAvance::Cumplido)
        ->and($filas[$excedido->id]->porcentaje)->toBe(120)
        ->and($filas[$excedido->id]->tramo)->toBe(TramoAvance::MasDeCien)
        ->and($filas[$cumplido->id]->tramo)->not->toBe($filas[$excedido->id]->tramo);
});

it('un contrato cuya campaña siembra dos cultivos aparece en los dos grupos pedidos', function () {
    $cliente = clienteInforme('doble');
    $campania = campaniaInforme($cliente, 'doble');
    $soyaId = siembraInforme($cliente, $campania, 'doble-soya', 'Soya');
    $maizId = siembraInforme($cliente, $campania, 'doble-maiz', 'Maíz');

    $contrato = contratoInforme($cliente, $campania, 'doble', '50.00');

    $informe = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(
        clienteIds: [$cliente->id],
        cultivoIds: [$soyaId, $maizId],
    ));

    $cultivosConElContrato = collect($informe->porCultivo)
        ->filter(fn ($grupo) => collect($grupo->contratos)->pluck('contratoId')->contains($contrato->id))
        ->pluck('cultivoNombre')
        ->sort()
        ->values()
        ->all();

    expect($informe->porCultivo)->toHaveCount(2)
        ->and($cultivosConElContrato)->toBe(['Maíz', 'Soya']);
});

it('el grupo "por cliente" anida los mismos contratos dentro de cada cultivo', function () {
    $clienteA = clienteInforme('nido-a');
    $clienteB = clienteInforme('nido-b');
    $campaniaA = campaniaInforme($clienteA, 'nido-a');
    $campaniaB = campaniaInforme($clienteB, 'nido-b');
    $soyaIdA = siembraInforme($clienteA, $campaniaA, 'nido-a', 'Soya');
    siembraInforme($clienteB, $campaniaB, 'nido-b', 'Soya');

    $contratoA = contratoInforme($clienteA, $campaniaA, 'nido-a', '10.00');
    $contratoB = contratoInforme($clienteB, $campaniaB, 'nido-b', '20.00');

    $informe = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(
        clienteIds: [$clienteA->id, $clienteB->id],
        cultivoIds: [$soyaIdA],
    ));

    $grupo = $informe->porCultivo[0];

    expect($grupo->clientes)->toHaveCount(2);

    $porClienteId = collect($grupo->clientes)->keyBy('clienteId');

    expect($porClienteId[$clienteA->id]->contratos[0]->contratoId)->toBe($contratoA->id)
        ->and($porClienteId[$clienteB->id]->contratos[0]->contratoId)->toBe($contratoB->id);
});

it('filtra por saldo: pendiente (0 aplicado), a_aplicar (en curso) y cumplido (>= contratado)', function () {
    $cliente = clienteInforme('saldo');
    $campania = campaniaInforme($cliente, 'saldo');
    $soyaId = siembraInforme($cliente, $campania, 'saldo', 'Soya');

    $pendiente = contratoInforme($cliente, $campania, 'saldo-pendiente', '10.00');

    $enCurso = contratoInforme($cliente, $campania, 'saldo-en-curso', '10.00');
    actaFirmadaInforme($enCurso, 'saldo-en-curso', '5.00');

    $cumplido = contratoInforme($cliente, $campania, 'saldo-cumplido', '10.00');
    actaFirmadaInforme($cumplido, 'saldo-cumplido', '10.00');

    $filtroBase = ['clienteIds' => [$cliente->id], 'cultivoIds' => [$soyaId]];

    $soloPendientes = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(...$filtroBase, saldo: SaldoContrato::Pendiente));
    $soloAAplicar = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(...$filtroBase, saldo: SaldoContrato::AAplicar));
    $soloCumplidos = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(...$filtroBase, saldo: SaldoContrato::Cumplido));

    expect(collect($soloPendientes->porCultivo[0]->contratos)->pluck('contratoId')->all())->toBe([$pendiente->id])
        ->and(collect($soloAAplicar->porCultivo[0]->contratos)->pluck('contratoId')->all())->toBe([$enCurso->id])
        ->and(collect($soloCumplidos->porCultivo[0]->contratos)->pluck('contratoId')->all())->toBe([$cumplido->id]);
});

it('excluye por defecto los contratos deshabilitados (soft-deleted), y los incluye si se pide', function () {
    $cliente = clienteInforme('deshab');
    $campania = campaniaInforme($cliente, 'deshab');
    $soyaId = siembraInforme($cliente, $campania, 'deshab', 'Soya');

    $activo = contratoInforme($cliente, $campania, 'deshab-activo', '10.00');
    $deshabilitado = contratoInforme($cliente, $campania, 'deshab-baja', '10.00');
    $deshabilitado->delete();

    $filtroBase = ['clienteIds' => [$cliente->id], 'cultivoIds' => [$soyaId]];

    $porDefecto = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(...$filtroBase));
    $conDeshabilitados = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(...$filtroBase, incluirDeshabilitados: true));

    expect(collect($porDefecto->porCultivo[0]->contratos)->pluck('contratoId')->all())->toBe([$activo->id])
        ->and(collect($conDeshabilitados->porCultivo[0]->contratos)->pluck('contratoId')->sort()->values()->all())
        ->toBe(collect([$activo->id, $deshabilitado->id])->sort()->values()->all());
});

it('filtra por campaña: quitar el filtro (campaniaIds vacío) trae contratos de otra campaña del mismo cliente', function () {
    $cliente = clienteInforme('camp');
    $campaniaUno = campaniaInforme($cliente, 'camp-1');
    $campaniaDos = campaniaInforme($cliente, 'camp-2');
    $soyaUno = siembraInforme($cliente, $campaniaUno, 'camp-1', 'Soya');
    siembraInforme($cliente, $campaniaDos, 'camp-2', 'Soya');

    $contratoUno = contratoInforme($cliente, $campaniaUno, 'camp-1', '10.00');
    $contratoDos = contratoInforme($cliente, $campaniaDos, 'camp-2', '10.00');

    $filtrado = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(
        clienteIds: [$cliente->id],
        cultivoIds: [$soyaUno],
        campaniaIds: [$campaniaUno->id],
    ));

    $sinFiltroDeCampania = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(
        clienteIds: [$cliente->id],
        cultivoIds: [$soyaUno],
    ));

    expect(collect($filtrado->porCultivo[0]->contratos)->pluck('contratoId')->all())->toBe([$contratoUno->id])
        ->and(collect($sinFiltroDeCampania->porCultivo[0]->contratos)->pluck('contratoId')->sort()->values()->all())
        ->toBe(collect([$contratoUno->id, $contratoDos->id])->sort()->values()->all());
});

it('devuelve el informe vacío cuando ningún contrato coincide con el filtro', function () {
    $cliente = clienteInforme('sin-datos');
    $campania = campaniaInforme($cliente, 'sin-datos');
    $soyaId = siembraInforme($cliente, $campania, 'sin-datos', 'Soya');

    $informe = app(ObtenerInformeAvanceContratos::class)->ejecutar(new FiltrosInformeAvanceContratos(
        clienteIds: [$cliente->id],
        cultivoIds: [$soyaId],
    ));

    expect($informe->porCultivo)->toBe([]);
});

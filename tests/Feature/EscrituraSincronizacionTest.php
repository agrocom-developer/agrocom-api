<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Contratos\AperturaSesion;
use App\Dominios\Operaciones\Contratos\AperturaTrabajo;
use App\Dominios\Operaciones\Contratos\CierreSesion;
use App\Dominios\Operaciones\Contratos\CierreTrabajo;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use App\Dominios\Operaciones\Contratos\RegistroRecepcionCaldo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Condiciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\RecepcionCaldo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Contrato de escritura de `Operaciones` para el motor de sync (ADR 0003,
 * regla 2; TE-05, tarea 09) — probado a nivel de caso de uso, sin pasar por
 * HTTP (eso lo cubre `tests/Feature/Api/SincronizarLoteTest.php`). Acá se
 * ejercita lo que el prompt de la tarea llama "el problema difícil": la
 * resolución de `sesion → trabajo` por `uuid_cliente`, y la traducción de la
 * violación del índice único a `duplicado`.
 */

uses(RefreshDatabase::class);

function ordenVigenteParaEscritura(): OrdenAplicacion
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de prueba']);

    $campo = Campo::create(['cliente_id' => $cliente->id, 'nombre' => 'Campo de prueba']);

    $lote = Lote::create([
        'campo_id' => $campo->id,
        'codigo' => 'L-TEST',
        'hectareas' => '50.00',
    ]);

    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '50.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '500.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    return OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
}

function pilotoParaEscritura(): PerPersona
{
    return PerPersona::create(['nombre' => 'Piloto de prueba', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
}

/**
 * Evidencia `imagen_campo` (HU-09, tarea 21: "sin captura no cierra"),
 * requisito de `cerrarTrabajo()` — mismo criterio que
 * `evidenciaImagenCampoParaCierre()` de `CierreSincronizacionTest.php`, con
 * nombre propio para no chocar con la declaración global de Pest.
 */
function evidenciaImagenCampoParaEscritura(string $id): string
{
    $uuidCliente = "uuid-evidencia-{$id}";

    Evidencia::query()->create([
        'uuid_cliente' => $uuidCliente,
        'tipo' => TipoEvidencia::ImagenCampo,
        'archivo_url' => "evidencias/imagen_campo/2026/09/{$uuidCliente}.jpg",
        'hash' => hash('sha256', $uuidCliente),
        'fecha' => '2026-09-01T09:00:00-04:00',
    ]);

    return $uuidCliente;
}

test('abrirTrabajo con datos válidos aplica y persiste la fila', function () {
    $orden = ordenVigenteParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $datos = AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-1',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);

    $resultado = $contrato->abrirTrabajo($datos);

    expect($resultado->estado)->toBe('aplicado')
        ->and($resultado->motivo)->toBeNull();

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-1')->exists())->toBeTrue();
});

test('abrirTrabajo con el mismo uuid_cliente responde duplicado sin crear una fila nueva', function () {
    $orden = ordenVigenteParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $datos = AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-repetido',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);

    expect($contrato->abrirTrabajo($datos)->estado)->toBe('aplicado');

    $resultado = $contrato->abrirTrabajo($datos);

    expect($resultado->estado)->toBe('duplicado')
        ->and(Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-repetido')->count())->toBe(1);
});

test('abrirSesion resuelve su trabajo por uuid_cliente, no por id de servidor', function () {
    $orden = ordenVigenteParaEscritura();
    $piloto = pilotoParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $trabajo = AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-para-sesion',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
    $contrato->abrirTrabajo($trabajo);

    $sesion = AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-sesion-1',
        'trabajo_uuid_cliente' => 'uuid-trabajo-para-sesion',
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]);

    $resultado = $contrato->abrirSesion($sesion);

    expect($resultado->estado)->toBe('aplicado');

    $trabajoPersistido = Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-para-sesion')->firstOrFail();
    $sesionPersistida = Sesion::query()->where('uuid_cliente', 'uuid-sesion-1')->firstOrFail();

    expect($sesionPersistida->trabajo_id)->toBe($trabajoPersistido->id);
});

test('abrirSesion con un trabajo_uuid_cliente que no existe se rechaza sin romper nada', function () {
    $piloto = pilotoParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $sesion = AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-sesion-huerfana',
        'trabajo_uuid_cliente' => 'uuid-trabajo-inexistente',
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]);

    $resultado = $contrato->abrirSesion($sesion);

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();

    expect(Sesion::query()->where('uuid_cliente', 'uuid-sesion-huerfana')->exists())->toBeFalse();
});

test('abrirSesion con el mismo uuid_cliente responde duplicado sin crear una fila nueva', function () {
    $orden = ordenVigenteParaEscritura();
    $piloto = pilotoParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->abrirTrabajo(AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-de-sesion-duplicada',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]));

    $sesion = AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-sesion-repetida',
        'trabajo_uuid_cliente' => 'uuid-trabajo-de-sesion-duplicada',
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]);

    expect($contrato->abrirSesion($sesion)->estado)->toBe('aplicado');

    $resultado = $contrato->abrirSesion($sesion);

    expect($resultado->estado)->toBe('duplicado')
        ->and(Sesion::query()->where('uuid_cliente', 'uuid-sesion-repetida')->count())->toBe(1);
});

test('AperturaTrabajo::intentarDesdeArreglo devuelve null ante un campo requerido faltante', function () {
    expect(AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-incompleto',
        'orden_id' => 1,
        // falta lote_id, nro_aplicacion, inicio
    ]))->toBeNull();
});

test('AperturaSesion::intentarDesdeArreglo devuelve null ante un campo requerido faltante', function () {
    expect(AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-incompleto',
        'trabajo_uuid_cliente' => 'uuid-trabajo',
        // falta secuencia, piloto_id, inicio
    ]))->toBeNull();
});

/*
 * Tarea 12, hallazgo 1: `hectareas_declaradas` es el único campo que no se
 * validaba — un arreglo se castea a la cadena literal "Array", y un negativo
 * solo lo frenaba el CHECK de Postgres (ausente en SQLite).
 */

test('AperturaTrabajo::intentarDesdeArreglo devuelve null con hectareas_declaradas no numérica', function () {
    expect(AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-ha-invalida',
        'orden_id' => 1,
        'lote_id' => 1,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => ['no', 'numerico'],
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]))->toBeNull();
});

test('AperturaTrabajo::intentarDesdeArreglo devuelve null con hectareas_declaradas negativa', function () {
    expect(AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-ha-negativa',
        'orden_id' => 1,
        'lote_id' => 1,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '-1.00',
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]))->toBeNull();
});

test('AperturaTrabajo::intentarDesdeArreglo acepta hectareas_declaradas numérica y la conserva como string', function () {
    $datos = AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-ha-valida',
        'orden_id' => 1,
        'lote_id' => 1,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => 12.5,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);

    expect($datos)->not->toBeNull()
        ->and($datos->hectareasDeclaradas)->toBe('12.5');
});

test('AperturaSesion::intentarDesdeArreglo devuelve null con hectareas_declaradas no numérica', function () {
    expect(AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-sesion-ha-invalida',
        'trabajo_uuid_cliente' => 'uuid-trabajo',
        'secuencia' => 1,
        'piloto_id' => 1,
        'hectareas_declaradas' => ['no', 'numerico'],
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]))->toBeNull();
});

test('AperturaSesion::intentarDesdeArreglo devuelve null con hectareas_declaradas negativa', function () {
    expect(AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-sesion-ha-negativa',
        'trabajo_uuid_cliente' => 'uuid-trabajo',
        'secuencia' => 1,
        'piloto_id' => 1,
        'hectareas_declaradas' => '-3',
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]))->toBeNull();
});

/*
 * Tarea 12, hallazgo 2: el contrato de escritura no verificaba que
 * `orden_id`/`lote_id` formaran un par legítimo — solo que la FK existiera.
 */

test('abrirTrabajo con un lote_id que no es el de la orden declarada se rechaza sin persistir la fila', function () {
    $orden = ordenVigenteParaEscritura();
    $campoId = Lote::query()->findOrFail($orden->lote_id)->campo_id;

    $otroLote = Lote::create([
        'campo_id' => $campoId,
        'codigo' => 'L-OTRO',
        'hectareas' => '30.00',
    ]);

    $contrato = app(EscrituraSincronizacion::class);

    $datos = AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-lote-ajeno',
        'orden_id' => $orden->id,
        'lote_id' => $otroLote->id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);

    $resultado = $contrato->abrirTrabajo($datos);

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-lote-ajeno')->exists())->toBeFalse();
});

test('abrirTrabajo con una orden no vigente se rechaza sin persistir la fila', function () {
    $orden = ordenVigenteParaEscritura();
    $orden->estado = EstadoOrdenAplicacion::Consumida;
    $orden->save();

    $contrato = app(EscrituraSincronizacion::class);

    $datos = AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-orden-no-vigente',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);

    $resultado = $contrato->abrirTrabajo($datos);

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-orden-no-vigente')->exists())->toBeFalse();
});

/*
 * HU-06, tarea 17: condiciones al iniciar sesión, autoriza o bloquea. DTO y
 * caso de uso (`registrarCondiciones()`), sin pasar por HTTP — eso lo cubre
 * `tests/Feature/Api/CondicionesSincronizacionTest.php`.
 */

/** Abre trabajo + sesión vía el propio contrato, para referenciar por `uuid_cliente` en los tests de abajo. */
function sesionAbiertaParaCondiciones(string $id): Sesion
{
    $orden = ordenVigenteParaEscritura();
    $piloto = pilotoParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->abrirTrabajo(AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => "uuid-trabajo-cond-{$id}",
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]));

    $contrato->abrirSesion(AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => "uuid-sesion-cond-{$id}",
        'trabajo_uuid_cliente' => "uuid-trabajo-cond-{$id}",
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]));

    return Sesion::query()->where('uuid_cliente', "uuid-sesion-cond-{$id}")->firstOrFail();
}

/** @return array<string, mixed> */
function registroCondicionesArreglo(array $sobrescribir = []): array
{
    return array_merge([
        'uuid_cliente' => 'uuid-condiciones-default',
        'sesion_uuid_cliente' => 'uuid-sesion-cond-default',
        'momento' => 'inicio_sesion',
        'viento_kmh' => '10.00',
        'temperatura_c' => '22.00',
        'humedad_pct' => '60.00',
    ], $sobrescribir);
}

test('RegistroCondiciones::intentarDesdeArreglo devuelve null ante un campo requerido faltante', function () {
    expect(RegistroCondiciones::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-incompleto',
        'sesion_uuid_cliente' => 'uuid-sesion',
        // falta momento, viento_kmh, temperatura_c, humedad_pct
    ]))->toBeNull();
});

test('RegistroCondiciones::intentarDesdeArreglo devuelve null con un momento fuera de catálogo (incidencia es HU-08)', function () {
    expect(RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo(['momento' => 'incidencia'])))->toBeNull();
});

test('RegistroCondiciones::intentarDesdeArreglo devuelve null con viento_kmh negativo', function () {
    expect(RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo(['viento_kmh' => '-1'])))->toBeNull();
});

test('RegistroCondiciones::intentarDesdeArreglo devuelve null con humedad_pct no numérica', function () {
    expect(RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo(['humedad_pct' => ['no', 'numerico']])))->toBeNull();
});

test('RegistroCondiciones::intentarDesdeArreglo acepta temperatura_c negativa (a diferencia de viento/humedad, sí puede ser negativa)', function () {
    $datos = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo(['temperatura_c' => '-5.50']));

    expect($datos)->not->toBeNull()
        ->and($datos->temperaturaC)->toBe('-5.50');
});

test('RegistroCondiciones::dentroDeRango es true justo en el límite superior de cada umbral (17/30/90)', function () {
    $datos = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo([
        'viento_kmh' => '17',
        'temperatura_c' => '30',
        'humedad_pct' => '90',
    ]));

    expect($datos->dentroDeRango())->toBeTrue();
});

test('RegistroCondiciones::dentroDeRango es false apenas por encima de un umbral', function () {
    $datos = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo(['viento_kmh' => '17.01']));

    expect($datos->dentroDeRango())->toBeFalse();
});

test('RegistroCondiciones::tieneObservacionFirmada exige observación Y firma, no alcanza con una sola', function () {
    $soloObservacion = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo(['observacion_agronomo' => 'vuela igual, ventana angosta']));
    $soloFirma = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo(['firma_observacion' => 'Agr. Pérez']));
    $ambas = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo([
        'observacion_agronomo' => 'vuela igual, ventana angosta',
        'firma_observacion' => 'Agr. Pérez',
    ]));

    expect($soloObservacion->tieneObservacionFirmada())->toBeFalse()
        ->and($soloFirma->tieneObservacionFirmada())->toBeFalse()
        ->and($ambas->tieneObservacionFirmada())->toBeTrue();
});

test('registrarCondiciones dentro de rango aplica autorizado, sin observación, con trabajo_id resuelto de la sesión', function () {
    $sesion = sesionAbiertaParaCondiciones('a');
    $contrato = app(EscrituraSincronizacion::class);

    $datos = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo([
        'uuid_cliente' => 'uuid-condiciones-a',
        'sesion_uuid_cliente' => $sesion->uuid_cliente,
    ]));

    $resultado = $contrato->registrarCondiciones($datos);

    expect($resultado->estado)->toBe('aplicado');

    $condiciones = Condiciones::query()->where('uuid_cliente', 'uuid-condiciones-a')->firstOrFail();
    expect($condiciones->autorizado)->toBeTrue()
        ->and($condiciones->resultado())->toBe('autorizado')
        ->and($condiciones->trabajo_id)->toBe($sesion->trabajo_id)
        ->and($condiciones->sesion_id)->toBe($sesion->id)
        ->and($condiciones->observacion_agronomo)->toBeNull();
});

test('registrarCondiciones fuera de rango con observación firmada aplica autorizado_con_observacion', function () {
    $sesion = sesionAbiertaParaCondiciones('b');
    $contrato = app(EscrituraSincronizacion::class);

    $datos = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo([
        'uuid_cliente' => 'uuid-condiciones-b',
        'sesion_uuid_cliente' => $sesion->uuid_cliente,
        'viento_kmh' => '25.00',
        'observacion_agronomo' => 'viento fuerte, se autoriza por ventana angosta',
        'firma_observacion' => 'Agr. Pérez',
    ]));

    $resultado = $contrato->registrarCondiciones($datos);

    expect($resultado->estado)->toBe('aplicado');

    $condiciones = Condiciones::query()->where('uuid_cliente', 'uuid-condiciones-b')->firstOrFail();
    expect($condiciones->autorizado)->toBeFalse()
        ->and($condiciones->resultado())->toBe('autorizado_con_observacion')
        ->and($condiciones->observacion_agronomo)->not->toBeNull()
        ->and($condiciones->firma_observacion)->toBe('Agr. Pérez');
});

test('registrarCondiciones fuera de rango sin observación se rechaza sin crear fila', function () {
    $sesion = sesionAbiertaParaCondiciones('c');
    $contrato = app(EscrituraSincronizacion::class);

    $datos = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo([
        'uuid_cliente' => 'uuid-condiciones-c',
        'sesion_uuid_cliente' => $sesion->uuid_cliente,
        'humedad_pct' => '95.00',
    ]));

    $resultado = $contrato->registrarCondiciones($datos);

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();

    expect(Condiciones::query()->where('uuid_cliente', 'uuid-condiciones-c')->exists())->toBeFalse();
});

test('registrarCondiciones con el mismo uuid_cliente responde duplicado sin crear una fila nueva', function () {
    $sesion = sesionAbiertaParaCondiciones('d');
    $contrato = app(EscrituraSincronizacion::class);

    $datos = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo([
        'uuid_cliente' => 'uuid-condiciones-d',
        'sesion_uuid_cliente' => $sesion->uuid_cliente,
    ]));

    expect($contrato->registrarCondiciones($datos)->estado)->toBe('aplicado');

    $resultado = $contrato->registrarCondiciones($datos);

    expect($resultado->estado)->toBe('duplicado')
        ->and(Condiciones::query()->where('uuid_cliente', 'uuid-condiciones-d')->count())->toBe(1);
});

test('registrarCondiciones con una sesion_uuid_cliente que no existe se rechaza sin romper nada', function () {
    $contrato = app(EscrituraSincronizacion::class);

    $datos = RegistroCondiciones::intentarDesdeArreglo(registroCondicionesArreglo([
        'uuid_cliente' => 'uuid-condiciones-huerfana',
        'sesion_uuid_cliente' => 'uuid-sesion-que-no-existe',
    ]));

    $resultado = $contrato->registrarCondiciones($datos);

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();

    expect(Condiciones::query()->where('uuid_cliente', 'uuid-condiciones-huerfana')->exists())->toBeFalse();
});

/*
 * ── Recepción de caldo (espec §7.2, HU-10 redefinida por CR-01, tarea 18) ──
 */

/** Abre solo un trabajo vía el propio contrato (recepción no depende de sesión). */
function trabajoAbiertoParaRecepcion(string $id): Trabajo
{
    $orden = ordenVigenteParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->abrirTrabajo(AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => "uuid-trabajo-recepcion-{$id}",
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]));

    return Trabajo::query()->where('uuid_cliente', "uuid-trabajo-recepcion-{$id}")->firstOrFail();
}

/** @return array<string, mixed> */
function registroRecepcionArreglo(array $sobrescribir = []): array
{
    return array_merge([
        'uuid_cliente' => 'uuid-recepcion-default',
        'trabajo_uuid_cliente' => 'uuid-trabajo-recepcion-default',
        'litros' => '200.00',
        'entregado_por' => 'Ing. Agr. del cliente',
        'hora' => '2026-09-01T09:00:00-04:00',
    ], $sobrescribir);
}

test('RegistroRecepcionCaldo::intentarDesdeArreglo devuelve null ante un campo requerido faltante', function () {
    expect(RegistroRecepcionCaldo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-incompleto',
        'trabajo_uuid_cliente' => 'uuid-trabajo',
        // falta litros, entregado_por, hora
    ]))->toBeNull();
});

test('RegistroRecepcionCaldo::intentarDesdeArreglo devuelve null con litros negativo', function () {
    expect(RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo(['litros' => '-5'])))->toBeNull();
});

test('RegistroRecepcionCaldo::intentarDesdeArreglo devuelve null con litros no numérico', function () {
    expect(RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo(['litros' => 'no-numerico'])))->toBeNull();
});

test('RegistroRecepcionCaldo::intentarDesdeArreglo devuelve null con entregado_por vacío', function () {
    expect(RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo(['entregado_por' => ''])))->toBeNull();
});

test('registrarRecepcionCaldo con datos válidos aplica y persiste litros/entregado_por/hora contra el trabajo correcto', function () {
    $trabajo = trabajoAbiertoParaRecepcion('a');
    $contrato = app(EscrituraSincronizacion::class);

    $datos = RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo([
        'uuid_cliente' => 'uuid-recepcion-a',
        'trabajo_uuid_cliente' => $trabajo->uuid_cliente,
    ]));

    $resultado = $contrato->registrarRecepcionCaldo($datos);

    expect($resultado->estado)->toBe('aplicado');

    $recepcion = RecepcionCaldo::query()->where('uuid_cliente', 'uuid-recepcion-a')->firstOrFail();
    expect($recepcion->trabajo_id)->toBe($trabajo->id)
        ->and($recepcion->litros)->toBe('200.00')
        ->and($recepcion->entregado_por)->toBe('Ing. Agr. del cliente');
});

test('registrarRecepcionCaldo permite VARIOS eventos para el mismo trabajo', function () {
    $trabajo = trabajoAbiertoParaRecepcion('b');
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->registrarRecepcionCaldo(RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo([
        'uuid_cliente' => 'uuid-recepcion-b1',
        'trabajo_uuid_cliente' => $trabajo->uuid_cliente,
        'litros' => '120.00',
    ])));
    $contrato->registrarRecepcionCaldo(RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo([
        'uuid_cliente' => 'uuid-recepcion-b2',
        'trabajo_uuid_cliente' => $trabajo->uuid_cliente,
        'litros' => '80.00',
    ])));

    // El total formateado (`200.00` exacto) se verifica vía `cuadreCaldo()`
    // más abajo, no sumando la columna cruda acá: `sum()` de la query
    // builder devuelve el valor tal cual lo entrega el driver — sin
    // decimales de más cuando el total cae en un entero exacto (`200`, no
    // `200.00`) — y normalizar eso es responsabilidad de `cuadreCaldo()`,
    // no de este test.
    expect($trabajo->refresh()->cuadreCaldo()['recibido'])->toBe('200.00');
});

test('registrarRecepcionCaldo con el mismo uuid_cliente responde duplicado sin crear una fila nueva', function () {
    $trabajo = trabajoAbiertoParaRecepcion('c');
    $contrato = app(EscrituraSincronizacion::class);

    $datos = RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo([
        'uuid_cliente' => 'uuid-recepcion-c',
        'trabajo_uuid_cliente' => $trabajo->uuid_cliente,
    ]));

    expect($contrato->registrarRecepcionCaldo($datos)->estado)->toBe('aplicado');

    $resultado = $contrato->registrarRecepcionCaldo($datos);

    expect($resultado->estado)->toBe('duplicado')
        ->and(RecepcionCaldo::query()->where('uuid_cliente', 'uuid-recepcion-c')->count())->toBe(1);
});

test('registrarRecepcionCaldo con un trabajo_uuid_cliente que no existe se rechaza sin romper nada', function () {
    $contrato = app(EscrituraSincronizacion::class);

    $datos = RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo([
        'uuid_cliente' => 'uuid-recepcion-huerfana',
        'trabajo_uuid_cliente' => 'uuid-trabajo-que-no-existe',
    ]));

    $resultado = $contrato->registrarRecepcionCaldo($datos);

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();

    expect(RecepcionCaldo::query()->where('uuid_cliente', 'uuid-recepcion-huerfana')->exists())->toBeFalse();
});

/*
 * ── litros_consumidos (CierreSesion) y litros_sobrante (CierreTrabajo) ──
 */

test('CierreSesion::intentarDesdeArreglo acepta litros_consumidos ausente (null) sin rechazar el registro', function () {
    $datos = CierreSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-sin-litros',
        'sesion_uuid_cliente' => 'uuid-sesion',
        'fin' => '2026-09-01T12:00:00-04:00',
        'motivo_cierre' => 'completado',
        'hectareas_declaradas' => '10.00',
    ]);

    expect($datos)->not->toBeNull()
        ->and($datos->litrosConsumidos)->toBeNull();
});

test('CierreSesion::intentarDesdeArreglo devuelve null con litros_consumidos negativo (el resto del registro no la salva)', function () {
    expect(CierreSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-litros-invalidos',
        'sesion_uuid_cliente' => 'uuid-sesion',
        'fin' => '2026-09-01T12:00:00-04:00',
        'motivo_cierre' => 'completado',
        'hectareas_declaradas' => '10.00',
        'litros_consumidos' => '-3',
    ]))->toBeNull();
});

test('CierreTrabajo::intentarDesdeArreglo acepta litros_sobrante ausente (null) sin rechazar el registro', function () {
    $datos = CierreTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-trabajo-sin-litros',
        'trabajo_uuid_cliente' => 'uuid-trabajo',
        'fin' => '2026-09-01T12:00:00-04:00',
        'evidencia_imagen_campo_uuid_cliente' => 'uuid-evidencia-cualquiera',
    ]);

    expect($datos)->not->toBeNull()
        ->and($datos->litrosSobrante)->toBeNull();
});

test('CierreTrabajo::intentarDesdeArreglo devuelve null con litros_sobrante no numérico', function () {
    expect(CierreTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-trabajo-litros-invalidos',
        'trabajo_uuid_cliente' => 'uuid-trabajo',
        'fin' => '2026-09-01T12:00:00-04:00',
        'litros_sobrante' => 'no-numerico',
        'evidencia_imagen_campo_uuid_cliente' => 'uuid-evidencia-cualquiera',
    ]))->toBeNull();
});

test('CierreTrabajo::intentarDesdeArreglo devuelve null sin evidencia_imagen_campo_uuid_cliente (HU-09: sin captura no cierra)', function () {
    expect(CierreTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-trabajo-sin-evidencia',
        'trabajo_uuid_cliente' => 'uuid-trabajo',
        'fin' => '2026-09-01T12:00:00-04:00',
    ]))->toBeNull();
});

test('cerrarSesion persiste litros_consumidos cuando el registro lo trae, y lo deja null cuando no', function () {
    $sesionCon = sesionAbiertaParaCondiciones('litros-con');
    $sesionSin = sesionAbiertaParaCondiciones('litros-sin');
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->cerrarSesion(CierreSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-con-litros',
        'sesion_uuid_cliente' => $sesionCon->uuid_cliente,
        'fin' => '2026-09-01T12:00:00-04:00',
        'motivo_cierre' => 'completado',
        'hectareas_declaradas' => '10.00',
        'litros_consumidos' => '95.50',
    ]), null);

    $contrato->cerrarSesion(CierreSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-sin-litros-2',
        'sesion_uuid_cliente' => $sesionSin->uuid_cliente,
        'fin' => '2026-09-01T12:00:00-04:00',
        'motivo_cierre' => 'falla_equipo',
        'hectareas_declaradas' => '0.00',
    ]), null);

    expect($sesionCon->refresh()->litros_consumidos)->toBe('95.50')
        ->and($sesionSin->refresh()->litros_consumidos)->toBeNull();
});

test('cerrarTrabajo persiste litros_sobrante cuando el registro lo trae, y lo deja null cuando no', function () {
    $trabajoCon = trabajoAbiertoParaRecepcion('sobrante-con');
    $trabajoSin = trabajoAbiertoParaRecepcion('sobrante-sin');
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->cerrarTrabajo(CierreTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-trabajo-con-sobrante',
        'trabajo_uuid_cliente' => $trabajoCon->uuid_cliente,
        'fin' => '2026-09-01T12:00:00-04:00',
        'litros_sobrante' => '15.00',
        'evidencia_imagen_campo_uuid_cliente' => evidenciaImagenCampoParaEscritura('sobrante-con'),
    ]), null);

    $contrato->cerrarTrabajo(CierreTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-trabajo-sin-sobrante',
        'trabajo_uuid_cliente' => $trabajoSin->uuid_cliente,
        'fin' => '2026-09-01T12:00:00-04:00',
        'evidencia_imagen_campo_uuid_cliente' => evidenciaImagenCampoParaEscritura('sobrante-sin'),
    ]), null);

    expect($trabajoCon->refresh()->litros_sobrante)->toBe('15.00')
        ->and($trabajoSin->refresh()->litros_sobrante)->toBeNull();
});

/*
 * ── Cuadre (espec §7.3, criterio de aceptación 4 de la tarea 18):
 *    recibido == consumido + sobrante, recalculado desde los registros de
 *    origen (invariante 6) ──
 */

test('Trabajo::cuadreCaldo recalcula recibido/consumido/sobrante exacto y cuadra cuando coinciden', function () {
    $orden = ordenVigenteParaEscritura();
    $piloto = pilotoParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->abrirTrabajo(AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-cuadre',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T08:00:00-04:00',
    ]));

    // Dos entregas de caldo: 120 + 80 = 200 recibidos.
    $contrato->registrarRecepcionCaldo(RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo([
        'uuid_cliente' => 'uuid-recepcion-cuadre-1',
        'trabajo_uuid_cliente' => 'uuid-trabajo-cuadre',
        'litros' => '120.00',
    ])));
    $contrato->registrarRecepcionCaldo(RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo([
        'uuid_cliente' => 'uuid-recepcion-cuadre-2',
        'trabajo_uuid_cliente' => 'uuid-trabajo-cuadre',
        'litros' => '80.00',
    ])));

    // Dos sesiones: 130.50 + 54.50 = 185.00 consumidos.
    $contrato->abrirSesion(AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-sesion-cuadre-1',
        'trabajo_uuid_cliente' => 'uuid-trabajo-cuadre',
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => '2026-09-01T08:10:00-04:00',
    ]));
    $contrato->abrirSesion(AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-sesion-cuadre-2',
        'trabajo_uuid_cliente' => 'uuid-trabajo-cuadre',
        'secuencia' => 2,
        'piloto_id' => $piloto->id,
        'inicio' => '2026-09-01T09:00:00-04:00',
    ]));
    $contrato->cerrarSesion(CierreSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-sesion-cuadre-1',
        'sesion_uuid_cliente' => 'uuid-sesion-cuadre-1',
        'fin' => '2026-09-01T08:55:00-04:00',
        'motivo_cierre' => 'completado',
        'hectareas_declaradas' => '12.00',
        'litros_consumidos' => '130.50',
    ]), null);
    $contrato->cerrarSesion(CierreSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-sesion-cuadre-2',
        'sesion_uuid_cliente' => 'uuid-sesion-cuadre-2',
        'fin' => '2026-09-01T09:45:00-04:00',
        'motivo_cierre' => 'completado',
        'hectareas_declaradas' => '8.00',
        'litros_consumidos' => '54.50',
    ]), null);

    // Sobrante declarado al cerrar el trabajo: 200 - 185 = 15.
    $contrato->cerrarTrabajo(CierreTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-trabajo-cuadre',
        'trabajo_uuid_cliente' => 'uuid-trabajo-cuadre',
        'fin' => '2026-09-01T10:00:00-04:00',
        'litros_sobrante' => '15.00',
        'evidencia_imagen_campo_uuid_cliente' => evidenciaImagenCampoParaEscritura('cuadre'),
    ]), null);

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-cuadre')->firstOrFail();
    $cuadre = $trabajo->cuadreCaldo();

    expect($cuadre['recibido'])->toBe('200.00')
        ->and($cuadre['consumido'])->toBe('185.00')
        ->and($cuadre['sobrante'])->toBe('15.00')
        ->and($cuadre['cuadra'])->toBeTrue();
});

test('Trabajo::cuadreCaldo marca cuadra en false cuando recibido no coincide con consumido + sobrante', function () {
    $trabajo = trabajoAbiertoParaRecepcion('descuadrado');
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->registrarRecepcionCaldo(RegistroRecepcionCaldo::intentarDesdeArreglo(registroRecepcionArreglo([
        'uuid_cliente' => 'uuid-recepcion-descuadrada',
        'trabajo_uuid_cliente' => $trabajo->uuid_cliente,
        'litros' => '200.00',
    ])));

    $contrato->cerrarTrabajo(CierreTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-cierre-trabajo-descuadrado',
        'trabajo_uuid_cliente' => $trabajo->uuid_cliente,
        'fin' => '2026-09-01T12:00:00-04:00',
        'litros_sobrante' => '15.00',
        'evidencia_imagen_campo_uuid_cliente' => evidenciaImagenCampoParaEscritura('descuadrado'),
    ]), null);

    // Sin sesiones (consumido = 0): recibido 200 != sobrante 15 + consumido 0.
    $cuadre = $trabajo->refresh()->cuadreCaldo();

    expect($cuadre['recibido'])->toBe('200.00')
        ->and($cuadre['consumido'])->toBe('0.00')
        ->and($cuadre['sobrante'])->toBe('15.00')
        ->and($cuadre['cuadra'])->toBeFalse();
});

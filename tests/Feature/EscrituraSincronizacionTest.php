<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Contratos\AperturaSesion;
use App\Dominios\Operaciones\Contratos\AperturaTrabajo;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Condiciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
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

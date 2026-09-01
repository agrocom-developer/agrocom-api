<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Finanzas\Dominio\Excepciones\PersonaSinTarifaHa;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Eventos\SesionValidada;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-16, tarea 16: el oyente real de `SesionValidada` genera el devengo del
 * piloto y su auxiliar. Criterios de aceptación 1, 2, 3 y 5 (ver el prompt
 * de la tarea): piloto+auxiliar → dos filas; sin auxiliar → una; reintento
 * → no duplica; decimales difíciles → monto exacto. El gate de la
 * invariante 3 (`cerrar()` nunca genera devengo) vive en
 * tests/Feature/Finanzas/InvarianteDevengoSoloAlValidarTest.php.
 */

uses(RefreshDatabase::class);

function trabajoAbiertoParaDevengo(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de devengo']);
    $campo = Campo::create(['cliente_id' => $cliente->id, 'nombre' => 'Campo de devengo']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-DEVENGO', 'hectareas' => '50.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '50.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '500.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    return Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-devengo-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
}

/**
 * Sesión `cerrado`, lista para validar. `$tarifaPiloto`/`$tarifaAuxiliar` en
 * `null` deja a esa persona sin `tarifa_ha` — el caso que decide
 * `GenerarDevengosSesion`.
 *
 * @return array{0: Sesion, 1: PerPersona, 2: ?PerPersona}
 */
function sesionParaDevengo(
    string $hectareas = '12.00',
    ?string $tarifaPiloto = '150.00',
    bool $conAuxiliar = false,
    ?string $tarifaAuxiliar = '100.00',
): array {
    $trabajo = trabajoAbiertoParaDevengo();

    $piloto = PerPersona::create([
        'nombre' => 'Piloto de devengo',
        'rol' => RolOperativoPersona::Piloto,
        'tarifa_ha' => $tarifaPiloto,
        'activo' => true,
    ]);

    $auxiliar = $conAuxiliar
        ? PerPersona::create([
            'nombre' => 'Auxiliar de devengo',
            'rol' => RolOperativoPersona::Auxiliar,
            'tarifa_ha' => $tarifaAuxiliar,
            'activo' => true,
        ])
        : null;

    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-devengo-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'auxiliar_id' => $auxiliar?->id,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-09-01T10:05:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-devengo-'.uniqid(),
    ]);

    return [$sesion, $piloto, $auxiliar];
}

function jefeValidadorParaDevengo(): PerPersona
{
    return PerPersona::create(['nombre' => 'Jefe validador de devengo', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);
}

test('validar una sesión con piloto y auxiliar genera dos devengos, cada uno con su tarifa y monto', function () {
    [$sesion, $piloto, $auxiliar] = sesionParaDevengo(hectareas: '12.00', tarifaPiloto: '150.00', conAuxiliar: true, tarifaAuxiliar: '100.00');
    $jefe = jefeValidadorParaDevengo();

    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);

    expect(DevengoPersonal::query()->count())->toBe(2);

    $devengoPiloto = DevengoPersonal::query()->where('sesion_id', $sesion->id)->where('persona_id', $piloto->id)->sole();
    expect($devengoPiloto->hectareas)->toBe('12.00')
        ->and($devengoPiloto->tarifa_ha)->toBe('150.00')
        ->and($devengoPiloto->monto)->toBe('1800.00');

    $devengoAuxiliar = DevengoPersonal::query()->where('sesion_id', $sesion->id)->where('persona_id', $auxiliar->id)->sole();
    expect($devengoAuxiliar->hectareas)->toBe('12.00')
        ->and($devengoAuxiliar->tarifa_ha)->toBe('100.00')
        ->and($devengoAuxiliar->monto)->toBe('1200.00');
});

test('validar una sesión sin auxiliar genera un solo devengo, el del piloto', function () {
    [$sesion, $piloto] = sesionParaDevengo(conAuxiliar: false);
    $jefe = jefeValidadorParaDevengo();

    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);

    $devengo = DevengoPersonal::query()->sole();
    expect($devengo->persona_id)->toBe($piloto->id)
        ->and($devengo->sesion_id)->toBe($sesion->id);
});

test('un caso con decimales que un float redondearía mal da el monto exacto', function () {
    // hectareas = 3.33, tarifa_ha = 12.35 → producto exacto 41.1255 →
    // redondeado al centavo (mitad hacia arriba) 41.13. Ver el docblock de
    // GenerarDevengosSesion::calcularMonto() para el porqué de redondear en
    // vez de truncar.
    [$sesion] = sesionParaDevengo(hectareas: '3.33', tarifaPiloto: '12.35');
    $jefe = jefeValidadorParaDevengo();

    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);

    $devengo = DevengoPersonal::query()->sole();
    expect($devengo->hectareas)->toBe('3.33')
        ->and($devengo->tarifa_ha)->toBe('12.35')
        ->and($devengo->monto)->toBe('41.13');
});

test('una persona sin tarifa_ha interrumpe la validación completa, sin devengo con monto 0 o null en silencio', function () {
    [$sesion, $piloto] = sesionParaDevengo(tarifaPiloto: null);
    $jefe = jefeValidadorParaDevengo();

    expect(fn () => (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id))
        ->toThrow(PersonaSinTarifaHa::class);

    // La transacción de MaquinaEstadosSesion::validar() revirtió todo: la
    // sesión sigue cerrado (invariante 6 — nada de un estado a medias) y no
    // quedó ningún devengo huérfano.
    expect(Sesion::query()->findOrFail($sesion->id)->estado)->toBe(EstadoSesion::Cerrado)
        ->and(DevengoPersonal::query()->count())->toBe(0);
});

test('un auxiliar sin tarifa_ha interrumpe la validación aunque el piloto sí tenga tarifa', function () {
    [$sesion] = sesionParaDevengo(tarifaPiloto: '150.00', conAuxiliar: true, tarifaAuxiliar: null);
    $jefe = jefeValidadorParaDevengo();

    expect(fn () => (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id))
        ->toThrow(PersonaSinTarifaHa::class);

    // El devengo del piloto (generado antes de llegar al auxiliar) también
    // se revierte: la transacción es todo o nada, no "lo que se pudo".
    expect(Sesion::query()->findOrFail($sesion->id)->estado)->toBe(EstadoSesion::Cerrado)
        ->and(DevengoPersonal::query()->count())->toBe(0);
});

test('reintentar validar() sobre la misma sesión no duplica devengos', function () {
    [$sesion] = sesionParaDevengo(conAuxiliar: true);
    $jefe = jefeValidadorParaDevengo();

    $maquina = new MaquinaEstadosSesion;
    $maquina->validar($sesion, $jefe->id);
    $maquina->validar($sesion->refresh(), $jefe->id);

    expect(DevengoPersonal::query()->count())->toBe(2);
});

test('el listener es idempotente por UNIQUE aunque el evento se dispare dos veces (segunda capa, detrás del guard de validar())', function () {
    // A diferencia del test anterior, esto pasa por alto el guard temprano
    // de MaquinaEstadosSesion::validar() (que ya evita re-disparar el evento
    // sobre una sesión ya validada) para probar la capa de abajo, la que
    // documenta el docblock de SesionValidada: "idempotente por UNIQUE
    // (sesion_id, persona_id)". Sin esta segunda capa, un reintento del
    // EVENTO en sí —no de validar()— duplicaría el devengo.
    [$sesion] = sesionParaDevengo(conAuxiliar: true);

    event(new SesionValidada($sesion->id));
    event(new SesionValidada($sesion->id));

    expect(DevengoPersonal::query()->count())->toBe(2);
});

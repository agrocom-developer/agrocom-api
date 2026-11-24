<?php

use App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\TransicionesSolicitud;

/*
 * Pasos que dibuja `molecules/step-arrow`. Puro, sin app ni DB: los textos
 * salen como la clave de idioma (Texto::de() sin framework levantado). Se
 * prueba contra la tabla real de `campania`, que es justo lo que el helper
 * tiene que respetar: lo dibujado nunca contradice `TransicionesSolicitud`.
 */

/**
 * @return list<array{key: string, label: string, tone: string, status: string, modal: string|null, hint: string|null}>
 */
function pasosDeSolicitud(EstadoSolicitud $actual, bool $puedeCambiar = true): array
{
    return PasosDeEstado::armar(
        ruta: [EstadoSolicitud::Borrador, EstadoSolicitud::Abierta, EstadoSolicitud::Adjudicada],
        actual: $actual,
        // El regreso de una adjudicada a abierta es del proveedor (cancela el servicio), no un paso del flujo.
        permitida: fn (EstadoSolicitud $desde, EstadoSolicitud $hacia): bool => TransicionesSolicitud::permitida($desde, $hacia)
            && ! ($desde === EstadoSolicitud::Adjudicada && $hacia === EstadoSolicitud::Abierta),
        tonos: ['borrador' => 'neutral', 'abierta' => 'success', 'adjudicada' => 'alert'],
        claveEtiqueta: 'solicitudes.estado',
        prefijoModal: 'solicitud-estado-modal',
        puedeCambiar: $puedeCambiar,
    );
}

/**
 * @param  list<array{status: string}>  $pasos
 * @return list<string>
 */
function situaciones(array $pasos): array
{
    return array_column($pasos, 'status');
}

test('pasos: planificada es el actual, abierta es accionable y cerrada está bloqueada', function () {
    $pasos = pasosDeSolicitud(EstadoSolicitud::Borrador);

    expect(situaciones($pasos))->toBe(['current', 'next', 'blocked'])
        ->and($pasos[1]['modal'])->toBe('solicitud-estado-modal-abierta')
        ->and($pasos[0]['modal'])->toBeNull()
        ->and($pasos[2]['modal'])->toBeNull();
});

test('pasos: abierta deja planificada completada y cerrada accionable', function () {
    expect(situaciones(pasosDeSolicitud(EstadoSolicitud::Abierta)))->toBe(['completed', 'current', 'next']);
});

test('pasos: cerrada es terminal, todo lo anterior queda completado y nada es accionable', function () {
    $pasos = pasosDeSolicitud(EstadoSolicitud::Adjudicada);

    expect(situaciones($pasos))->toBe(['completed', 'completed', 'current'])
        ->and(array_column($pasos, 'modal'))->each->toBeNull();
});

test('pasos: sin permiso el paso alcanzable queda pendiente, con su pista y sin modal', function () {
    $pasos = pasosDeSolicitud(EstadoSolicitud::Borrador, puedeCambiar: false);

    expect(situaciones($pasos))->toBe(['current', 'pending', 'blocked'])
        ->and($pasos[1]['modal'])->toBeNull()
        ->and($pasos[1]['hint'])->toBe('ui.pasos.pista_sin_permiso');
});

test('pasos: el paso bloqueado explica por cuál hay que pasar antes', function () {
    $pasos = pasosDeSolicitud(EstadoSolicitud::Borrador);

    expect($pasos[2]['hint'])->toBe('ui.pasos.pista_bloqueado')
        ->and($pasos[0]['hint'])->toBeNull()
        ->and($pasos[1]['hint'])->toBeNull();
});

test('pasos: cada paso trae su clave, su etiqueta y el tono de su estado', function () {
    $pasos = pasosDeSolicitud(EstadoSolicitud::Abierta);

    expect(array_column($pasos, 'key'))->toBe(['borrador', 'abierta', 'adjudicada'])
        ->and($pasos[1]['label'])->toBe('solicitudes.estado.abierta')
        ->and(array_column($pasos, 'tone'))->toBe(['neutral', 'success', 'alert']);
});

test('pasos: un estado sin tono declarado cae en neutral', function () {
    $pasos = PasosDeEstado::armar(
        ruta: [EstadoSolicitud::Borrador, EstadoSolicitud::Abierta],
        actual: EstadoSolicitud::Borrador,
        permitida: TransicionesSolicitud::permitida(...),
        tonos: [],
        claveEtiqueta: 'solicitudes.estado',
        prefijoModal: 'm',
        puedeCambiar: true,
    );

    expect(array_column($pasos, 'tone'))->toBe(['neutral', 'neutral']);
});

test('pasos: ningún paso es accionable si la tabla de transiciones no lo permite', function () {
    foreach (EstadoSolicitud::cases() as $actual) {
        foreach (pasosDeSolicitud($actual) as $paso) {
            if ($paso['status'] === 'next') {
                expect(TransicionesSolicitud::permitida($actual, EstadoSolicitud::from($paso['key'])))->toBeTrue();
            }
        }
    }
});

/*
 * Párrafo de ayuda (PasosDeEstado::ayuda). Sin framework, cada texto sale como
 * su clave: se prueba QUÉ claves se piden y en qué orden, y el catálogo real
 * (lang/es) se vigila aparte más abajo.
 */
test('ayuda: con permiso lleva el texto del estado y la invitación a hacer clic', function () {
    $ayuda = PasosDeEstado::ayuda(pasosDeSolicitud(EstadoSolicitud::Borrador), 'solicitudes.estado_ayuda');

    expect($ayuda)->toBe('solicitudes.estado_ayuda.borrador ui.pasos.ayuda_accion');
});

test('ayuda: sin permiso avisa que el rol no puede cambiar el estado, en vez de invitar a hacer clic', function () {
    $ayuda = PasosDeEstado::ayuda(pasosDeSolicitud(EstadoSolicitud::Abierta, puedeCambiar: false), 'solicitudes.estado_ayuda');

    expect($ayuda)->toBe('solicitudes.estado_ayuda.abierta ui.pasos.ayuda_sin_permiso');
});

test('ayuda: un estado final lleva solo el texto del objeto, sin paso al que ir', function () {
    expect(PasosDeEstado::ayuda(pasosDeSolicitud(EstadoSolicitud::Adjudicada), 'solicitudes.estado_ayuda'))
        ->toBe('solicitudes.estado_ayuda.adjudicada');
});

test('ayuda: sin estado actual entre los pasos no hay párrafo', function () {
    expect(PasosDeEstado::ayuda([], 'solicitudes.estado_ayuda'))->toBeNull();
});

/*
 * Opciones para una máquina con desvíos y salidas (contrato): todas son
 * opcionales y, sin ellas, el resultado es el de la solicitud de arriba. El
 * caso real de cada una se prueba en `PasosDeContratoTest`.
 */
function pasosDeSolicitudCon(EstadoSolicitud $actual, mixed ...$opciones): array
{
    return PasosDeEstado::armar(...[
        'ruta' => [EstadoSolicitud::Borrador, EstadoSolicitud::Abierta, EstadoSolicitud::Adjudicada],
        'actual' => $actual,
        'permitida' => fn (EstadoSolicitud $desde, EstadoSolicitud $hacia): bool => TransicionesSolicitud::permitida($desde, $hacia)
            && ! ($desde === EstadoSolicitud::Adjudicada && $hacia === EstadoSolicitud::Abierta),
        'tonos' => [],
        'claveEtiqueta' => 'solicitudes.estado',
        'prefijoModal' => 'm',
        'puedeCambiar' => true,
        ...$opciones,
    ]);
}

test('pasos: los recorridos explícitos reemplazan a la posición para decidir qué está completado', function () {
    // Por posición, cerrada deja planificada y abierta completadas; diciendo que
    // no se recorrió ninguna, quedan bloqueadas.
    expect(situaciones(pasosDeSolicitudCon(EstadoSolicitud::Adjudicada)))->toBe(['completed', 'completed', 'current'])
        ->and(situaciones(pasosDeSolicitudCon(EstadoSolicitud::Adjudicada, recorridos: [])))->toBe(['blocked', 'blocked', 'current'])
        ->and(situaciones(pasosDeSolicitudCon(EstadoSolicitud::Adjudicada, recorridos: [EstadoSolicitud::Borrador])))->toBe(['completed', 'blocked', 'current']);
});

test('pasos: una pista propia reemplaza al «pasa antes por…» del paso bloqueado, y solo a ese', function () {
    $pasos = pasosDeSolicitudCon(EstadoSolicitud::Borrador, pistas: ['adjudicada' => 'Motivo propio', 'abierta' => 'No se usa']);

    expect($pasos[2]['hint'])->toBe('Motivo propio')
        // `abierta` no está bloqueado: la pista no le corresponde.
        ->and($pasos[1]['hint'])->toBeNull();
});

test('pasos: el ícono de un paso se toma de la lista y por defecto no hay', function () {
    $pasos = pasosDeSolicitudCon(EstadoSolicitud::Abierta, iconos: ['adjudicada' => 'cancel']);

    expect(array_column($pasos, 'icon'))->toBe([null, null, 'cancel']);
});

test('pasos: el «pasa antes por…» nombra el primer paso al que sí se puede ir', function () {
    // Desde planificada solo se puede ir a abierta: es el que hay que dar antes de llegar a cerrada.
    expect(pasosDeSolicitud(EstadoSolicitud::Borrador)[2]['hint'])->toBe('ui.pasos.pista_bloqueado');
});

test('pasos: el permiso por destino se suma al general, y sin él el paso queda pendiente', function () {
    // Puede cambiar de estado en general, pero no ir a «cerrada»: solo ese paso queda pendiente.
    $sinCerrar = fn (EstadoSolicitud $hacia): bool => $hacia !== EstadoSolicitud::Adjudicada;

    expect(situaciones(pasosDeSolicitudCon(EstadoSolicitud::Abierta, puedeIrA: $sinCerrar)))->toBe(['completed', 'current', 'pending'])
        ->and(pasosDeSolicitudCon(EstadoSolicitud::Abierta, puedeIrA: $sinCerrar)[2]['hint'])->toBe('ui.pasos.pista_sin_permiso')
        ->and(pasosDeSolicitudCon(EstadoSolicitud::Abierta, puedeIrA: $sinCerrar)[2]['modal'])->toBeNull();

    // Sin el permiso general, ningún permiso por destino lo devuelve.
    expect(situaciones(pasosDeSolicitudCon(EstadoSolicitud::Abierta, puedeCambiar: false, puedeIrA: fn (): bool => true)))->toBe(['completed', 'current', 'pending']);
});

test('pasos: un modal propio reemplaza al «prefijo-valor» del paso accionable, y solo a ese', function () {
    $pasos = pasosDeSolicitudCon(EstadoSolicitud::Borrador, modales: ['abierta' => 'modal-propio', 'adjudicada' => 'no-se-usa']);

    expect($pasos[1]['modal'])->toBe('modal-propio')
        // `cerrada` está bloqueado: no abre ningún modal aunque figure en la lista.
        ->and($pasos[2]['modal'])->toBeNull()
        // Sin entrada en la lista, vale el prefijo de siempre.
        ->and(pasosDeSolicitudCon(EstadoSolicitud::Borrador, modales: [])[1]['modal'])->toBe('m-abierta');
});

test('ayuda: el paso del cierre puede nombrarse aparte del primero accionable', function () {
    $pasos = pasosDeSolicitud(EstadoSolicitud::Borrador);

    // `abierta` es accionable: el cierre lo nombra. `adjudicada` está bloqueado: el párrafo no invita a hacer clic.
    expect(PasosDeEstado::ayuda($pasos, 'solicitudes.estado_ayuda', 'abierta'))->toBe('solicitudes.estado_ayuda.borrador ui.pasos.ayuda_accion')
        ->and(PasosDeEstado::ayuda($pasos, 'solicitudes.estado_ayuda', 'adjudicada'))->toBe('solicitudes.estado_ayuda.borrador');
});

<?php

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Dominio\MaquinaEstados\TransicionesCampania;
use App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado;

/*
 * Pasos que dibuja `molecules/step-arrow`. Puro, sin app ni DB: los textos
 * salen como la clave de idioma (Texto::de() sin framework levantado). Se
 * prueba contra la tabla real de `campania`, que es justo lo que el helper
 * tiene que respetar: lo dibujado nunca contradice `TransicionesCampania`.
 */

/**
 * @return list<array{key: string, label: string, tone: string, status: string, modal: string|null, hint: string|null}>
 */
function pasosDeCampania(EstadoCampania $actual, bool $puedeCambiar = true): array
{
    return PasosDeEstado::armar(
        ruta: [EstadoCampania::Planificada, EstadoCampania::Abierta, EstadoCampania::Cerrada],
        actual: $actual,
        permitida: TransicionesCampania::permitida(...),
        tonos: ['planificada' => 'neutral', 'abierta' => 'success', 'cerrada' => 'alert'],
        claveEtiqueta: 'campania.campania.estado',
        prefijoModal: 'campania-estado-modal',
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
    $pasos = pasosDeCampania(EstadoCampania::Planificada);

    expect(situaciones($pasos))->toBe(['current', 'next', 'blocked'])
        ->and($pasos[1]['modal'])->toBe('campania-estado-modal-abierta')
        ->and($pasos[0]['modal'])->toBeNull()
        ->and($pasos[2]['modal'])->toBeNull();
});

test('pasos: abierta deja planificada completada y cerrada accionable', function () {
    expect(situaciones(pasosDeCampania(EstadoCampania::Abierta)))->toBe(['completed', 'current', 'next']);
});

test('pasos: cerrada es terminal, todo lo anterior queda completado y nada es accionable', function () {
    $pasos = pasosDeCampania(EstadoCampania::Cerrada);

    expect(situaciones($pasos))->toBe(['completed', 'completed', 'current'])
        ->and(array_column($pasos, 'modal'))->each->toBeNull();
});

test('pasos: sin permiso el paso alcanzable queda pendiente, con su pista y sin modal', function () {
    $pasos = pasosDeCampania(EstadoCampania::Planificada, puedeCambiar: false);

    expect(situaciones($pasos))->toBe(['current', 'pending', 'blocked'])
        ->and($pasos[1]['modal'])->toBeNull()
        ->and($pasos[1]['hint'])->toBe('ui.pasos.pista_sin_permiso');
});

test('pasos: el paso bloqueado explica por cuál hay que pasar antes', function () {
    $pasos = pasosDeCampania(EstadoCampania::Planificada);

    expect($pasos[2]['hint'])->toBe('ui.pasos.pista_bloqueado')
        ->and($pasos[0]['hint'])->toBeNull()
        ->and($pasos[1]['hint'])->toBeNull();
});

test('pasos: cada paso trae su clave, su etiqueta y el tono de su estado', function () {
    $pasos = pasosDeCampania(EstadoCampania::Abierta);

    expect(array_column($pasos, 'key'))->toBe(['planificada', 'abierta', 'cerrada'])
        ->and($pasos[1]['label'])->toBe('campania.campania.estado.abierta')
        ->and(array_column($pasos, 'tone'))->toBe(['neutral', 'success', 'alert']);
});

test('pasos: un estado sin tono declarado cae en neutral', function () {
    $pasos = PasosDeEstado::armar(
        ruta: [EstadoCampania::Planificada, EstadoCampania::Abierta],
        actual: EstadoCampania::Planificada,
        permitida: TransicionesCampania::permitida(...),
        tonos: [],
        claveEtiqueta: 'campania.campania.estado',
        prefijoModal: 'm',
        puedeCambiar: true,
    );

    expect(array_column($pasos, 'tone'))->toBe(['neutral', 'neutral']);
});

test('pasos: ningún paso es accionable si la tabla de transiciones no lo permite', function () {
    foreach (EstadoCampania::cases() as $actual) {
        foreach (pasosDeCampania($actual) as $paso) {
            if ($paso['status'] === 'next') {
                expect(TransicionesCampania::permitida($actual, EstadoCampania::from($paso['key'])))->toBeTrue();
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
    $ayuda = PasosDeEstado::ayuda(pasosDeCampania(EstadoCampania::Planificada), 'campania.campania.estado_ayuda');

    expect($ayuda)->toBe('campania.campania.estado_ayuda.planificada ui.pasos.ayuda_accion');
});

test('ayuda: sin permiso avisa que el rol no puede cambiar el estado, en vez de invitar a hacer clic', function () {
    $ayuda = PasosDeEstado::ayuda(pasosDeCampania(EstadoCampania::Abierta, puedeCambiar: false), 'campania.campania.estado_ayuda');

    expect($ayuda)->toBe('campania.campania.estado_ayuda.abierta ui.pasos.ayuda_sin_permiso');
});

test('ayuda: un estado final lleva solo el texto del objeto, sin paso al que ir', function () {
    expect(PasosDeEstado::ayuda(pasosDeCampania(EstadoCampania::Cerrada), 'campania.campania.estado_ayuda'))
        ->toBe('campania.campania.estado_ayuda.cerrada');
});

test('ayuda: sin estado actual entre los pasos no hay párrafo', function () {
    expect(PasosDeEstado::ayuda([], 'campania.campania.estado_ayuda'))->toBeNull();
});

test('ayuda: cada estado de la campaña tiene su etiqueta y su texto de ayuda en lang/es', function () {
    /** @var array{campania: array{estado: array<string, string>, estado_ayuda: array<string, string>}} $lang */
    $lang = require dirname(__DIR__, 2).'/lang/es/campania.php';

    foreach (EstadoCampania::cases() as $estado) {
        expect($lang['campania']['estado'])->toHaveKey($estado->value)
            ->and($lang['campania']['estado_ayuda'])->toHaveKey($estado->value);
    }
});

/*
 * Opciones para una máquina con desvíos y salidas (contrato): todas son
 * opcionales y, sin ellas, el resultado es el de la campaña de arriba. El
 * caso real de cada una se prueba en `PasosDeContratoTest`.
 */
function pasosDeCampaniaCon(EstadoCampania $actual, mixed ...$opciones): array
{
    return PasosDeEstado::armar(...[
        'ruta' => [EstadoCampania::Planificada, EstadoCampania::Abierta, EstadoCampania::Cerrada],
        'actual' => $actual,
        'permitida' => TransicionesCampania::permitida(...),
        'tonos' => [],
        'claveEtiqueta' => 'campania.campania.estado',
        'prefijoModal' => 'm',
        'puedeCambiar' => true,
        ...$opciones,
    ]);
}

test('pasos: los recorridos explícitos reemplazan a la posición para decidir qué está completado', function () {
    // Por posición, cerrada deja planificada y abierta completadas; diciendo que
    // no se recorrió ninguna, quedan bloqueadas.
    expect(situaciones(pasosDeCampaniaCon(EstadoCampania::Cerrada)))->toBe(['completed', 'completed', 'current'])
        ->and(situaciones(pasosDeCampaniaCon(EstadoCampania::Cerrada, recorridos: [])))->toBe(['blocked', 'blocked', 'current'])
        ->and(situaciones(pasosDeCampaniaCon(EstadoCampania::Cerrada, recorridos: [EstadoCampania::Planificada])))->toBe(['completed', 'blocked', 'current']);
});

test('pasos: una pista propia reemplaza al «pasa antes por…» del paso bloqueado, y solo a ese', function () {
    $pasos = pasosDeCampaniaCon(EstadoCampania::Planificada, pistas: ['cerrada' => 'Motivo propio', 'abierta' => 'No se usa']);

    expect($pasos[2]['hint'])->toBe('Motivo propio')
        // `abierta` no está bloqueado: la pista no le corresponde.
        ->and($pasos[1]['hint'])->toBeNull();
});

test('pasos: el ícono de un paso se toma de la lista y por defecto no hay', function () {
    $pasos = pasosDeCampaniaCon(EstadoCampania::Abierta, iconos: ['cerrada' => 'cancel']);

    expect(array_column($pasos, 'icon'))->toBe([null, null, 'cancel']);
});

test('pasos: el «pasa antes por…» nombra el primer paso al que sí se puede ir', function () {
    // Desde planificada solo se puede ir a abierta: es el que hay que dar antes de llegar a cerrada.
    expect(pasosDeCampania(EstadoCampania::Planificada)[2]['hint'])->toBe('ui.pasos.pista_bloqueado');
});

test('pasos: el permiso por destino se suma al general, y sin él el paso queda pendiente', function () {
    // Puede cambiar de estado en general, pero no ir a «cerrada»: solo ese paso queda pendiente.
    $sinCerrar = fn (EstadoCampania $hacia): bool => $hacia !== EstadoCampania::Cerrada;

    expect(situaciones(pasosDeCampaniaCon(EstadoCampania::Abierta, puedeIrA: $sinCerrar)))->toBe(['completed', 'current', 'pending'])
        ->and(pasosDeCampaniaCon(EstadoCampania::Abierta, puedeIrA: $sinCerrar)[2]['hint'])->toBe('ui.pasos.pista_sin_permiso')
        ->and(pasosDeCampaniaCon(EstadoCampania::Abierta, puedeIrA: $sinCerrar)[2]['modal'])->toBeNull();

    // Sin el permiso general, ningún permiso por destino lo devuelve.
    expect(situaciones(pasosDeCampaniaCon(EstadoCampania::Abierta, puedeCambiar: false, puedeIrA: fn (): bool => true)))->toBe(['completed', 'current', 'pending']);
});

test('pasos: un modal propio reemplaza al «prefijo-valor» del paso accionable, y solo a ese', function () {
    $pasos = pasosDeCampaniaCon(EstadoCampania::Planificada, modales: ['abierta' => 'modal-propio', 'cerrada' => 'no-se-usa']);

    expect($pasos[1]['modal'])->toBe('modal-propio')
        // `cerrada` está bloqueado: no abre ningún modal aunque figure en la lista.
        ->and($pasos[2]['modal'])->toBeNull()
        // Sin entrada en la lista, vale el prefijo de siempre.
        ->and(pasosDeCampaniaCon(EstadoCampania::Planificada, modales: [])[1]['modal'])->toBe('m-abierta');
});

test('ayuda: el paso del cierre puede nombrarse aparte del primero accionable', function () {
    $pasos = pasosDeCampania(EstadoCampania::Planificada);

    // `abierta` es accionable: el cierre lo nombra. `cerrada` está bloqueado: el párrafo no invita a hacer clic.
    expect(PasosDeEstado::ayuda($pasos, 'campania.campania.estado_ayuda', 'abierta'))->toBe('campania.campania.estado_ayuda.planificada ui.pasos.ayuda_accion')
        ->and(PasosDeEstado::ayuda($pasos, 'campania.campania.estado_ayuda', 'cerrada'))->toBe('campania.campania.estado_ayuda.planificada');
});

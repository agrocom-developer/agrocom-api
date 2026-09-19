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

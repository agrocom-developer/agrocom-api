<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\MaquinaEstados\TransicionesContrato;
use App\Dominios\Comercial\Infraestructura\Http\PasosDeContrato;

/*
 * Pasos que dibuja `molecules/step-arrow` en la ficha de edición de un
 * contrato. Puro, sin app ni DB: los textos salen como la clave de idioma
 * (Texto::de() sin framework levantado). Se prueba contra la tabla real
 * `TransicionesContrato`, que es lo que lo dibujado nunca puede contradecir.
 *
 * La ruta son cinco pasos —En aprobación, En ejecución, Pausado, Ejecutado,
 * Cancelado—; en un contrato en conflicto el primero pasa a ser «En conflicto».
 */

/**
 * @return array<string, string> clave del paso → situación
 */
function situacionesDeContrato(EstadoContrato $actual, bool $puedeCambiar = true): array
{
    return array_column(PasosDeContrato::armar($actual, $puedeCambiar), 'status', 'key');
}

/**
 * @return array<string, array{key: string, hint: string|null, modal: string|null, icon: string|null, tone: string}>
 */
function pasosDeContratoPorClave(EstadoContrato $actual, bool $puedeCambiar = true): array
{
    $porClave = [];

    foreach (PasosDeContrato::armar($actual, $puedeCambiar) as $paso) {
        $porClave[$paso['key']] = $paso;
    }

    return $porClave;
}

test('contrato: en aprobación se puede aprobar o cancelar, y lo demás está bloqueado', function () {
    expect(situacionesDeContrato(EstadoContrato::Borrador))->toBe([
        'borrador' => 'current',
        'vigente' => 'next',
        'pausado' => 'blocked',
        'finalizado' => 'blocked',
        'cancelado' => 'next',
    ]);
});

test('contrato: en conflicto ocupa el primer paso, no se puede aprobar y sí cancelar', function () {
    $pasos = pasosDeContratoPorClave(EstadoContrato::Conflicto);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'conflicto' => 'current',
        'vigente' => 'blocked',
        'pausado' => 'blocked',
        'finalizado' => 'blocked',
        'cancelado' => 'next',
    ])
        // El bloqueo de aprobar no es de orden sino de negocio: lleva su propia pista.
        ->and($pasos['vigente']['hint'])->toBe('comercial.contrato.pasos.pista_conflicto');
});

test('contrato: en ejecución se puede pausar, finalizar o cancelar, y en aprobación quedó completado', function () {
    expect(situacionesDeContrato(EstadoContrato::Vigente))->toBe([
        'borrador' => 'completed',
        'vigente' => 'current',
        'pausado' => 'next',
        'finalizado' => 'next',
        'cancelado' => 'next',
    ]);
});

test('contrato: pausado ofrece volver a ejecución aunque quede antes en la fila', function () {
    $pasos = pasosDeContratoPorClave(EstadoContrato::Pausado);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'borrador' => 'completed',
        'vigente' => 'next',
        'pausado' => 'current',
        'finalizado' => 'blocked',
        'cancelado' => 'blocked',
    ])
        ->and($pasos['vigente']['modal'])->toBe('contrato-estado-modal-vigente')
        // Lo bloqueado dice por cuál paso hay que pasar antes: reanudar.
        ->and($pasos['finalizado']['hint'])->toBe('ui.pasos.pista_bloqueado')
        ->and($pasos['cancelado']['hint'])->toBe('ui.pasos.pista_bloqueado');
});

test('contrato: ejecutado es final, recorrió aprobación y ejecución, y no ofrece nada', function () {
    $pasos = pasosDeContratoPorClave(EstadoContrato::Finalizado);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'borrador' => 'completed',
        'vigente' => 'completed',
        'pausado' => 'blocked',
        'finalizado' => 'current',
        'cancelado' => 'blocked',
    ])
        // Sin pasos a los que ir tampoco hay «pasa antes por…» que decir.
        ->and(array_column($pasos, 'hint'))->each->toBeNull()
        ->and(array_column($pasos, 'modal'))->each->toBeNull();
});

test('contrato: cancelado es final y no da por completado nada: no se sabe desde dónde se canceló', function () {
    expect(situacionesDeContrato(EstadoContrato::Cancelado))->toBe([
        'borrador' => 'blocked',
        'vigente' => 'blocked',
        'pausado' => 'blocked',
        'finalizado' => 'blocked',
        'cancelado' => 'current',
    ]);
});

test('contrato: sin permiso el paso alcanzable queda pendiente, con su pista y sin modal', function () {
    $pasos = pasosDeContratoPorClave(EstadoContrato::Vigente, puedeCambiar: false);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'borrador' => 'completed',
        'vigente' => 'current',
        'pausado' => 'pending',
        'finalizado' => 'pending',
        'cancelado' => 'pending',
    ])
        ->and($pasos['finalizado']['hint'])->toBe('ui.pasos.pista_sin_permiso')
        ->and(array_column($pasos, 'modal'))->each->toBeNull();
});

test('contrato: cada paso accionable respeta la tabla y nunca ofrece un estado del sistema', function () {
    foreach (EstadoContrato::cases() as $actual) {
        foreach (PasosDeContrato::armar($actual, true) as $paso) {
            if ($paso['status'] !== 'next') {
                continue;
            }

            $destino = EstadoContrato::from($paso['key']);

            expect(TransicionesContrato::permitida($actual, $destino))->toBeTrue()
                // `borrador` y `conflicto` los fija solo el sistema: ningún paso los pide.
                ->and($destino)->not->toBe(EstadoContrato::Borrador)
                ->and($destino)->not->toBe(EstadoContrato::Conflicto)
                ->and($paso['modal'])->toBe('contrato-estado-modal-'.$paso['key']);
        }
    }
});

test('contrato: el estado actual siempre aparece entre los pasos, exactamente una vez', function () {
    foreach (EstadoContrato::cases() as $actual) {
        $actuales = array_filter(PasosDeContrato::armar($actual, true), fn (array $paso): bool => $paso['status'] === 'current');

        expect($actuales)->toHaveCount(1)
            ->and(array_values($actuales)[0]['key'])->toBe($actual->value);
    }
});

test('contrato: los tonos son los del listado y cubren todos los estados', function () {
    // Los estados del enum y el mapa de tonos no pueden desincronizarse: un
    // estado nuevo sin tono cae en gris en el paso pero rompe el badge del listado.
    expect(array_keys(PasosDeContrato::TONO_POR_ESTADO))
        ->toEqualCanonicalizing(array_map(fn (EstadoContrato $estado): string => $estado->value, EstadoContrato::cases()));

    $pasos = pasosDeContratoPorClave(EstadoContrato::Vigente);

    expect($pasos['borrador']['tone'])->toBe('neutral')
        ->and($pasos['vigente']['tone'])->toBe('success')
        ->and($pasos['pausado']['tone'])->toBe('info')
        ->and($pasos['finalizado']['tone'])->toBe('distintivo-2')
        ->and($pasos['cancelado']['tone'])->toBe('danger')
        ->and(pasosDeContratoPorClave(EstadoContrato::Conflicto)['conflicto']['tone'])->toBe('alert');
});

test('contrato: «Cancelado» se dibuja procesado con su propio ícono, no con el check de un éxito', function () {
    $pasos = pasosDeContratoPorClave(EstadoContrato::Cancelado);

    expect($pasos['cancelado']['icon'])->toBe('cancel')
        ->and($pasos['finalizado']['icon'])->toBeNull();
});

/*
 * Párrafo de ayuda. El paso que nombra el cierre no es el primero accionable:
 * desde «En ejecución» ese sería «Pausado», y el avance natural es «Ejecutado».
 */
test('ayuda de contrato: en aprobación invita a aprobar', function () {
    $pasos = PasosDeContrato::armar(EstadoContrato::Borrador, true);

    expect(PasosDeContrato::ayuda($pasos, EstadoContrato::Borrador))
        ->toBe('comercial.contrato.estado_ayuda.borrador ui.pasos.ayuda_accion');
});

test('ayuda de contrato: en ejecución apunta a ejecutado, no a pausado', function () {
    $pasos = PasosDeContrato::armar(EstadoContrato::Vigente, true);

    expect(PasosDeContrato::ayuda($pasos, EstadoContrato::Vigente))
        ->toBe('comercial.contrato.estado_ayuda.vigente ui.pasos.ayuda_accion');
});

test('ayuda de contrato: pausado invita a reanudar', function () {
    $pasos = PasosDeContrato::armar(EstadoContrato::Pausado, true);

    expect(PasosDeContrato::ayuda($pasos, EstadoContrato::Pausado))
        ->toBe('comercial.contrato.estado_ayuda.pausado ui.pasos.ayuda_accion');
});

test('ayuda de contrato: en conflicto explica qué decidir y no invita a aprobar', function () {
    $pasos = PasosDeContrato::armar(EstadoContrato::Conflicto, true);

    expect(PasosDeContrato::ayuda($pasos, EstadoContrato::Conflicto))
        ->toBe('comercial.contrato.estado_ayuda.conflicto');
});

test('ayuda de contrato: un estado final lleva solo su texto', function () {
    foreach ([EstadoContrato::Finalizado, EstadoContrato::Cancelado] as $final) {
        expect(PasosDeContrato::ayuda(PasosDeContrato::armar($final, true), $final))
            ->toBe("comercial.contrato.estado_ayuda.{$final->value}");
    }
});

test('ayuda de contrato: sin permiso avisa que el rol no puede cambiar el estado', function () {
    $pasos = PasosDeContrato::armar(EstadoContrato::Vigente, false);

    expect(PasosDeContrato::ayuda($pasos, EstadoContrato::Vigente))
        ->toBe('comercial.contrato.estado_ayuda.vigente ui.pasos.ayuda_sin_permiso');
});

test('ayuda de contrato: cada estado tiene su etiqueta y su texto de ayuda en lang/es', function () {
    /** @var array{contrato: array{estado: array<string, string>, estado_ayuda: array<string, string>, pasos: array<string, string>}} $lang */
    $lang = require dirname(__DIR__, 2).'/lang/es/comercial.php';

    foreach (EstadoContrato::cases() as $estado) {
        expect($lang['contrato']['estado'])->toHaveKey($estado->value)
            ->and($lang['contrato']['estado_ayuda'])->toHaveKey($estado->value);
    }

    expect($lang['contrato']['pasos'])->toHaveKey('pista_conflicto');
});

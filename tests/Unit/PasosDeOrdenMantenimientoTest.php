<?php

use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\MaquinaEstados\TransicionesOrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Http\PasosDeOrdenMantenimiento;

/*
 * Pasos que dibuja `molecules/step-arrow` en la ficha de una orden de
 * mantenimiento (tarea 116). Puro, sin app ni DB: los textos salen como la
 * clave de idioma (`Texto::de()` sin framework levantado). Se prueba contra la
 * tabla real `TransicionesOrdenMantenimiento`, que es lo que lo dibujado nunca
 * puede contradecir.
 *
 * Lo propio de la orden: una sola transición («Abierta → Cerrada»), un solo
 * permiso que la gobierna, y ninguna reapertura.
 */

/**
 * @return array<string, array{key: string, label: string, tone: string, status: string, modal: string|null, hint: string|null, icon: string|null}>
 */
function pasosDeOrdenMantenimientoPorClave(EstadoOrdenMantenimiento $actual, bool $puedeCerrar): array
{
    $porClave = [];

    foreach (PasosDeOrdenMantenimiento::armar($actual, $puedeCerrar) as $paso) {
        $porClave[$paso['key']] = $paso;
    }

    return $porClave;
}

test('orden abierta con permiso: «Cerrada» es el paso accionable y abre su modal', function () {
    $pasos = pasosDeOrdenMantenimientoPorClave(EstadoOrdenMantenimiento::Abierta, true);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'abierta' => 'current',
        'cerrada' => 'next',
    ])
        ->and($pasos['cerrada']['modal'])->toBe(PasosDeOrdenMantenimiento::modal(EstadoOrdenMantenimiento::Cerrada))
        ->and($pasos['abierta']['modal'])->toBeNull();
});

test('orden abierta sin permiso: «Cerrada» queda pendiente, sin modal, y la pista culpa al rol', function () {
    $pasos = pasosDeOrdenMantenimientoPorClave(EstadoOrdenMantenimiento::Abierta, false);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'abierta' => 'current',
        'cerrada' => 'pending',
    ])
        ->and($pasos['cerrada']['modal'])->toBeNull()
        ->and($pasos['cerrada']['hint'])->toBe('ui.pasos.pista_sin_permiso');
});

test('orden cerrada: «Abierta» queda recorrida y no hay ningún paso al que ir', function (bool $puedeCerrar) {
    $pasos = pasosDeOrdenMantenimientoPorClave(EstadoOrdenMantenimiento::Cerrada, $puedeCerrar);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'abierta' => 'completed',
        'cerrada' => 'current',
    ])
        ->and(array_column($pasos, 'status'))->not->toContain('next');
})->with([true, false]);

test('el permiso no inventa pasos: sin él ninguno es accionable', function (EstadoOrdenMantenimiento $estado) {
    expect(array_column(PasosDeOrdenMantenimiento::armar($estado, false), 'status'))->not->toContain('next');
})->with(EstadoOrdenMantenimiento::cases());

test('los pasos no contradicen la tabla de transiciones', function (EstadoOrdenMantenimiento $estado) {
    $pasos = PasosDeOrdenMantenimiento::armar($estado, true);

    expect(array_column($pasos, 'key'))->toBe(['abierta', 'cerrada']);

    foreach ($pasos as $paso) {
        $hacia = EstadoOrdenMantenimiento::from($paso['key']);

        // Un paso accionable tiene que estar en la tabla; uno al que la
        // máquina no deja ir queda `blocked`. El actual y lo ya recorrido no
        // dependen de la tabla.
        if ($paso['status'] === 'next') {
            expect(TransicionesOrdenMantenimiento::permitida($estado, $hacia))->toBeTrue();
        }

        if ($paso['status'] === 'blocked') {
            expect(TransicionesOrdenMantenimiento::permitida($estado, $hacia))->toBeFalse();
        }
    }
})->with(EstadoOrdenMantenimiento::cases());

test('cada estado de la ruta tiene tono propio y etiqueta traducible', function (EstadoOrdenMantenimiento $estado) {
    foreach (PasosDeOrdenMantenimiento::armar($estado, true) as $paso) {
        expect(PasosDeOrdenMantenimiento::TONO_POR_ESTADO)->toHaveKey($paso['key'])
            ->and($paso['tone'])->toBe(PasosDeOrdenMantenimiento::TONO_POR_ESTADO[$paso['key']])
            ->and($paso['label'])->toBe("mantenimiento.orden.estado.{$paso['key']}");
    }
})->with(EstadoOrdenMantenimiento::cases());

test('la ayuda parte del texto del estado actual y cierra según el permiso', function () {
    $conPermiso = PasosDeOrdenMantenimiento::ayuda(PasosDeOrdenMantenimiento::armar(EstadoOrdenMantenimiento::Abierta, true));
    $sinPermiso = PasosDeOrdenMantenimiento::ayuda(PasosDeOrdenMantenimiento::armar(EstadoOrdenMantenimiento::Abierta, false));
    $cerrada = PasosDeOrdenMantenimiento::ayuda(PasosDeOrdenMantenimiento::armar(EstadoOrdenMantenimiento::Cerrada, true));

    expect($conPermiso)->toStartWith('mantenimiento.orden.estado_ayuda.abierta')
        ->toContain('ui.pasos.ayuda_accion')
        ->and($sinPermiso)->toStartWith('mantenimiento.orden.estado_ayuda.abierta')
        ->toContain('ui.pasos.ayuda_sin_permiso')
        // Estado final: no hay paso al que invitar, así que el párrafo queda
        // solo con el texto del estado.
        ->and($cerrada)->toBe('mantenimiento.orden.estado_ayuda.cerrada');
});

test('el id del modal de un paso es el que arma el presentador', function () {
    expect(PasosDeOrdenMantenimiento::modal(EstadoOrdenMantenimiento::Cerrada))
        ->toBe(PasosDeOrdenMantenimiento::PREFIJO_MODAL.'-cerrada');
});

test('cada estado de la orden tiene su etiqueta y su texto de ayuda en el idioma', function () {
    /** @var array{orden: array{estado: array<string, string>, estado_ayuda: array<string, string>}} $lang */
    $lang = require dirname(__DIR__, 2).'/lang/es/mantenimiento.php';

    foreach (EstadoOrdenMantenimiento::cases() as $estado) {
        expect($lang['orden']['estado'])->toHaveKey($estado->value)
            ->and($lang['orden']['estado_ayuda'])->toHaveKey($estado->value);
    }
});

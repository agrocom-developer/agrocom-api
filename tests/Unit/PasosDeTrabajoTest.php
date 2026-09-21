<?php

use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesTrabajo;
use App\Dominios\Operaciones\Infraestructura\Http\PasosDeTrabajo;

/*
 * Pasos que dibuja `molecules/step-arrow` en la ficha de edición de un trabajo
 * (tarea 114). Puro, sin app ni DB: los textos salen como la clave de idioma
 * (`Texto::de()` sin framework levantado). Se prueba contra la tabla real
 * `TransicionesTrabajo`, que es lo que lo dibujado nunca puede contradecir.
 *
 * Lo propio del trabajo: el panel no dispara ninguna transición —la de
 * «Abierto → Cerrado» la registra el piloto desde la app de campo—, así que
 * ningún paso llega a `next` y ninguno abre un modal.
 */

/**
 * @return array<string, array{key: string, label: string, tone: string, status: string, modal: string|null, hint: string|null, icon: string|null}>
 */
function pasosDeTrabajoPorClave(EstadoTrabajo $actual): array
{
    $porClave = [];

    foreach (PasosDeTrabajo::armar($actual) as $paso) {
        $porClave[$paso['key']] = $paso;
    }

    return $porClave;
}

test('trabajo abierto: es el paso actual y «Cerrado» queda pendiente, sin modal', function () {
    $pasos = pasosDeTrabajoPorClave(EstadoTrabajo::Abierto);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'abierto' => 'current',
        'cerrado' => 'pending',
    ])
        ->and($pasos['cerrado']['modal'])->toBeNull()
        ->and($pasos['abierto']['modal'])->toBeNull();
});

test('trabajo abierto: la pista del cierre no culpa al rol, dice que se registra en campo', function () {
    $pasos = pasosDeTrabajoPorClave(EstadoTrabajo::Abierto);

    expect($pasos['cerrado']['hint'])
        ->toBe('operaciones.trabajos.estado_pista_cierre_en_campo')
        ->not->toBe('ui.pasos.pista_sin_permiso');
});

test('trabajo cerrado: «Abierto» queda recorrido y no hay ningún paso al que ir', function () {
    $pasos = pasosDeTrabajoPorClave(EstadoTrabajo::Cerrado);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'abierto' => 'completed',
        'cerrado' => 'current',
    ]);
});

test('ningún estado ofrece un paso accionable: el panel no cambia el estado de un trabajo', function (EstadoTrabajo $estado) {
    expect(array_column(PasosDeTrabajo::armar($estado), 'status'))->not->toContain('next');
})->with([EstadoTrabajo::Abierto, EstadoTrabajo::Cerrado]);

test('los pasos no contradicen la tabla de transiciones', function (EstadoTrabajo $estado) {
    $pasos = PasosDeTrabajo::armar($estado);

    expect(array_column($pasos, 'key'))->toBe(['abierto', 'cerrado']);

    foreach ($pasos as $paso) {
        $hacia = EstadoTrabajo::from($paso['key']);

        // Un paso sin acción pero alcanzable es `pending`; uno al que la
        // máquina no deja ir queda `blocked`. Los demás son el actual o lo ya
        // recorrido, que no dependen de la tabla.
        if ($paso['status'] === 'pending') {
            expect(TransicionesTrabajo::permitida($estado, $hacia))->toBeTrue();
        }

        if ($paso['status'] === 'blocked') {
            expect(TransicionesTrabajo::permitida($estado, $hacia))->toBeFalse();
        }
    }
})->with([EstadoTrabajo::Abierto, EstadoTrabajo::Cerrado]);

test('cada estado de la ruta tiene tono propio y etiqueta traducible', function (EstadoTrabajo $estado) {
    foreach (PasosDeTrabajo::armar($estado) as $paso) {
        expect(PasosDeTrabajo::TONO_POR_ESTADO)->toHaveKey($paso['key'])
            ->and($paso['tone'])->toBe(PasosDeTrabajo::TONO_POR_ESTADO[$paso['key']])
            ->and($paso['label'])->toBe("operaciones.trabajos.estado.{$paso['key']}");
    }
})->with([EstadoTrabajo::Abierto, EstadoTrabajo::Cerrado]);

test('la ayuda nombra el estado actual y no cierra culpando al rol', function (EstadoTrabajo $estado) {
    $ayuda = PasosDeTrabajo::ayuda(PasosDeTrabajo::armar($estado));

    expect($ayuda)
        ->toBe("operaciones.trabajos.estado_ayuda.{$estado->value}")
        ->not->toContain('ui.pasos.ayuda_sin_permiso');
})->with([EstadoTrabajo::Abierto, EstadoTrabajo::Cerrado]);

test('cada estado del trabajo tiene su etiqueta y su texto de ayuda en el idioma', function () {
    /** @var array{trabajos: array{estado: array<string, string>, estado_ayuda: array<string, string>}} $lang */
    $lang = require dirname(__DIR__, 2).'/lang/es/operaciones.php';

    foreach (EstadoTrabajo::cases() as $estado) {
        expect($lang['trabajos']['estado'])->toHaveKey($estado->value)
            ->and($lang['trabajos']['estado_ayuda'])->toHaveKey($estado->value);
    }
});

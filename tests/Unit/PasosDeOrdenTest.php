<?php

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesOrden;
use App\Dominios\Operaciones\Infraestructura\Http\PasosDeOrden;

/*
 * Pasos que dibuja `molecules/step-arrow` en la ficha de edición y en el
 * detalle de una orden de aplicación. Puro, sin app ni DB: los textos salen
 * como la clave de idioma (Texto::de() sin framework levantado). Se prueba
 * contra la tabla real `TransicionesOrden`, que es lo que lo dibujado nunca
 * puede contradecir.
 *
 * La ruta son cinco pasos —Emitida, Vigente, Pausada, Consumida, Cancelada—;
 * «Vencida» solo aparece, al final, en una orden que ya está vencida.
 */

const SUFIJO_MODAL_ORDEN = 'detalle-7';

/**
 * @return array{activar: bool, pausar: bool, cerrar: bool, cancelar: bool}
 */
function permisosDeOrden(bool $activar = true, bool $pausar = true, bool $cerrar = true, bool $cancelar = true): array
{
    return ['activar' => $activar, 'pausar' => $pausar, 'cerrar' => $cerrar, 'cancelar' => $cancelar];
}

/**
 * @param  array{activar: bool, pausar: bool, cerrar: bool, cancelar: bool}|null  $puede
 * @return array<string, string> clave del paso → situación
 */
function situacionesDeOrden(EstadoOrdenAplicacion $actual, ?array $puede = null): array
{
    return array_column(PasosDeOrden::armar($actual, $puede ?? permisosDeOrden(), SUFIJO_MODAL_ORDEN), 'status', 'key');
}

/**
 * @param  array{activar: bool, pausar: bool, cerrar: bool, cancelar: bool}|null  $puede
 * @return array<string, array{key: string, hint: string|null, modal: string|null, icon: string|null, tone: string, status: string}>
 */
function pasosDeOrdenPorClave(EstadoOrdenAplicacion $actual, ?array $puede = null): array
{
    $porClave = [];

    foreach (PasosDeOrden::armar($actual, $puede ?? permisosDeOrden(), SUFIJO_MODAL_ORDEN) as $paso) {
        $porClave[$paso['key']] = $paso;
    }

    return $porClave;
}

test('orden: emitida se puede activar y lo demás está bloqueado; cancelar dice que se elimina', function () {
    $pasos = pasosDeOrdenPorClave(EstadoOrdenAplicacion::Emitida);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'emitida' => 'current',
        'vigente' => 'next',
        'pausada' => 'blocked',
        'consumida' => 'blocked',
        'cancelada' => 'blocked',
    ])
        // Una emitida no se cancela: se elimina. La pista lo dice en vez del «pasa antes por…».
        ->and($pasos['cancelada']['hint'])->toBe('operaciones.ordenes.pasos.pista_cancelar_emitida')
        ->and($pasos['pausada']['hint'])->toBe('ui.pasos.pista_bloqueado')
        ->and($pasos['vigente']['modal'])->toBe('orden-activar-modal-detalle-7');
});

test('orden: vigente se puede pausar, cerrar o cancelar, y emitida quedó completada', function () {
    $pasos = pasosDeOrdenPorClave(EstadoOrdenAplicacion::Vigente);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'emitida' => 'completed',
        'vigente' => 'current',
        'pausada' => 'next',
        'consumida' => 'next',
        'cancelada' => 'next',
    ])
        ->and($pasos['pausada']['modal'])->toBe('orden-pausar-modal-detalle-7')
        ->and($pasos['consumida']['modal'])->toBe('orden-cerrar-modal-detalle-7')
        ->and($pasos['cancelada']['modal'])->toBe('orden-cancelar-modal-detalle-7');
});

test('orden: pausada ofrece reanudar aunque quede antes en la fila, y no se puede cerrar', function () {
    $pasos = pasosDeOrdenPorClave(EstadoOrdenAplicacion::Pausada);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'emitida' => 'completed',
        'vigente' => 'next',
        'pausada' => 'current',
        'consumida' => 'blocked',
        'cancelada' => 'next',
    ])
        // «Vigente» se alcanza reanudando, no activando: es otro modal.
        ->and($pasos['vigente']['modal'])->toBe('orden-reanudar-modal-detalle-7')
        // Lo bloqueado dice por cuál paso hay que pasar antes: reanudar.
        ->and($pasos['consumida']['hint'])->toBe('ui.pasos.pista_bloqueado');
});

test('orden: consumida es final, recorrió emitida y vigente, y no ofrece nada', function () {
    $pasos = pasosDeOrdenPorClave(EstadoOrdenAplicacion::Consumida);

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'emitida' => 'completed',
        'vigente' => 'completed',
        'pausada' => 'blocked',
        'consumida' => 'current',
        'cancelada' => 'blocked',
    ])
        // Sin pasos a los que ir tampoco hay «pasa antes por…» que decir.
        ->and(array_column($pasos, 'hint'))->each->toBeNull()
        ->and(array_column($pasos, 'modal'))->each->toBeNull();
});

test('orden: cancelada es final y, como solo se cancela una vigente o pausada, activarla ya ocurrió', function () {
    expect(situacionesDeOrden(EstadoOrdenAplicacion::Cancelada))->toBe([
        'emitida' => 'completed',
        'vigente' => 'completed',
        'pausada' => 'blocked',
        'consumida' => 'blocked',
        'cancelada' => 'current',
    ]);
});

test('orden: una vencida aparece como sexto paso, es final y no ofrece nada', function () {
    $pasos = pasosDeOrdenPorClave(EstadoOrdenAplicacion::Vencida);

    expect(array_keys($pasos))->toBe(['emitida', 'vigente', 'pausada', 'consumida', 'cancelada', 'vencida'])
        ->and($pasos['vencida']['status'])->toBe('current')
        ->and($pasos['vencida']['icon'])->toBe('event_busy')
        ->and(array_column($pasos, 'modal'))->each->toBeNull();

    // Fuera de ese caso la ruta no la dibuja: no es un destino de nadie.
    expect(array_keys(pasosDeOrdenPorClave(EstadoOrdenAplicacion::Vigente)))->not->toContain('vencida');
});

test('orden: cada acción exige SU permiso, y reanudar usa el de pausar', function () {
    // Puede pausar, pero no cerrar ni cancelar: solo el primero queda accionable.
    $pasos = pasosDeOrdenPorClave(EstadoOrdenAplicacion::Vigente, permisosDeOrden(pausar: true, cerrar: false, cancelar: false));

    expect(array_column($pasos, 'status', 'key'))->toBe([
        'emitida' => 'completed',
        'vigente' => 'current',
        'pausada' => 'next',
        'consumida' => 'pending',
        'cancelada' => 'pending',
    ])
        ->and($pasos['consumida']['hint'])->toBe('ui.pasos.pista_sin_permiso')
        ->and($pasos['consumida']['modal'])->toBeNull()
        ->and($pasos['cancelada']['modal'])->toBeNull();

    // Reanudar es «pausar» para el rol: sin ese permiso, «Vigente» queda pendiente.
    expect(situacionesDeOrden(EstadoOrdenAplicacion::Pausada, permisosDeOrden(pausar: false))['vigente'])->toBe('pending')
        ->and(situacionesDeOrden(EstadoOrdenAplicacion::Pausada, permisosDeOrden(pausar: true, cancelar: false))['cancelada'])->toBe('pending');

    // Activar es su propio permiso, aunque el rol pueda todo lo demás.
    expect(situacionesDeOrden(EstadoOrdenAplicacion::Emitida, permisosDeOrden(activar: false))['vigente'])->toBe('pending');
});

test('orden: cada paso accionable respeta la tabla y su modal es el de su acción', function () {
    $modalPorAccion = [
        'emitida→vigente' => 'orden-activar-modal-detalle-7',
        'pausada→vigente' => 'orden-reanudar-modal-detalle-7',
        'vigente→pausada' => 'orden-pausar-modal-detalle-7',
        'vigente→consumida' => 'orden-cerrar-modal-detalle-7',
        'vigente→cancelada' => 'orden-cancelar-modal-detalle-7',
        'pausada→cancelada' => 'orden-cancelar-modal-detalle-7',
    ];

    foreach (EstadoOrdenAplicacion::cases() as $actual) {
        foreach (PasosDeOrden::armar($actual, permisosDeOrden(), SUFIJO_MODAL_ORDEN) as $paso) {
            if ($paso['status'] !== 'next') {
                continue;
            }

            $destino = EstadoOrdenAplicacion::from($paso['key']);

            expect(TransicionesOrden::permitida($actual, $destino))->toBeTrue()
                // `emitida` la fija solo el sistema al crear: ningún paso la pide.
                ->and($destino)->not->toBe(EstadoOrdenAplicacion::Emitida)
                ->and($paso['modal'])->toBe($modalPorAccion["{$actual->value}→{$destino->value}"]);
        }
    }
});

test('orden: los modales que piden los pasos existen en _orden-modales', function () {
    $modales = file_get_contents(dirname(__DIR__, 2).'/app/Dominios/Operaciones/Infraestructura/Http/Views/pages/ordenes/_orden-modales.blade.php');

    foreach (['activar', 'pausar', 'reanudar', 'cerrar', 'cancelar'] as $accion) {
        expect($modales)->toContain("'orden-{$accion}-modal-'");
    }
});

test('orden: el estado actual siempre aparece entre los pasos, exactamente una vez', function () {
    foreach (EstadoOrdenAplicacion::cases() as $actual) {
        $actuales = array_filter(PasosDeOrden::armar($actual, permisosDeOrden(), SUFIJO_MODAL_ORDEN), fn (array $paso): bool => $paso['status'] === 'current');

        expect($actuales)->toHaveCount(1)
            ->and(array_values($actuales)[0]['key'])->toBe($actual->value);
    }
});

test('orden: los tonos son los del listado y cubren todos los estados', function () {
    // Los estados del enum y el mapa de tonos no pueden desincronizarse: un
    // estado nuevo sin tono cae en gris en el paso pero rompe el badge.
    expect(array_keys(PasosDeOrden::TONO_POR_ESTADO))
        ->toEqualCanonicalizing(array_map(fn (EstadoOrdenAplicacion $estado): string => $estado->value, EstadoOrdenAplicacion::cases()));

    $pasos = pasosDeOrdenPorClave(EstadoOrdenAplicacion::Vigente);

    expect($pasos['emitida']['tone'])->toBe('neutral')
        ->and($pasos['vigente']['tone'])->toBe('success')
        ->and($pasos['pausada']['tone'])->toBe('warning')
        ->and($pasos['consumida']['tone'])->toBe('info')
        ->and($pasos['cancelada']['tone'])->toBe('danger');
});

test('orden: «Cancelada» se dibuja procesada con su propio ícono, no con el check de un éxito', function () {
    $pasos = pasosDeOrdenPorClave(EstadoOrdenAplicacion::Cancelada);

    expect($pasos['cancelada']['icon'])->toBe('cancel')
        ->and($pasos['consumida']['icon'])->toBeNull();
});

/*
 * Párrafo de ayuda. El paso que nombra el cierre no es el primero accionable:
 * desde «Vigente» ese sería «Pausada», y el avance natural es «Consumida».
 */
test('ayuda de orden: emitida invita a activar', function () {
    $pasos = PasosDeOrden::armar(EstadoOrdenAplicacion::Emitida, permisosDeOrden(), SUFIJO_MODAL_ORDEN);

    expect(PasosDeOrden::ayuda($pasos, EstadoOrdenAplicacion::Emitida))
        ->toBe('operaciones.ordenes.estado_ayuda.emitida ui.pasos.ayuda_accion');
});

test('ayuda de orden: vigente apunta a consumida, no a pausada', function () {
    $pasos = PasosDeOrden::armar(EstadoOrdenAplicacion::Vigente, permisosDeOrden(), SUFIJO_MODAL_ORDEN);

    expect(PasosDeOrden::ayuda($pasos, EstadoOrdenAplicacion::Vigente))
        ->toBe('operaciones.ordenes.estado_ayuda.vigente ui.pasos.ayuda_accion');
});

test('ayuda de orden: pausada invita a reanudar', function () {
    $pasos = PasosDeOrden::armar(EstadoOrdenAplicacion::Pausada, permisosDeOrden(), SUFIJO_MODAL_ORDEN);

    expect(PasosDeOrden::ayuda($pasos, EstadoOrdenAplicacion::Pausada))
        ->toBe('operaciones.ordenes.estado_ayuda.pausada ui.pasos.ayuda_accion');
});

test('ayuda de orden: un estado final lleva solo su texto', function () {
    foreach ([EstadoOrdenAplicacion::Consumida, EstadoOrdenAplicacion::Cancelada, EstadoOrdenAplicacion::Vencida] as $final) {
        expect(PasosDeOrden::ayuda(PasosDeOrden::armar($final, permisosDeOrden(), SUFIJO_MODAL_ORDEN), $final))
            ->toBe("operaciones.ordenes.estado_ayuda.{$final->value}");
    }
});

test('ayuda de orden: sin el permiso del paso siguiente avisa que el rol no puede cambiar el estado', function () {
    // Cerrar es el paso que nombra el párrafo desde «Vigente»: sin su permiso no invita a hacer clic.
    $pasos = PasosDeOrden::armar(EstadoOrdenAplicacion::Vigente, permisosDeOrden(cerrar: false), SUFIJO_MODAL_ORDEN);

    expect(PasosDeOrden::ayuda($pasos, EstadoOrdenAplicacion::Vigente))
        ->toBe('operaciones.ordenes.estado_ayuda.vigente ui.pasos.ayuda_sin_permiso');
});

test('ayuda de orden: cada estado tiene su etiqueta y su texto de ayuda en lang/es', function () {
    /** @var array{estado: array<string, string>, ordenes: array{estado_ayuda: array<string, string>, pasos: array<string, string>, estado_pasos_aria: string}} $lang */
    $lang = require dirname(__DIR__, 2).'/lang/es/operaciones.php';

    foreach (EstadoOrdenAplicacion::cases() as $estado) {
        expect($lang['estado'])->toHaveKey($estado->value)
            ->and($lang['ordenes']['estado_ayuda'])->toHaveKey($estado->value);
    }

    expect($lang['ordenes']['pasos'])->toHaveKey('pista_cancelar_emitida')
        ->and($lang['ordenes'])->toHaveKey('estado_pasos_aria');
});

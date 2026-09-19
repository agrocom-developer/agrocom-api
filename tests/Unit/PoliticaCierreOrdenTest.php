<?php

use App\Dominios\Operaciones\Dominio\Excepciones\CierreOrdenNoPermitido;
use App\Dominios\Operaciones\Dominio\ImpedimentoCierreOrden;
use App\Dominios\Operaciones\Dominio\PoliticaCierreOrden;

/*
 * Regla de cierre de una orden (`vigente → consumida`, ADR 0022 adenda del
 * 19/9/2026): solo se cierra si tiene al menos un trabajo (una orden de trabajo
 * registrada), TODAS sus hectáreas están asignadas a algún equipo y NINGÚN
 * trabajo sigue abierto — todos los equipos terminaron los suyos. Pura, sin app ni
 * DB; las cuentas las trae quien la aplica. Hectáreas como decimales, nunca float.
 */

test('cierre: sin ningún trabajo registrado no se puede cerrar', function () {
    expect(PoliticaCierreOrden::impedimento(trabajos: 0, abiertos: 0, hectareasSolicitadas: '20.00', hectareasAsignadas: '0'))
        ->toBe(ImpedimentoCierreOrden::SinTrabajos);
});

test('cierre: con hectáreas sin asignar a ningún equipo no se puede cerrar, aunque los trabajos estén terminados', function () {
    // 12 ha asignadas de 20: aunque el único trabajo esté cerrado, falta repartir el resto.
    expect(PoliticaCierreOrden::impedimento(trabajos: 1, abiertos: 0, hectareasSolicitadas: '20.00', hectareasAsignadas: '12.00'))
        ->toBe(ImpedimentoCierreOrden::HectareasSinAsignar);
});

test('cierre: con algún trabajo sin terminar no se puede cerrar', function () {
    expect(PoliticaCierreOrden::impedimento(trabajos: 3, abiertos: 1, hectareasSolicitadas: '20.00', hectareasAsignadas: '20.00'))
        ->toBe(ImpedimentoCierreOrden::TrabajosAbiertos)
        ->and(PoliticaCierreOrden::impedimento(trabajos: 3, abiertos: 3, hectareasSolicitadas: '20.00', hectareasAsignadas: '20.00'))
        ->toBe(ImpedimentoCierreOrden::TrabajosAbiertos);
});

test('cierre: con trabajos, todas las hectáreas asignadas y todos terminados ya se puede cerrar', function () {
    expect(PoliticaCierreOrden::impedimento(trabajos: 1, abiertos: 0, hectareasSolicitadas: '20.00', hectareasAsignadas: '20.00'))->toBeNull()
        // «20» y «20.00» son la misma cantidad: se comparan como decimales.
        ->and(PoliticaCierreOrden::impedimento(trabajos: 4, abiertos: 0, hectareasSolicitadas: '20', hectareasAsignadas: '20.00'))->toBeNull();
});

test('cierre: si hay más de un impedimento, manda el primero: sin trabajos, hectáreas, trabajos abiertos', function () {
    // Faltan hectáreas Y hay un trabajo abierto: se dice primero lo que falta repartir.
    expect(PoliticaCierreOrden::impedimento(trabajos: 2, abiertos: 1, hectareasSolicitadas: '20.00', hectareasAsignadas: '5.00'))
        ->toBe(ImpedimentoCierreOrden::HectareasSinAsignar);
});

test('cierre: las hectáreas sin asignar nunca salen negativas y se calculan exactas', function () {
    expect((string) PoliticaCierreOrden::hectareasSinAsignar('20.00', '12.25'))->toBe('7.75')
        ->and((string) PoliticaCierreOrden::hectareasSinAsignar('20.00', '20.00'))->toBe('0.00')
        // Asignadas de más (no debería pasar: lo impide CrearOrdenTrabajo): cero, no negativo.
        ->and(PoliticaCierreOrden::hectareasSinAsignar('20.00', '25.00')->isZero())->toBeTrue()
        // Sin float: 0.1 + 0.2 no arrastra error binario.
        ->and((string) PoliticaCierreOrden::hectareasSinAsignar('0.30', '0.10'))->toBe('0.20');
});

test('cierre: la excepción del servidor dice lo que falta con el mensaje del idioma', function () {
    // Sin framework, cada texto sale como su clave (Texto::de()).
    expect(CierreOrdenNoPermitido::por(ImpedimentoCierreOrden::SinTrabajos)->getMessage())
        ->toBe('operaciones.errores.cierre_orden_sin_trabajos')
        ->and(CierreOrdenNoPermitido::por(ImpedimentoCierreOrden::HectareasSinAsignar, 0, '7.75')->getMessage())
        ->toBe('operaciones.errores.cierre_orden_hectareas_sin_asignar')
        ->and(CierreOrdenNoPermitido::por(ImpedimentoCierreOrden::TrabajosAbiertos, 2)->getMessage())
        ->toBe('operaciones.errores.cierre_orden_trabajos_abiertos');
});

test('cierre: los textos de la pantalla y del servidor existen en lang/es', function () {
    /** @var array{ordenes: array<string, mixed>, errores: array<string, string>} $lang */
    $lang = require dirname(__DIR__, 2).'/lang/es/operaciones.php';

    foreach (['cierre_no_disponible_titulo', 'cierre_sin_trabajos', 'cierre_hectareas_sin_asignar', 'cierre_trabajos_abiertos', 'cierre_entendido'] as $clave) {
        expect($lang['ordenes'])->toHaveKey($clave);
    }

    expect($lang['errores'])->toHaveKey('cierre_orden_sin_trabajos')
        ->toHaveKey('cierre_orden_hectareas_sin_asignar')
        ->toHaveKey('cierre_orden_trabajos_abiertos');
});

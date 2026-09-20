<?php

use App\Dominios\Operaciones\Dominio\EstadoEstadia;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesEstadia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;

/*
 * Tabla de transiciones de una estadía en hacienda (invariante 7 de
 * CLAUDE.md, reforma 19/9/2026). Pura, sin Eloquent ni DB para la tabla en
 * sí — mismo patrón que tests/Unit/MaquinaEstadosCampaniaTest.php. El estado
 * derivado (`EstadiaHacienda::estado()`) se prueba instanciando el modelo
 * directo (sin persistir, mismo criterio que `new Campania([...])` en ese
 * archivo): `estado()` solo lee el atributo `salida`, no toca la base.
 */

test('estadia: en curso puede pasar a finalizada', function () {
    expect(TransicionesEstadia::permitida(EstadoEstadia::EnCurso, EstadoEstadia::Finalizada))->toBeTrue();
});

test('estadia: finalizada no tiene ninguna salida', function () {
    expect(TransicionesEstadia::permitida(EstadoEstadia::Finalizada, EstadoEstadia::EnCurso))->toBeFalse()
        ->and(TransicionesEstadia::permitida(EstadoEstadia::Finalizada, EstadoEstadia::Finalizada))->toBeFalse();
});

test('estadia: ningún estado se transiciona a sí mismo', function () {
    expect(TransicionesEstadia::permitida(EstadoEstadia::EnCurso, EstadoEstadia::EnCurso))->toBeFalse()
        ->and(TransicionesEstadia::permitida(EstadoEstadia::Finalizada, EstadoEstadia::Finalizada))->toBeFalse();
});

/*
 * Estado derivado de `salida` (sin columna `estado` propia, ver docblock de
 * `EstadiaHacienda`): `null` es "en curso", cualquier fecha es "finalizada".
 *
 * El caso "finalizada" usa `setRawAttributes()` (mismo mecanismo que
 * Eloquent usa al hidratar una fila de la base) en vez de pasar la fecha por
 * el constructor: el cast `immutable_datetime` sobre un valor NO nulo
 * necesita el formato de fecha de la conexión de base (`getDateFormat()`),
 * que no existe en `tests/Unit` (Pest puro, sin base) — `setRawAttributes()`
 * deja el valor crudo tal cual, y una fecha simple `Y-m-d` se interpreta sin
 * consultar la conexión (mismo camino que toma un valor `null`, que el
 * primer caso de abajo sí prueba pasando por el constructor normal).
 */
test('estadia: el estado se deriva de salida, nunca de una columna propia', function () {
    $enCurso = new EstadiaHacienda(['salida' => null]);

    $finalizada = new EstadiaHacienda;
    $finalizada->setRawAttributes(['salida' => '2026-09-19']);

    expect($enCurso->estado())->toBe(EstadoEstadia::EnCurso)
        ->and($finalizada->estado())->toBe(EstadoEstadia::Finalizada);
});

/*
 * Cada estado tiene su etiqueta y su texto de ayuda en lang/es (mismo criterio
 * que el final de tests/Unit/PasosDeEstadoTest.php): sin esto, `PasosDeEstado`
 * pediría una clave que no existe y la pantalla mostraría la clave cruda.
 */
test('estadia: cada estado tiene su etiqueta y su texto de ayuda en lang/es', function () {
    /** @var array{estadias: array{estado: array<string, string>, estado_ayuda: array<string, string>}} $lang */
    $lang = require dirname(__DIR__, 2).'/lang/es/operaciones.php';

    foreach (EstadoEstadia::cases() as $estado) {
        expect($lang['estadias']['estado'])->toHaveKey($estado->value)
            ->and($lang['estadias']['estado_ayuda'])->toHaveKey($estado->value);
    }
});

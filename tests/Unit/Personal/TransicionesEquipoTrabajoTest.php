<?php

use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\MaquinaEstados\TransicionesEquipoTrabajo;

/*
 * Tabla de transiciones de la cuadrilla (invariante 7 de CLAUDE.md, tarea
 * "cuadrillas-estadias", pedido del dueño 19/9/2026). Pura, sin Eloquent ni
 * DB — mismo patrón que tests/Unit/MaquinaEstadosCampaniaTest.php.
 *
 * A diferencia de `campania` (ruta en línea recta, sin vuelta atrás), la
 * cuadrilla es `activo ⇄ inactivo`: ida y vuelta, ninguno de los dos es
 * terminal — una cuadrilla dada de baja se puede reactivar.
 */

test('cuadrilla: activo puede pasar a inactivo', function () {
    expect(TransicionesEquipoTrabajo::permitida(EstadoEquipoTrabajo::Activo, EstadoEquipoTrabajo::Inactivo))->toBeTrue();
});

test('cuadrilla: inactivo puede volver a activo', function () {
    expect(TransicionesEquipoTrabajo::permitida(EstadoEquipoTrabajo::Inactivo, EstadoEquipoTrabajo::Activo))->toBeTrue();
});

test('cuadrilla: ningún estado se transiciona a sí mismo', function () {
    expect(TransicionesEquipoTrabajo::permitida(EstadoEquipoTrabajo::Activo, EstadoEquipoTrabajo::Activo))->toBeFalse()
        ->and(TransicionesEquipoTrabajo::permitida(EstadoEquipoTrabajo::Inactivo, EstadoEquipoTrabajo::Inactivo))->toBeFalse();
});

/*
 * Cobertura de idioma (molde: final de tests/Unit/PasosDeEstadoTest.php,
 * "ayuda: cada estado de la campaña tiene su etiqueta y su texto de ayuda en
 * lang/es"): `PasosDeEstado::armar()`/`::ayuda()` arman la etiqueta y el
 * párrafo de cada paso leyendo `personal.equipos_trabajo.estado.<valor>` y
 * `personal.equipos_trabajo.estado_ayuda.<valor>` — si a un estado le falta
 * la clave, `Texto::de()` devuelve la clave cruda en vez del texto.
 */
test('cada estado de la cuadrilla tiene su etiqueta y su texto de ayuda en lang/es', function () {
    /** @var array{equipos_trabajo: array{estado: array<string, string>, estado_ayuda: array<string, string>}} $lang */
    $lang = require dirname(__DIR__, 3).'/lang/es/personal.php';

    foreach (EstadoEquipoTrabajo::cases() as $estado) {
        expect($lang['equipos_trabajo']['estado'])->toHaveKey($estado->value)
            ->and($lang['equipos_trabajo']['estado_ayuda'])->toHaveKey($estado->value);
    }
});

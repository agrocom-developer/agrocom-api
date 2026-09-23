<?php

use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrdenTrabajo;

/*
 * Regla de edición de una Orden de Trabajo (tarea 127): se corrige la
 * cabecera mientras ningún trabajo esté `validado`; con algún trabajo
 * `cerrado` la corrección pide motivo; la condición de pago de un equipo
 * solo se toca si TODOS sus trabajos siguen `abierto`. Pura, sin app ni DB.
 */

test('admiteEdicion: se corrige mientras no TODOS los trabajos están validados', function () {
    // Un trabajo validado entre otros que no lo están (caso real de la OT #2
    // del compose demo) no cierra la cabecera a edición.
    expect(PoliticaEdicionOrdenTrabajo::admiteEdicion([
        EstadoTableroTrabajo::Abierto,
        EstadoTableroTrabajo::Validado,
    ]))->toBeTrue();

    expect(PoliticaEdicionOrdenTrabajo::admiteEdicion([
        EstadoTableroTrabajo::Validado,
        EstadoTableroTrabajo::Validado,
    ]))->toBeFalse();
});

test('exigeMotivo: algún trabajo ya no abierto pide motivo; todos abiertos no', function () {
    expect(PoliticaEdicionOrdenTrabajo::exigeMotivo([
        EstadoTableroTrabajo::Abierto,
        EstadoTableroTrabajo::Cerrado,
    ]))->toBeTrue();

    expect(PoliticaEdicionOrdenTrabajo::exigeMotivo([
        EstadoTableroTrabajo::Abierto,
        EstadoTableroTrabajo::Validado,
    ]))->toBeTrue();

    expect(PoliticaEdicionOrdenTrabajo::exigeMotivo([
        EstadoTableroTrabajo::Abierto,
        EstadoTableroTrabajo::Abierto,
    ]))->toBeFalse();
});

test('admiteCondicion: solo si TODOS los trabajos del equipo siguen abiertos', function () {
    expect(PoliticaEdicionOrdenTrabajo::admiteCondicion([
        EstadoTableroTrabajo::Abierto,
        EstadoTableroTrabajo::Abierto,
    ]))->toBeTrue();

    expect(PoliticaEdicionOrdenTrabajo::admiteCondicion([
        EstadoTableroTrabajo::Abierto,
        EstadoTableroTrabajo::Cerrado,
    ]))->toBeFalse();
});

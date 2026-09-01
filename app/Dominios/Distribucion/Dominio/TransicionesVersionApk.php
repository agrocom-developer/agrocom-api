<?php

namespace App\Dominios\Distribucion\Dominio;

/**
 * Tabla de transiciones permitidas para una versión del APK (invariante 7 de
 * CLAUDE.md). Clase pura, sin Eloquent — {@see
 * \App\Dominios\Distribucion\Aplicacion\MaquinaEstados\MaquinaEstadosVersionApk}
 * es la única que la consulta antes de escribir.
 *
 * `Autorizada → Pendiente` es la transición que desautoriza: la usa la
 * máquina de estados internamente cuando autoriza una versión nueva y había
 * otra vigente. `Rechazada → Pendiente` deja abierta la puerta a reconsiderar
 * una versión rechazada sin tener que resubirla.
 */
final class TransicionesVersionApk
{
    /** @var list<array{EstadoVersionApk, EstadoVersionApk}> */
    private const PERMITIDAS = [
        [EstadoVersionApk::Pendiente, EstadoVersionApk::Autorizada],
        [EstadoVersionApk::Pendiente, EstadoVersionApk::Rechazada],
        [EstadoVersionApk::Rechazada, EstadoVersionApk::Pendiente],
        [EstadoVersionApk::Autorizada, EstadoVersionApk::Pendiente],
    ];

    public static function permitida(EstadoVersionApk $desde, EstadoVersionApk $hasta): bool
    {
        foreach (self::PERMITIDAS as [$origen, $destino]) {
            if ($origen === $desde && $destino === $hasta) {
                return true;
            }
        }

        return false;
    }
}

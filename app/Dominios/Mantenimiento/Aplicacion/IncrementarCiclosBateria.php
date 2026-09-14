<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;

/**
 * Oyente de `RecargaRegistrada` (HU-87, tarea 102): el "odómetro" de la
 * batería — cada recarga real de vuelo suma un ciclo a la que volvió del
 * vuelo, nunca a mano (ver la guarda nueva de {@see ActualizarBateria}).
 *
 * Cableado en `MantenimientoServiceProvider::boot()`, mismo molde que
 * `FinanzasServiceProvider::boot()` con `SesionValidada`/`GenerarDevengosSesion`.
 *
 * La correlación `bateriaSalienteId` → `man_baterias.identificador` es
 * blanda (texto libre, sin FK — ver el docblock de `RegistroRecarga`): si no
 * hay batería con ese identificador, no hace nada. No es un error — igual
 * que `Operaciones` no valida el identificador al recibir la recarga, este
 * oyente tampoco puede.
 */
final class IncrementarCiclosBateria
{
    public function ejecutar(string $bateriaSalienteId): void
    {
        $bateria = Bateria::query()->where('identificador', $bateriaSalienteId)->first();

        if ($bateria === null) {
            return;
        }

        $bateria->ciclos_acumulados++;
        $bateria->save();
    }
}

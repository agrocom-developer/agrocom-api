<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Dominio\Excepciones\BateriaDuplicada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use Illuminate\Database\QueryException;

/**
 * Edición de una batería del catálogo (HU-39, tarea 51), incluidos sus
 * ciclos acumulados: a diferencia de un monto ya validado (p. ej.
 * `fin_gastos`), acá SÍ se espera actualizar el contador con el uso — es el
 * dato que dispara la alerta de retiro (ver `ListarBaterias`). Mismo
 * criterio que `ActualizarVehiculo` para la traducción de la violación del
 * índice único parcial.
 */
final class ActualizarBateria
{
    /**
     * @throws BateriaDuplicada si el identificador ya pertenece a otra
     *                          batería activa (índice parcial
     *                          `man_baterias_identificador_unico`).
     */
    public function ejecutar(Bateria $bateria, string $identificador, int $ciclosAcumulados, ?int $baseId, EstadoBateria $estado): Bateria
    {
        $bateria->identificador = $identificador;
        $bateria->ciclos_acumulados = $ciclosAcumulados;
        $bateria->base_id = $baseId;
        $bateria->estado = $estado->value;

        try {
            $bateria->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $identificador);
        }

        return $bateria->refresh();
    }

    /**
     * @throws BateriaDuplicada si la violación corresponde al identificador.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicada(QueryException $excepcion, string $identificador): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'man_baterias_identificador_unico') || str_contains($mensaje, 'man_baterias.identificador')) {
            throw BateriaDuplicada::porIdentificador($identificador);
        }

        throw $excepcion;
    }
}

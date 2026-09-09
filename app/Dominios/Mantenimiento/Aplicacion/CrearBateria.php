<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Dominio\Excepciones\BateriaDuplicada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use Illuminate\Database\QueryException;

/**
 * Alta de una batería del catálogo (HU-39, tarea 51): identificador, ciclos
 * acumulados (una batería puede entrar al catálogo con uso previo), base
 * asignada (opcional) y estado.
 */
final class CrearBateria
{
    /**
     * @throws BateriaDuplicada si el identificador ya pertenece a otra
     *                          batería activa (índice parcial
     *                          `man_baterias_identificador_unico`).
     */
    public function ejecutar(string $identificador, int $ciclosAcumulados, ?int $baseId, EstadoBateria $estado): Bateria
    {
        $bateria = new Bateria([
            'identificador' => $identificador,
            'ciclos_acumulados' => $ciclosAcumulados,
            'base_id' => $baseId,
            'estado' => $estado->value,
        ]);

        try {
            $bateria->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $identificador);
        }

        return $bateria->refresh();
    }

    /**
     * Traduce la violación del índice único parcial a una excepción de
     * dominio legible — mismo criterio que
     * `CrearVehiculo::relanzarComoDuplicado`. El formato del mensaje
     * difiere por driver: Postgres nombra el índice
     * (`man_baterias_identificador_unico`); SQLite (motor de los tests)
     * nombra tabla.columna (`man_baterias.identificador`).
     *
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

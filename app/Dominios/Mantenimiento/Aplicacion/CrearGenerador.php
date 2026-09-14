<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoGenerador;
use App\Dominios\Mantenimiento\Dominio\Excepciones\GeneradorDuplicado;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use Illuminate\Database\QueryException;

/**
 * Alta de un generador de catálogo (tarea 72, HU-49): identificador, modelo
 * (opcional), base asignada (opcional), estado y horas inicial/actual
 * (opcionales, HU-86 tarea 101).
 */
final class CrearGenerador
{
    /**
     * @throws GeneradorDuplicado si el identificador ya pertenece a otro
     *                            generador activo (índice parcial
     *                            `man_generadores_identificador_unico`).
     */
    public function ejecutar(
        string $identificador,
        ?string $modelo,
        ?int $baseId,
        EstadoGenerador $estado,
        ?string $horasInicial,
        ?string $horasActual,
    ): Generador {
        $generador = new Generador([
            'identificador' => $identificador,
            'modelo' => $modelo,
            'base_id' => $baseId,
            'estado' => $estado->value,
            'horas_inicial' => $horasInicial,
            'horas_actual' => $horasActual,
        ]);

        try {
            $generador->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $identificador);
        }

        return $generador->refresh();
    }

    /**
     * Traduce la violación del índice único parcial a una excepción de
     * dominio legible — mismo criterio que
     * `CrearVehiculo::relanzarComoDuplicado`. El formato del mensaje difiere
     * por driver: Postgres nombra el índice
     * (`man_generadores_identificador_unico`); SQLite (motor de los tests)
     * nombra tabla.columna (`man_generadores.identificador`).
     *
     * @throws GeneradorDuplicado si la violación corresponde al identificador.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, string $identificador): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'man_generadores_identificador_unico') || str_contains($mensaje, 'man_generadores.identificador')) {
            throw GeneradorDuplicado::porIdentificador($identificador);
        }

        throw $excepcion;
    }
}

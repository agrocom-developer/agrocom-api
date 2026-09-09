<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoGenerador;
use App\Dominios\Mantenimiento\Dominio\Excepciones\GeneradorDuplicado;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use Illuminate\Database\QueryException;

/**
 * Edición de un generador de catálogo (tarea 72, HU-49). Mismo criterio que
 * `CrearGenerador` para la traducción de la violación del índice único
 * parcial.
 */
final class ActualizarGenerador
{
    /**
     * @throws GeneradorDuplicado si el identificador ya pertenece a otro
     *                            generador activo (índice parcial
     *                            `man_generadores_identificador_unico`).
     */
    public function ejecutar(
        Generador $generador,
        string $identificador,
        ?string $modelo,
        ?int $baseId,
        EstadoGenerador $estado,
        ?string $horasUso,
    ): Generador {
        $generador->identificador = $identificador;
        $generador->modelo = $modelo;
        $generador->base_id = $baseId;
        $generador->estado = $estado->value;
        $generador->horas_uso = $horasUso;

        try {
            $generador->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $identificador);
        }

        return $generador->refresh();
    }

    /**
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

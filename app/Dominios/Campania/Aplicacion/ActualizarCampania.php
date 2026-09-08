<?php

namespace App\Dominios\Campania\Aplicacion;

use App\Dominios\Campania\Dominio\Excepciones\CampaniaDuplicada;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use Illuminate\Database\QueryException;

/**
 * Edición de los datos de una campaña (ADR 0015 punto 1, tarea 69):
 * `codigo`/`nombre`/`fecha_inicio`/`fecha_fin`. Sin `estado`: la transición
 * de estado es responsabilidad exclusiva de {@see
 * \App\Dominios\Campania\Aplicacion\MaquinaEstados\MaquinaEstadosCampania}
 * (invariante 7 de CLAUDE.md), nunca de esta clase.
 */
final class ActualizarCampania
{
    /**
     * @throws CampaniaDuplicada si el código ya pertenece a otra campaña activa
     *                           (índice parcial `cpn_campanias_codigo_unico`).
     */
    public function ejecutar(Campania $campania, string $codigo, ?string $nombre, string $fechaInicio, string $fechaFin): Campania
    {
        $campania->fill([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ]);

        try {
            $campania->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $codigo);
        }

        return $campania->refresh();
    }

    /**
     * @throws CampaniaDuplicada si la violación corresponde al código.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicada(QueryException $excepcion, string $codigo): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'cpn_campanias_codigo_unico') || str_contains($mensaje, 'cpn_campanias.codigo')) {
            throw CampaniaDuplicada::porCodigo($codigo);
        }

        throw $excepcion;
    }
}

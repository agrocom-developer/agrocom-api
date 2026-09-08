<?php

namespace App\Dominios\Campania\Aplicacion;

use App\Dominios\Campania\Aplicacion\MaquinaEstados\MaquinaEstadosCampania;
use App\Dominios\Campania\Dominio\Excepciones\CampaniaDuplicada;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use Illuminate\Database\QueryException;

/**
 * Alta de una campaña (ADR 0015 punto 1, tarea 69). El estado inicial
 * (`planificada`) lo fija {@see MaquinaEstadosCampania::crear()}, nunca esta
 * clase directamente (invariante 7).
 */
final class CrearCampania
{
    public function __construct(private readonly MaquinaEstadosCampania $maquinaEstados) {}

    /**
     * @throws CampaniaDuplicada si el código ya pertenece a otra campaña activa
     *                           (índice parcial `cpn_campanias_codigo_unico`).
     */
    public function ejecutar(string $codigo, ?string $nombre, string $fechaInicio, string $fechaFin): Campania
    {
        try {
            return $this->maquinaEstados->crear([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ]);
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $codigo);
        }
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

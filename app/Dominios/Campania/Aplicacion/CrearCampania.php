<?php

namespace App\Dominios\Campania\Aplicacion;

use App\Dominios\Campania\Aplicacion\MaquinaEstados\MaquinaEstadosCampania;
use App\Dominios\Campania\Dominio\Excepciones\CampaniaDuplicada;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

/**
 * Alta de una campaña (ADR 0015 punto 1, tarea 69). El estado inicial
 * (`planificada`) lo fija {@see MaquinaEstadosCampania::crear()}, nunca esta
 * clase directamente (invariante 7).
 *
 * `nombre` se autogenera cuando llega `null` (HU-77, tarea 93):
 * `Estación/AñoInicio/AñoFin` (p. ej. `Verano/2025/2026`) — el encargado ya
 * no tiene que tipearlo a mano si no quiere. `ActualizarCampania` NO repite
 * esta lógica a propósito: editar sin nombre preserva el que ya tiene, nunca
 * lo regenera solo (ver su docblock).
 */
final class CrearCampania
{
    public function __construct(private readonly MaquinaEstadosCampania $maquinaEstados) {}

    /**
     * @throws CampaniaDuplicada si el código ya pertenece a otra campaña activa
     *                           del mismo cliente (índice parcial `cpn_campanias_cliente_codigo_unico`).
     */
    public function ejecutar(int $clienteId, string $codigo, ?string $nombre, string $fechaInicio, string $fechaFin, string $estacion): Campania
    {
        try {
            return $this->maquinaEstados->crear([
                'cliente_id' => $clienteId,
                'codigo' => $codigo,
                'nombre' => $nombre ?? $this->generarNombre($estacion, $fechaInicio, $fechaFin),
                'estacion' => $estacion,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ]);
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $codigo);
        }
    }

    private function generarNombre(string $estacion, string $fechaInicio, string $fechaFin): string
    {
        return sprintf(
            '%s/%d/%d',
            ucfirst($estacion),
            Carbon::parse($fechaInicio)->year,
            Carbon::parse($fechaFin)->year,
        );
    }

    /**
     * @throws CampaniaDuplicada si la violación corresponde al código.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicada(QueryException $excepcion, string $codigo): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'cpn_campanias_cliente_codigo_unico') || str_contains($mensaje, 'cpn_campanias.codigo')) {
            throw CampaniaDuplicada::porCodigo($codigo);
        }

        throw $excepcion;
    }
}

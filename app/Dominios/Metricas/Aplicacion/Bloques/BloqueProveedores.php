<?php

namespace App\Dominios\Metricas\Aplicacion\Bloques;

use App\Dominios\Metricas\Contratos\LecturaMetricasIngreso;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;

/** Tabla «por proveedor»: los que más servicios completaron en el período; nombre y tasas vienen del contrato de `Proveedores`. */
final class BloqueProveedores
{
    private const EN_TABLA = 10;

    public function __construct(
        private readonly LecturaMetricasIngreso $lectura,
        private readonly LecturaProveedores $proveedores,
    ) {}

    /** @return list<array<string, mixed>> */
    public function armar(PeriodoDeMetricas $periodo): array
    {
        $filas = $this->lectura->serviciosPorProveedor($periodo, self::EN_TABLA);
        $respuestas = $this->lectura->minutosDeRespuesta($periodo, array_column($filas, 'proveedor_id'));

        return array_map(function (array $fila) use ($respuestas): array {
            $datos = $this->proveedores->proveedor($fila['proveedor_id']);

            return [
                'proveedor_id' => $fila['proveedor_id'],
                'nombre' => $datos === null ? "#{$fila['proveedor_id']}" : $datos->razonSocial,
                'tipo' => $datos === null ? '' : $datos->tipo,
                'servicios' => $fila['servicios'],
                'calificacion_promedio' => $datos === null ? null : $datos->calificacionPromedio,
                'calificaciones_conteo' => $datos === null ? 0 : $datos->calificacionesConteo,
                'tasa_aceptacion' => $datos === null ? null : $datos->tasaAceptacion,
                'respuesta_promedio_min' => Razones::promedio($respuestas[$fila['proveedor_id']] ?? []),
            ];
        }, $filas);
    }
}

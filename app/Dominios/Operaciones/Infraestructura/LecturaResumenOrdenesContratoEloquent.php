<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Aplicacion\ProximaAplicacionPorContrato;
use App\Dominios\Operaciones\Contratos\DatosAplicacionAbierta;
use App\Dominios\Operaciones\Contratos\LecturaResumenOrdenesContrato;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;

/**
 * Implementación Eloquent del contrato de resumen de Órdenes de Aplicación
 * por contrato. Vive fuera de `Infraestructura/Eloquent/` por el mismo
 * motivo que {@see LecturaContadoresPanelEloquent}: no es un modelo, es el
 * adaptador que el `ServiceProvider` liga al contrato.
 */
final class LecturaResumenOrdenesContratoEloquent implements LecturaResumenOrdenesContrato
{
    public function __construct(private readonly ProximaAplicacionPorContrato $proximaAplicacion) {}

    public function siguienteAplicacion(int $contratoId, int $aplicacionesPrevistas): ?int
    {
        return $this->proximaAplicacion->ejecutar([$contratoId => $aplicacionesPrevistas])[$contratoId]['siguiente'];
    }

    public function aplicacionesAbiertas(array $contratoIds): array
    {
        if ($contratoIds === []) {
            return [];
        }

        $resultado = [];

        OrdenAplicacion::query()
            ->whereIn('contrato_id', $contratoIds)
            ->whereIn('estado', EstadoOrdenAplicacion::valoresAbiertos())
            ->orderBy('id')
            ->get(['id', 'contrato_id', 'nro_aplicacion', 'estado'])
            ->each(function (OrdenAplicacion $orden) use (&$resultado): void {
                $resultado[(int) $orden->contrato_id] ??= new DatosAplicacionAbierta(
                    ordenId: (int) $orden->id,
                    contratoId: (int) $orden->contrato_id,
                    nroAplicacion: (int) $orden->nro_aplicacion,
                    estado: $orden->estado->value,
                    estadoEtiqueta: Texto::de("operaciones.estado.{$orden->estado->value}"),
                );
            });

        return $resultado;
    }

    public function resumen(array $contratoIds): array
    {
        if ($contratoIds === []) {
            return ['total' => 0, 'vigentes' => 0, 'abiertas' => 0];
        }

        $consulta = OrdenAplicacion::query()->whereIn('contrato_id', $contratoIds);

        return [
            'total' => (clone $consulta)->count(),
            'vigentes' => (clone $consulta)->where('estado', EstadoOrdenAplicacion::Vigente)->count(),
            'abiertas' => (clone $consulta)->whereIn('estado', EstadoOrdenAplicacion::valoresAbiertos())->count(),
        ];
    }
}

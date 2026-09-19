<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\Excepciones\AplicacionesCompletas;
use App\Dominios\Operaciones\Dominio\Excepciones\ContratoConOrdenAbierta;
use App\Dominios\Operaciones\Dominio\NumeracionAplicaciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;

/**
 * Qué número de aplicación le toca a cada contrato y si admite una orden nueva
 * (ADR 0022) — lo que necesita el formulario de alta para mostrar solo los
 * contratos que pueden recibir una orden y decirle al usuario "Aplicación 2 de
 * 3". Solo lectura, en UNA consulta para todos los contratos pedidos; la regla
 * es la misma {@see NumeracionAplicaciones} que aplica {@see CrearOrden}, así
 * que lo que el formulario anuncia y lo que el servidor hace cumplir nunca
 * divergen.
 */
final class ProximaAplicacionPorContrato
{
    public const string DISPONIBLE = 'disponible';

    public const string CON_ORDEN_ABIERTA = 'con_orden_abierta';

    public const string COMPLETAS = 'completas';

    /**
     * @param  array<int, int>  $previstasPorContrato  contrato_id => aplicaciones_previstas
     * @return array<int, array{estado: string, siguiente: ?int, abierta_nro: ?int}>
     *                                                                               contrato_id => `disponible` (con `siguiente`), `con_orden_abierta` (con `abierta_nro`) o `completas`
     */
    public function ejecutar(array $previstasPorContrato): array
    {
        if ($previstasPorContrato === []) {
            return [];
        }

        $ordenesPorContrato = OrdenAplicacion::query()
            ->whereIn('contrato_id', array_keys($previstasPorContrato))
            ->get(['id', 'contrato_id', 'nro_aplicacion', 'estado', 'causa_cancelacion'])
            ->groupBy('contrato_id');

        $resultado = [];

        foreach ($previstasPorContrato as $contratoId => $previstas) {
            $ordenes = $ordenesPorContrato->get($contratoId, collect())
                ->map(fn (OrdenAplicacion $orden): array => [
                    'nro' => $orden->nro_aplicacion,
                    'estado' => $orden->estado,
                    'causa' => $orden->causa_cancelacion,
                ])
                ->values()
                ->all();

            try {
                $resultado[$contratoId] = [
                    'estado' => self::DISPONIBLE,
                    'siguiente' => NumeracionAplicaciones::siguiente($ordenes, $previstas, $contratoId),
                    'abierta_nro' => null,
                ];
            } catch (ContratoConOrdenAbierta) {
                $abierta = $ordenesPorContrato->get($contratoId, collect())
                    ->first(fn (OrdenAplicacion $orden): bool => $orden->estado->estaAbierta());

                $resultado[$contratoId] = [
                    'estado' => self::CON_ORDEN_ABIERTA,
                    'siguiente' => null,
                    'abierta_nro' => $abierta?->nro_aplicacion,
                ];
            } catch (AplicacionesCompletas) {
                $resultado[$contratoId] = ['estado' => self::COMPLETAS, 'siguiente' => null, 'abierta_nro' => null];
            }
        }

        return $resultado;
    }
}

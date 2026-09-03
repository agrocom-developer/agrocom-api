<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Factura;
use App\Dominios\Operaciones\Contratos\LecturaActaConformada;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Reporte comercial de avance por contrato (HU-32, tarea 46): "como dueño,
 * quiero un reporte comercial de avance por cliente, contrato y campaña,
 * para saber cuánto queda por aplicar y por cobrar" — cierra Sprint 9.
 * Agrega, sin mutar nada, tres magnitudes de origen distinto por contrato:
 *
 * - Hectáreas contratadas: columna propia de `Contrato` (Comercial).
 * - Hectáreas aplicadas: suma de `hectareasConformadas` de TODAS las actas
 *   firmadas del contrato, vía {@see LecturaActaConformada::listarFirmadas()}
 *   (Operaciones, ADR 0003 regla 2) — sin filtrar por facturadas o no: un
 *   acta firmada es hectárea aplicada, esté cobrada o todavía no.
 * - Hectáreas/monto facturado: `com_facturas`, dato propio de este módulo.
 *
 * Las tres sumas se acumulan con `Brick\Math\BigDecimal` en PHP, nunca con
 * `SUM()` de SQL: en SQLite (motor de los tests) la agregación numérica
 * pasa por REAL/float, lo que violaría la invariante 6 de CLAUDE.md para un
 * reporte que existe justamente para cuadrar dinero y hectáreas exacto.
 */
final class ObtenerAvanceComercial
{
    public function __construct(private readonly LecturaActaConformada $lecturaActa) {}

    /**
     * @return list<array{
     *     contratoId: int,
     *     clienteNombre: string,
     *     hectareasContratadas: string,
     *     hectareasAplicadas: string,
     *     hectareasFacturadas: string,
     *     montoFacturado: string,
     * }>
     */
    public function ejecutar(?int $clienteId = null, ?int $contratoId = null): array
    {
        $contratos = Contrato::query()
            ->with('cliente:id,razon_social')
            ->when($clienteId !== null, fn ($consulta) => $consulta->where('cliente_id', $clienteId))
            ->when($contratoId !== null, fn ($consulta) => $consulta->where('id', $contratoId))
            ->orderBy('id')
            ->get();

        if ($contratos->isEmpty()) {
            return [];
        }

        $hectareasAplicadasPorContrato = $this->agruparHectareasAplicadas();
        $facturadoPorContrato = $this->agruparFacturado($contratos->pluck('id'));

        return $contratos
            ->map(function (Contrato $contrato) use ($hectareasAplicadasPorContrato, $facturadoPorContrato): array {
                $facturado = $facturadoPorContrato[$contrato->id] ?? ['hectareas' => BigDecimal::zero(), 'monto' => BigDecimal::zero()];

                return [
                    'contratoId' => $contrato->id,
                    'clienteNombre' => $contrato->cliente->razon_social,
                    'hectareasContratadas' => $contrato->hectareas_contratadas,
                    'hectareasAplicadas' => $this->aEscalaDos($hectareasAplicadasPorContrato[$contrato->id] ?? BigDecimal::zero()),
                    'hectareasFacturadas' => $this->aEscalaDos($facturado['hectareas']),
                    'montoFacturado' => $this->aEscalaDos($facturado['monto']),
                ];
            })
            ->all();
    }

    /** @return array<int, BigDecimal> */
    private function agruparHectareasAplicadas(): array
    {
        $acumulado = [];

        foreach ($this->lecturaActa->listarFirmadas() as $acta) {
            $acumulado[$acta->contratoId] = ($acumulado[$acta->contratoId] ?? BigDecimal::zero())
                ->plus($acta->hectareasConformadas);
        }

        return $acumulado;
    }

    /**
     * @param  Collection<int, int>  $contratoIds
     * @return array<int, array{hectareas: BigDecimal, monto: BigDecimal}>
     */
    private function agruparFacturado(Collection $contratoIds): array
    {
        $acumulado = [];

        $facturas = Factura::query()
            ->whereIn('contrato_id', $contratoIds)
            ->get(['contrato_id', 'hectareas_facturadas', 'monto']);

        foreach ($facturas as $factura) {
            $actual = $acumulado[$factura->contrato_id] ?? ['hectareas' => BigDecimal::zero(), 'monto' => BigDecimal::zero()];

            $acumulado[$factura->contrato_id] = [
                'hectareas' => $actual['hectareas']->plus($factura->hectareas_facturadas),
                'monto' => $actual['monto']->plus($factura->monto),
            ];
        }

        return $acumulado;
    }

    private function aEscalaDos(BigDecimal $valor): string
    {
        return (string) $valor->toScale(2, RoundingMode::HalfUp);
    }
}

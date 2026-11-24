<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoCoberturaTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Solicitudes\Contratos\SolicitudParaTrabajo;
use Brick\Math\BigDecimal;

/**
 * Cobertura de hectáreas de un trabajo frente a lo pedido en su solicitud
 * (HU-07, tarea 20; espec §5). Caso de uso, no método del modelo `Trabajo` — a
 * diferencia de `Trabajo::estadoTablero()`/`cuadreCaldo()` (tarea 15/18, puro
 * Eloquent local), este cálculo necesita las hectáreas SOLICITADAS, que viven
 * en el módulo `Solicitudes` (`sol_solicitudes_servicio.hectareas_solicitadas`,
 * no `ope_trabajos.hectareas_declaradas` — ese campo es la suma DERIVADA de
 * sesiones, ver docblock de `Trabajo`). Cruzar módulo exige el contrato
 * `SolicitudParaTrabajo` (ADR 0003, regla 2), que un
 * modelo Eloquent no puede recibir por inyección de dependencias con
 * limpieza — de ahí que esto sea un caso de uso.
 *
 * Recalculado desde los registros de origen en cada llamada (invariante 6,
 * mismo criterio que `cuadreCaldo()`): nunca un valor cacheado.
 */
final class CalcularCoberturaTrabajo
{
    public function __construct(
        private readonly SolicitudParaTrabajo $solicitudes,
    ) {}

    /**
     * `null` cuando el trabajo está en curso sin alerta: ni cubrió lo pedido
     * (dentro de tolerancia) ni tiene ninguna sesión vigente cerrada con
     * motivo distinto de `completado` (ver docblock de `EstadoCoberturaTrabajo`).
     */
    public function ejecutar(Trabajo $trabajo): ?EstadoCoberturaTrabajo
    {
        $solicitud = $trabajo->solicitud_id === null ? null : $this->solicitudes->datos((int) $trabajo->solicitud_id);

        if ($solicitud === null) {
            return null;
        }

        $sumaSesionesVigentes = BigDecimal::of(
            (string) $trabajo->sesiones()->whereNull('anulada_en')->sum('hectareas_declaradas')
        );
        $hectareasLote = BigDecimal::of($solicitud->hectareasSolicitadas);
        $tolerancia = BigDecimal::of((string) config('operaciones.tolerancia_solape_hectareas'));
        $limite = $hectareasLote->plus($tolerancia);

        if ($sumaSesionesVigentes->isGreaterThan($limite)) {
            return EstadoCoberturaTrabajo::Observado;
        }

        if ($sumaSesionesVigentes->isGreaterThanOrEqualTo($hectareasLote)) {
            return EstadoCoberturaTrabajo::Completo;
        }

        $tieneSesionCerradaSinCompletar = $trabajo->sesiones()
            ->whereNull('anulada_en')
            ->whereNotNull('motivo_cierre')
            ->where('motivo_cierre', '!=', 'completado')
            ->exists();

        return $tieneSesionCerradaSinCompletar ? EstadoCoberturaTrabajo::Parcial : null;
    }
}

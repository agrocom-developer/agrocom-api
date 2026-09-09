<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Dominio\EstadoCoberturaTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Brick\Math\BigDecimal;

/**
 * Cobertura de hectáreas de un trabajo frente al lote (HU-07, tarea 20; espec
 * §5). Caso de uso, no método del modelo `Trabajo` — a diferencia de
 * `Trabajo::estadoTablero()`/`cuadreCaldo()` (tarea 15/18, puro Eloquent
 * local), este cálculo necesita las hectáreas del LOTE, que vive en el
 * módulo `Comercial` (`com_lotes.hectareas`, no `ope_trabajos.hectareas_declaradas`
 * — ese campo es la suma DERIVADA de sesiones, ver docblock de `Trabajo`).
 * Cruzar módulo exige el contrato `LecturaLotes` (ADR 0003, regla 2), que un
 * modelo Eloquent no puede recibir por inyección de dependencias con
 * limpieza — de ahí que esto sea un caso de uso.
 *
 * Recalculado desde los registros de origen en cada llamada (invariante 6,
 * mismo criterio que `cuadreCaldo()`): nunca un valor cacheado.
 */
final class CalcularCoberturaTrabajo
{
    public function __construct(
        private readonly LecturaLotes $lecturaLotes,
    ) {}

    /**
     * `null` cuando el trabajo está en curso sin alerta: ni cubrió el lote
     * (dentro de tolerancia) ni tiene ninguna sesión vigente cerrada con
     * motivo distinto de `completado` (ver docblock de `EstadoCoberturaTrabajo`).
     */
    public function ejecutar(Trabajo $trabajo): ?EstadoCoberturaTrabajo
    {
        $lote = $this->lecturaLotes->obtenerPorId($trabajo->lote_id);

        if ($lote === null) {
            return null;
        }

        $sumaSesionesVigentes = BigDecimal::of(
            (string) $trabajo->sesiones()->whereNull('anulada_en')->sum('hectareas_declaradas')
        );
        $hectareasLote = BigDecimal::of($lote->hectareas);
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

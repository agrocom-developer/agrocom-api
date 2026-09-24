<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Planilla;
use App\Dominios\Finanzas\Infraestructura\Eloquent\PlanillaDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Recibo individual en PDF de un renglón de planilla (HU-30, tarea 44):
 * persona, período, devengado, anticipos, neto. Mismo patrón exacto que
 * `Operaciones/Aplicacion/GenerarActaTrabajo` — `Pdf::loadView()->output()` +
 * `Storage::disk('r2')->put()`, guardando la ruta en `pdf_path`.
 *
 * Invocada únicamente por `Aplicacion/AprobarPlanilla`, DESPUÉS de que la
 * fila de `$detalle` ya existe (guardarraíl de `RegistrarEvidencia`: nunca
 * I/O de archivo antes de persistir) — nunca al generar la planilla, cuando
 * todavía está en `Borrador`.
 *
 * Ruta `planillas/{planilla_id}/recibos/{detalle_id}.pdf` (ADR 0026): los
 * recibos ya emitidos con la ruta anterior (`planillas/{planilla_id}/{detalle_id}.pdf`)
 * siguen resolviendo por `pdf_path`.
 */
final class GenerarReciboPlanilla
{
    public function ejecutar(Planilla $planilla, PlanillaDetalle $detalle): PlanillaDetalle
    {
        $pdf = Pdf::loadView('finanzas::pdf.recibo-planilla', [
            'planilla' => $planilla,
            'detalle' => $detalle,
        ])->output();

        $ruta = sprintf('planillas/%d/recibos/%d.pdf', $planilla->id, $detalle->id);
        Storage::disk('r2')->put($ruta, $pdf);

        $detalle->update(['pdf_path' => $ruta]);

        return $detalle;
    }

    /**
     * Reconstruye el PDF si el archivo físico no existe en `r2` pero la fila
     * del recibo sí — mismo criterio que `GenerarActaTrabajo::asegurarPdf`.
     * Vuelve a cargar `$detalle->planilla` para renderizar con los datos
     * ACTUALES de la fila, no con lo que haya quedado en memoria.
     *
     * Precondición: `$detalle->pdf_path` no es `null`.
     */
    public function asegurarPdf(PlanillaDetalle $detalle): void
    {
        /** @var string $ruta */
        $ruta = $detalle->pdf_path;

        if (Storage::disk('r2')->exists($ruta)) {
            return;
        }

        $detalle->loadMissing('planilla');

        $pdf = Pdf::loadView('finanzas::pdf.recibo-planilla', [
            'planilla' => $detalle->planilla,
            'detalle' => $detalle,
        ])->output();

        Storage::disk('r2')->put($ruta, $pdf);
    }
}

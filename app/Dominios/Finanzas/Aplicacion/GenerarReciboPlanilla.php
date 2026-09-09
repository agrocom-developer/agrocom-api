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
 */
final class GenerarReciboPlanilla
{
    public function ejecutar(Planilla $planilla, PlanillaDetalle $detalle): PlanillaDetalle
    {
        $pdf = Pdf::loadView('finanzas::pdf.recibo-planilla', [
            'planilla' => $planilla,
            'detalle' => $detalle,
        ])->output();

        $ruta = sprintf('planillas/%d/%d.pdf', $planilla->id, $detalle->id);
        Storage::disk('r2')->put($ruta, $pdf);

        $detalle->update(['pdf_path' => $ruta]);

        return $detalle;
    }
}

<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Infraestructura\Eloquent\ReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * HU-18 (tarea 25): genera el reporte técnico de un trabajo, disparado
 * desde `Aplicacion/FirmarActa.php` justo después de que
 * `MaquinaEstadosActa::firmar()` transiciona el acta a `firmada`, dentro de
 * la MISMA transacción (mismo guardarraíl "INSERT antes que I/O" que usó
 * `GenerarActaTrabajo`, tarea 24).
 *
 * No hay evento de dominio `ActaFirmada` (la tarea 24 no lo dejó — verificado
 * en el código, no asumido): `MaquinaEstadosActa::firmar()` solo muta y
 * guarda, sin `event(...)`. Enganchar acá, extendiendo el caso de uso de
 * firmar, evita inventar un evento que esa tarea no dejó (ver runs/25.md,
 * igual criterio que el prompt pide para este caso).
 *
 * Idempotente por REGLA DE NEGOCIO igual que `GenerarActaTrabajo`: "un
 * trabajo tiene a lo sumo un reporte" (`ope_reportes_tecnicos.trabajo_id`
 * `UNIQUE`) — un segundo llamado sobre un trabajo que ya tiene reporte
 * devuelve la fila existente sin regenerar nada. En la práctica esto ya lo
 * evita `FirmarActa` (la firma en sí es idempotente y solo llama a esta
 * clase en la rama que TRANSICIONA de verdad), pero el guardarraíl queda acá
 * también, mismo criterio defensivo que `GenerarActaTrabajo`.
 *
 * Ruta `trabajos/{trabajo_id}/reportes-tecnicos/{reporte_id}.pdf` (ADR 0026,
 * objeto primero, actividad después): los reportes ya emitidos con la ruta
 * anterior (`reportes-tecnicos/{trabajo_id}/{reporte_id}.pdf`) siguen
 * resolviendo por `pdf_path`.
 */
final class GenerarReporteTecnico
{
    public function __construct(private readonly ArmarContenidoReporteTecnico $armarContenido) {}

    public function ejecutar(Trabajo $trabajo): ReporteTecnico
    {
        $existente = ReporteTecnico::query()->where('trabajo_id', $trabajo->id)->first();

        if ($existente !== null) {
            return $existente;
        }

        $datos = $this->armarContenido->ejecutar($trabajo);

        $reporte = ReporteTecnico::create([
            'trabajo_id' => $trabajo->id,
            // `->utc()`: mismo motivo que `MaquinaEstadosActa::firmar()` con
            // `fecha_firma` — `hora_inicio`/`hora_fin` son `datetime` SIN tz,
            // así que `format()` escribiría la hora local literal del offset
            // original (`Sesion::inicio`/`fin` traen el que mandó el
            // dispositivo, no necesariamente UTC) y una relectura posterior
            // la interpretaría como UTC, corriendo el instante real por ese
            // offset (ver runs/25.md).
            'hora_inicio' => $datos['hora_inicio']?->utc(),
            'hora_fin' => $datos['hora_fin']?->utc(),
            'generado_en' => now(),
        ]);

        $pdf = Pdf::loadView('operaciones::pdf.reporte-tecnico', ['trabajo' => $trabajo, 'reporte' => $reporte, 'datos' => $datos])->output();
        $ruta = sprintf('trabajos/%d/reportes-tecnicos/%d.pdf', $trabajo->id, $reporte->id);
        Storage::disk('r2')->put($ruta, $pdf);

        $reporte->update(['pdf_path' => $ruta]);

        return $reporte;
    }
}

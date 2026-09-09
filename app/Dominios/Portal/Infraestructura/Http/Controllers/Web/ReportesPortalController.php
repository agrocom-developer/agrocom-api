<?php

namespace App\Dominios\Portal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Contratos\LecturaReporteTecnico;
use App\Dominios\Seguridad\Contratos\AutorizacionPortalCliente;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * `GET /portal/reportes` y `GET /portal/reportes/{reporte}/pdf` (HU-41,
 * tarea 55): reportes técnicos del contrato del cliente autenticado, con
 * descarga de PDF. Mismo criterio exacto que {@see ActasPortalController}:
 * `contratoId` resuelto SIEMPRE desde la sesión de portal (invariante 5),
 * `{reporte}` es un id plano buscado DENTRO de la lista ya acotada al
 * contrato propio — sin route-model-binding a `ReporteTecnico` (`Portal` no
 * importa modelos Eloquent de `Operaciones`, ADR 0003 regla 2).
 */
final class ReportesPortalController
{
    public function __construct(private readonly AutorizacionPortalCliente $autorizacion) {}

    public function index(Request $request, LecturaReporteTecnico $lecturaReporte): View
    {
        $contratoId = $this->autorizacion->contratoId($request);

        abort_if($contratoId === null, 404);

        return view('portal::pages.reportes.index', [
            ...$this->autorizacion->cascara($request),
            'reportes' => $lecturaReporte->listarPorContrato($contratoId),
        ]);
    }

    public function pdf(Request $request, int $reporte, LecturaReporteTecnico $lecturaReporte): Response
    {
        $contratoId = $this->autorizacion->contratoId($request);

        abort_if($contratoId === null, 404);

        $reportes = $lecturaReporte->listarPorContrato($contratoId);
        $datosReporte = collect($reportes)->firstWhere('reporteId', $reporte);

        abort_if($datosReporte === null || $datosReporte->pdfPath === null || ! Storage::disk('r2')->exists($datosReporte->pdfPath), 404);

        return response(Storage::disk('r2')->get($datosReporte->pdfPath), 200, ['Content-Type' => 'application/pdf']);
    }
}

<?php

namespace App\Dominios\Portal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Contratos\LecturaActaConformada;
use App\Dominios\Seguridad\Contratos\AutorizacionPortalCliente;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * `GET /portal/actas` y `GET /portal/actas/{acta}/pdf` (HU-41, tarea 55):
 * actas firmadas del contrato del cliente autenticado, con descarga de PDF.
 *
 * `contratoId` se resuelve SIEMPRE desde la sesión de portal vía
 * {@see AutorizacionPortalCliente} (invariante 5 de CLAUDE.md) — el listado
 * ya nace acotado a ese contrato ({@see LecturaActaConformada::listarFirmadasPorContrato()}).
 * `{acta}` en la ruta de descarga es un id plano, sin route-model-binding a
 * `Acta`: `Portal` no importa modelos Eloquent de `Operaciones` (ADR 0003,
 * regla 2) — en vez de eso, se busca el acta pedida DENTRO de la lista ya
 * acotada al contrato propio; si no aparece ahí (no existe, es de otro
 * contrato, o no está firmada) es 404, sin filtrar "todas las actas" después
 * (mismo criterio que TrabajosController::actaPdf, pero sin poder tocar
 * `Storage::disk('r2')` sobre un modelo ajeno).
 */
final class ActasPortalController
{
    public function __construct(private readonly AutorizacionPortalCliente $autorizacion) {}

    public function index(Request $request, LecturaActaConformada $lecturaActa): View
    {
        $contratoId = $this->autorizacion->contratoId($request);

        abort_if($contratoId === null, 404);

        return view('portal::pages.actas.index', [
            ...$this->autorizacion->cascara($request),
            'actas' => $lecturaActa->listarFirmadasPorContrato($contratoId),
        ]);
    }

    public function pdf(Request $request, int $acta, LecturaActaConformada $lecturaActa): Response
    {
        $contratoId = $this->autorizacion->contratoId($request);

        abort_if($contratoId === null, 404);

        $actas = $lecturaActa->listarFirmadasPorContrato($contratoId);
        $datosActa = collect($actas)->firstWhere('actaId', $acta);

        abort_if($datosActa === null || $datosActa->pdfPath === null || ! Storage::disk('r2')->exists($datosActa->pdfPath), 404);

        return response(Storage::disk('r2')->get($datosActa->pdfPath), 200, ['Content-Type' => 'application/pdf']);
    }
}

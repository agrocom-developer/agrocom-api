<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api;

use App\Dominios\Operaciones\Aplicacion\GenerarReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * `GET /api/reportes/lote/{id}` (espec §8 línea 328, reinterpretada sobre el
 * `id` numérico del trabajo — HU-18, tarea 25): descarga el reporte técnico
 * ya generado al firmar el acta. Nunca CREA la fila: si el trabajo todavía
 * no tiene reporte (porque su acta no está firmada), responde 404 — mismo
 * criterio de solo-lectura que `ActaController::pdf()`. Si la fila existe
 * pero el archivo físico no (borrado del bucket), lo reconstruye desde los
 * datos actuales antes de servirlo (ADR 0026, `GenerarReporteTecnico::asegurarPdf`).
 *
 * A diferencia de `ActaController::pdf()` (abierto a cualquier token de
 * dispositivo válido, porque el acta es una prueba que cualquiera en el
 * flujo puede necesitar mostrar), este endpoint SÍ exige el permiso
 * `operaciones.reporte.ver`: la espec (§3, línea 89) restringe "Ver reportes
 * técnicos" a jefe de campo/encargado/dueño (y agrónomo, desde el portal que
 * todavía no existe) — piloto y auxiliar quedan afuera.
 */
#[OA\Tag(
    name: 'Operaciones',
    description: 'Trabajos, sesiones, condiciones, recepción de caldo, evidencias, actas y reportes técnicos.',
)]
final class ReporteTecnicoController
{
    private const PERMISO_VER = 'operaciones.reporte.ver';

    public function __construct(private readonly GenerarReporteTecnico $generarReporte) {}

    #[OA\Get(
        path: '/api/reportes/lote/{id}',
        operationId: 'descargarReporteTecnico',
        description: 'Descarga el reporte técnico de un trabajo, ya generado por la firma de su acta de '
            .'conformidad (HU-17/HU-18) — nunca crea la fila. `404` si el trabajo no existe o todavía no tiene '
            .'reporte (acta sin firmar). Si el archivo físico se perdió, lo reconstruye antes de responder.',
        summary: 'Descarga el reporte técnico de un trabajo',
        security: [['tokenDispositivo' => []]],
        tags: ['Operaciones'],
        parameters: [
            new OA\Parameter(name: 'id', description: 'id del trabajo (`trabajo_id`).', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contenido binario del PDF.', content: new OA\MediaType(mediaType: 'application/pdf')),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
            new OA\Response(response: 403, description: 'El token no tiene el permiso operaciones.reporte.ver.'),
            new OA\Response(response: 404, description: 'El trabajo no existe, o todavía no tiene reporte técnico generado.'),
        ],
    )]
    public function mostrar(Request $request, Trabajo $trabajo, IdentidadOperarioToken $identidad): Response
    {
        abort_unless($identidad->tienePermiso($request, self::PERMISO_VER), Response::HTTP_FORBIDDEN);

        $reporte = $trabajo->reporteTecnico;

        if ($reporte === null || $reporte->pdf_path === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $this->generarReporte->asegurarPdf($reporte);

        return response(Storage::disk('r2')->get($reporte->pdf_path), Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
    }
}

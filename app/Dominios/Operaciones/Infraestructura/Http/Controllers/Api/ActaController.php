<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api;

use App\Dominios\Operaciones\Aplicacion\FirmarActa;
use App\Dominios\Operaciones\Aplicacion\GenerarActaTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\FirmaActaNoDisponible;
use App\Dominios\Operaciones\Dominio\Excepciones\TrabajoNoListoParaActa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\FirmarActaRequest;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\GenerarActaRequest;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * `POST /api/trabajos/{uuid_cliente}/acta`, `POST /api/actas/{uuid_cliente}/firmar`
 * y `GET /api/actas/{uuid_cliente}/pdf` (HU-17, tarea 24). Adaptador delgado
 * (ADR 0008): valida el permiso del token, invoca el caso de uso y traduce
 * el resultado — ninguna regla de negocio vive acá.
 *
 * Permisos (`operaciones.acta.generar`/`operaciones.acta.firmar`) verificados
 * contra la UNIÓN de roles del token (no hay "rol activo" en un dispositivo,
 * ver {@see IdentidadOperarioToken::tienePermiso()}) — primer endpoint de
 * `routes/api.php` que chequea `sec_permission` además de "token válido"
 * (ver runs/24.md).
 */
#[OA\Tag(
    name: 'Operaciones',
    description: 'Trabajos, sesiones, condiciones, recepción de caldo, evidencias y actas de conformidad.',
)]
final class ActaController
{
    private const PERMISO_GENERAR = 'operaciones.acta.generar';

    private const PERMISO_FIRMAR = 'operaciones.acta.firmar';

    public function __construct(
        private readonly GenerarActaTrabajo $generarActa,
        private readonly FirmarActa $firmarActa,
    ) {}

    #[OA\Post(
        path: '/api/trabajos/{uuid_cliente}/acta',
        operationId: 'generarActaTrabajo',
        description: 'Genera el acta de conformidad de un trabajo `cerrado` con todas sus sesiones vigentes '
            .'`validado` (espec §4.3/§17). Idempotente por trabajo, no por `uuid_cliente`: un segundo pedido '
            .'sobre un trabajo que ya tiene acta devuelve la MISMA fila y el MISMO PDF, sin regenerar nada. '
            .'`estado: rechazado` cuando el trabajo no cumple las condiciones — nunca 422 de negocio.',
        summary: 'Genera el acta de conformidad de un trabajo',
        security: [['tokenDispositivo' => []]],
        tags: ['Operaciones'],
        parameters: [
            new OA\Parameter(name: 'uuid_cliente', description: 'uuid_cliente del trabajo (el que trajo su apertura).', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['uuid_cliente'],
                properties: [
                    new OA\Property(property: 'uuid_cliente', description: 'uuid_cliente generado por el dispositivo para el ACTA (no el del trabajo).', type: 'string', example: 'a1b2c3d4-0000-4000-8000-000000000020'),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Acta generada, ya existente, o rechazo con motivo.',
                content: new OA\JsonContent(
                    required: ['estado'],
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', nullable: true),
                        new OA\Property(property: 'uuid_cliente', type: 'string', nullable: true),
                        new OA\Property(property: 'trabajo_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'firmada', 'rechazado']),
                        new OA\Property(property: 'hectareas_conformadas', type: 'string', nullable: true),
                        new OA\Property(property: 'pdf_url', type: 'string', nullable: true),
                        new OA\Property(property: 'motivo', description: 'Presente solo cuando `estado` es `rechazado`.', type: 'string', nullable: true),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
            new OA\Response(response: 403, description: 'El token no tiene el permiso operaciones.acta.generar.'),
            new OA\Response(response: 404, description: 'El trabajo referenciado no existe.'),
        ],
    )]
    public function generar(GenerarActaRequest $request, Trabajo $trabajo, IdentidadOperarioToken $identidad): JsonResponse
    {
        abort_unless($identidad->tienePermiso($request, self::PERMISO_GENERAR), Response::HTTP_FORBIDDEN);

        try {
            $acta = $this->generarActa->ejecutar($trabajo, (string) $request->validated('uuid_cliente'));
        } catch (TrabajoNoListoParaActa $excepcion) {
            return response()->json(['estado' => 'rechazado', 'motivo' => $excepcion->getMessage()]);
        }

        return $this->respuestaActa($acta);
    }

    #[OA\Post(
        path: '/api/actas/{uuid_cliente}/firmar',
        operationId: 'firmarActa',
        description: 'Registra la firma del agrónomo sobre un acta `pendiente`, referenciando una evidencia '
            .'ya subida por `POST /api/evidencias` (tipo `firma_acta`). Reintento con la MISMA evidencia sobre '
            .'un acta ya `firmada` es idempotente; con OTRA evidencia se rechaza.',
        summary: 'Firma un acta de conformidad',
        security: [['tokenDispositivo' => []]],
        tags: ['Operaciones'],
        parameters: [
            new OA\Parameter(name: 'uuid_cliente', description: 'uuid_cliente del acta (el que devolvió su generación).', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['evidencia_firma_uuid_cliente', 'firmante', 'fecha_firma'],
                properties: [
                    new OA\Property(property: 'evidencia_firma_uuid_cliente', type: 'string', example: 'a1b2c3d4-0000-4000-8000-000000000030'),
                    new OA\Property(property: 'firmante', description: 'Nombre del agrónomo — texto libre.', type: 'string', example: 'Ing. Marcela Vargas'),
                    new OA\Property(property: 'fecha_firma', type: 'string', format: 'date-time', example: '2026-09-02T16:30:00-04:00'),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Acta firmada (o ya firmada con la misma evidencia), o rechazo con motivo.',
                content: new OA\JsonContent(
                    required: ['estado'],
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', nullable: true),
                        new OA\Property(property: 'uuid_cliente', type: 'string', nullable: true),
                        new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'firmada', 'rechazado']),
                        new OA\Property(property: 'firmante', type: 'string', nullable: true),
                        new OA\Property(property: 'fecha_firma', type: 'string', nullable: true),
                        new OA\Property(property: 'motivo', description: 'Presente solo cuando `estado` es `rechazado`.', type: 'string', nullable: true),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
            new OA\Response(response: 403, description: 'El token no tiene el permiso operaciones.acta.firmar.'),
            new OA\Response(response: 404, description: 'El acta referenciada no existe.'),
        ],
    )]
    public function firmar(FirmarActaRequest $request, Acta $acta, IdentidadOperarioToken $identidad): JsonResponse
    {
        abort_unless($identidad->tienePermiso($request, self::PERMISO_FIRMAR), Response::HTTP_FORBIDDEN);

        try {
            $acta = $this->firmarActa->ejecutar(
                $acta,
                (string) $request->validated('evidencia_firma_uuid_cliente'),
                (string) $request->validated('firmante'),
                (string) $request->validated('fecha_firma'),
            );
        } catch (FirmaActaNoDisponible $excepcion) {
            return response()->json(['estado' => 'rechazado', 'motivo' => $excepcion->getMessage()]);
        }

        return response()->json([
            'id' => $acta->id,
            'uuid_cliente' => $acta->uuid_cliente,
            'estado' => $acta->estado->value,
            'firmante' => $acta->firmante,
            'fecha_firma' => $acta->fecha_firma?->toIso8601String(),
        ]);
    }

    #[OA\Get(
        path: '/api/actas/{uuid_cliente}/pdf',
        operationId: 'descargarActaPdf',
        description: 'Descarga el PDF del acta, ya generado por `POST /api/trabajos/{uuid_cliente}/acta` — nunca '
            .'lo regenera. Cualquier token de dispositivo válido puede descargarlo (misma exposición que el resto '
            .'de la API de campo); no exige `operaciones.acta.generar`/`firmar`, que son de mutación.',
        summary: 'Descarga el PDF de un acta',
        security: [['tokenDispositivo' => []]],
        tags: ['Operaciones'],
        parameters: [
            new OA\Parameter(name: 'uuid_cliente', description: 'uuid_cliente del acta.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contenido binario del PDF.', content: new OA\MediaType(mediaType: 'application/pdf')),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
            new OA\Response(response: 404, description: 'El acta no existe o todavía no tiene PDF generado.'),
        ],
    )]
    public function pdf(Request $request, Acta $acta): Response
    {
        if ($acta->pdf_path === null || ! Storage::disk('r2')->exists($acta->pdf_path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $contenido = Storage::disk('r2')->get($acta->pdf_path);

        return response($contenido, Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
    }

    private function respuestaActa(Acta $acta): JsonResponse
    {
        return response()->json([
            'id' => $acta->id,
            'uuid_cliente' => $acta->uuid_cliente,
            'trabajo_id' => $acta->trabajo_id,
            'estado' => $acta->estado->value,
            'hectareas_conformadas' => $acta->hectareas_conformadas,
            'pdf_url' => route('api.actas.pdf', $acta->uuid_cliente),
        ]);
    }
}

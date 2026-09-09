<?php

namespace App\Dominios\Distribucion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Distribucion\Aplicacion\ObtenerVersionVigente;
use App\Dominios\Distribucion\Infraestructura\Http\Resources\VersionApkResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * `GET /api/version` (HU-20): lo que `agrocom-field` va a consultar antes de
 * operar, para saber si tiene que actualizarse. Adaptador delgado (ADR
 * 0008) — la resolución de cuál es la vigente vive en {@see
 * ObtenerVersionVigente}.
 */
#[OA\Tag(
    name: 'Distribucion',
    description: 'Versionado y distribución del APK de agrocom-field (HU-20).',
)]
final class VersionController
{
    public function __construct(private readonly ObtenerVersionVigente $obtenerVigente) {}

    #[OA\Get(
        path: '/api/version',
        operationId: 'obtenerVersionVigente',
        description: 'Versión mínima aceptada y versión vigente del APK, con la URL de su release en '
            .'agrocom-field (GitHub Releases) — el binario no se hospeda en este servidor. Sin '
            .'autenticación: la app todavía no tiene ningún token emitido cuando la consulta. Con la '
            .'invariante de negocio "una sola versión autorizada a la vez" (HU-20), hoy `minima` y '
            .'`vigente` son la misma versión — el contrato ya distingue ambas claves para el día que '
            .'una historia futura las separe.',
        summary: 'Versión mínima y vigente del APK',
        tags: ['Distribucion'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ambas claves son `null` si el dueño todavía no autorizó ninguna versión.',
                content: new OA\JsonContent(
                    required: ['minima', 'vigente'],
                    properties: [
                        new OA\Property(property: 'minima', ref: '#/components/schemas/VersionApk', nullable: true),
                        new OA\Property(property: 'vigente', ref: '#/components/schemas/VersionApk', nullable: true),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function show(): JsonResponse
    {
        $vigente = $this->obtenerVigente->ejecutar();

        $recurso = $vigente === null ? null : new VersionApkResource($vigente);

        return response()->json([
            'minima' => $recurso,
            'vigente' => $recurso,
        ]);
    }
}

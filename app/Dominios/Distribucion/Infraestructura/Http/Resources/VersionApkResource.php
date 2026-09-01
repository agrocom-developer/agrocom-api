<?php

namespace App\Dominios\Distribucion\Infraestructura\Http\Resources;

use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Representación pública de una versión del APK (HU-20). `url_descarga` es
 * siempre firmada con expiración, nunca la ruta pública del disco `r2`
 * (ADR 0009) — se firma recién acá, al servir, nunca antes.
 *
 * @mixin VersionApk
 */
#[OA\Schema(
    schema: 'VersionApk',
    title: 'Versión del APK',
    description: 'Versión autorizada del APK de agrocom-field, con su enlace de descarga firmado (HU-20).',
    required: ['version', 'version_code', 'url_descarga'],
    properties: [
        new OA\Property(property: 'version', description: 'Versión SemVer.', type: 'string', example: '1.4.2'),
        new OA\Property(property: 'version_code', description: 'Código de versión de Android.', type: 'integer', example: 14002),
        new OA\Property(
            property: 'url_descarga',
            description: 'Enlace de descarga del `.apk`, firmado con expiración corta — nunca una URL pública.',
            type: 'string',
            format: 'uri',
        ),
    ],
    type: 'object',
)]
final class VersionApkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version,
            'version_code' => $this->version_code,
            'url_descarga' => Storage::disk('r2')->temporaryUrl($this->ruta_apk, now()->addMinutes(5)),
        ];
    }
}

<?php

namespace App\Dominios\Distribucion\Infraestructura\Http\Resources;

use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Representación pública de una versión del APK (HU-20). `url_descarga` es
 * la URL del release publicado en `agrocom-field` (GitHub Releases), tal
 * cual está guardada — el binario no se hospeda en este servidor.
 *
 * @mixin VersionApk
 */
#[OA\Schema(
    schema: 'VersionApk',
    title: 'Versión del APK',
    description: 'Versión autorizada del APK de agrocom-field, con la URL de su release (HU-20).',
    required: ['version', 'version_code', 'url_descarga'],
    properties: [
        new OA\Property(property: 'version', description: 'Versión SemVer.', type: 'string', example: '1.4.2'),
        new OA\Property(property: 'version_code', description: 'Código de versión de Android.', type: 'integer', example: 14002),
        new OA\Property(
            property: 'url_descarga',
            description: 'URL del release del `.apk` publicado en agrocom-field (GitHub Releases).',
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
            'url_descarga' => $this->url_apk,
        ];
    }
}

<?php

namespace App\Dominios\Distribucion\Aplicacion;

use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Sube el binario al disco `r2` (ADR 0009, extensión 1/9/2026:
 * `distribucion/apk/{version}.apk`, nunca el disco `public`) y registra la
 * versión como `pendiente` (HU-20).
 *
 * `estado` no se pasa a `create()` a propósito: el valor inicial lo aplica
 * el default de la columna en la migración, no una transición — la única
 * escritora de `estado` es {@see
 * \App\Dominios\Distribucion\Aplicacion\MaquinaEstados\MaquinaEstadosVersionApk}
 * (invariante 7 de CLAUDE.md), y esta clase vive fuera de esa carpeta.
 */
final class SubirVersionApk
{
    public function ejecutar(string $version, int $versionCode, UploadedFile $archivo): VersionApk
    {
        $ruta = "distribucion/apk/{$version}.apk";

        Storage::disk('r2')->put($ruta, $archivo);

        $nueva = VersionApk::query()->create([
            'version' => $version,
            'version_code' => $versionCode,
            'ruta_apk' => $ruta,
        ]);

        return $nueva->refresh();
    }
}

<?php

namespace App\Dominios\Distribucion\Aplicacion;

use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;

/**
 * Registra una versión del APK de `agrocom-field` (HU-20). El binario vive
 * en ese repo, publicado como release de GitHub — acá solo se guarda su URL
 * y nace `pendiente`; autorizarla es un paso aparte.
 *
 * `estado` no se pasa a `create()` a propósito: el valor inicial lo aplica
 * el default de la columna en la migración, no una transición — la única
 * escritora de `estado` es {@see
 * \App\Dominios\Distribucion\Aplicacion\MaquinaEstados\MaquinaEstadosVersionApk}
 * (invariante 7 de CLAUDE.md), y esta clase vive fuera de esa carpeta.
 */
final class RegistrarVersionApk
{
    public function ejecutar(string $version, int $versionCode, string $urlApk): VersionApk
    {
        $nueva = VersionApk::query()->create([
            'version' => $version,
            'version_code' => $versionCode,
            'url_apk' => $urlApk,
        ]);

        return $nueva->refresh();
    }
}

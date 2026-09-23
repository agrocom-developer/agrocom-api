<?php

namespace App\Dominios\Distribucion\Contratos;

/**
 * Forma primitiva de una versión del APK para el panel (ADR 0003, regla 2;
 * tarea 139): lo justo para nombrarla y llevar a quien mira a la pantalla donde
 * se autoriza. El estado no viaja: cada método de {@see LecturaVersionesApk}
 * ya dice de cuál se trata.
 */
final readonly class VersionApkPanel
{
    public function __construct(
        public int $id,
        public string $version,
        public int $versionCode,
    ) {}
}

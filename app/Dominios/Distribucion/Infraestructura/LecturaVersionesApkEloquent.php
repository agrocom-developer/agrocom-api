<?php

namespace App\Dominios\Distribucion\Infraestructura;

use App\Dominios\Distribucion\Contratos\LecturaVersionesApk;
use App\Dominios\Distribucion\Contratos\VersionApkPanel;
use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaVersionesApk}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito: esa subcarpeta es solo para modelos.
 */
final class LecturaVersionesApkEloquent implements LecturaVersionesApk
{
    public function vigente(): ?VersionApkPanel
    {
        $version = $this->autorizadas()->first();

        return $version === null ? null : $this->aPanel($version);
    }

    public function pendientesDeAutorizar(): array
    {
        $codigoVigente = $this->autorizadas()->value('version_code');

        $pendientes = VersionApk::query()
            ->where('estado', EstadoVersionApk::Pendiente->value)
            ->when($codigoVigente !== null, fn (Builder $consulta) => $consulta->where('version_code', '>', $codigoVigente))
            ->orderByDesc('version_code')
            ->get();

        return array_values($pendientes->map(fn (VersionApk $version): VersionApkPanel => $this->aPanel($version))->all());
    }

    /** @return Builder<VersionApk> */
    private function autorizadas(): Builder
    {
        return VersionApk::query()->where('estado', EstadoVersionApk::Autorizada->value);
    }

    private function aPanel(VersionApk $version): VersionApkPanel
    {
        return new VersionApkPanel($version->id, $version->version, $version->version_code);
    }
}

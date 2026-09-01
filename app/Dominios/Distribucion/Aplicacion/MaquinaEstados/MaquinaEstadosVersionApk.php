<?php

namespace App\Dominios\Distribucion\Aplicacion\MaquinaEstados;

use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use App\Dominios\Distribucion\Dominio\Excepciones\TransicionVersionApkNoPermitida;
use App\Dominios\Distribucion\Dominio\TransicionesVersionApk;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Support\Facades\DB;

/**
 * Única escritora de `dis_versiones_apk.estado` (invariante 7 de CLAUDE.md).
 * Consulta {@see TransicionesVersionApk} antes de cada asignación.
 *
 * Autorizar una versión desautoriza la anterior (si la había) en la MISMA
 * transacción: nunca queda un instante con dos filas `autorizada`, y si
 * desautorizar la anterior fallara, la nueva tampoco queda autorizada — es
 * la invariante de negocio "una sola versión autorizada a la vez"
 * respaldada además por un índice único parcial en la migración.
 */
final class MaquinaEstadosVersionApk
{
    public function autorizar(VersionApk $version): void
    {
        DB::transaction(function () use ($version): void {
            $vigente = VersionApk::query()
                ->where('estado', EstadoVersionApk::Autorizada->value)
                ->where('id', '!=', $version->id)
                ->lockForUpdate()
                ->first();

            if ($vigente !== null) {
                $this->transicionar($vigente, EstadoVersionApk::Pendiente);
            }

            $this->transicionar($version, EstadoVersionApk::Autorizada);
        });
    }

    private function transicionar(VersionApk $version, EstadoVersionApk $hasta): void
    {
        $desde = $version->estado;

        if (! TransicionesVersionApk::permitida($desde, $hasta)) {
            throw TransicionVersionApkNoPermitida::entre($desde, $hasta);
        }

        $version->estado = $hasta;
        $version->save();
    }
}

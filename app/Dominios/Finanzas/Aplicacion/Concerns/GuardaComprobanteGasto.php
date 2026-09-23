<?php

namespace App\Dominios\Finanzas\Aplicacion\Concerns;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda un comprobante en el disco `r2` con su hash SHA-256 (compartido por
 * alta y edición de Gasto, tarea 134) — mismo criterio de integridad que
 * `Operaciones/Aplicacion/RegistrarEvidencia`. En edición, reemplaza la
 * referencia (`comprobante_url`/`comprobante_hash`) sin borrar el archivo
 * anterior de `r2`: no hay caso de uso que limpie comprobantes huérfanos
 * todavía (fuera del alcance de esta tarea).
 */
trait GuardaComprobanteGasto
{
    private function guardarComprobante(Gasto $gasto, UploadedFile $comprobante): void
    {
        $hash = (string) hash_file('sha256', $comprobante->getRealPath());
        $extension = $comprobante->extension() ?: 'bin';
        $momento = Carbon::parse($gasto->fecha);

        $ruta = sprintf(
            'gastos/%s/%s/%d.%s',
            $momento->format('Y'),
            $momento->format('m'),
            $gasto->id,
            $extension,
        );

        Storage::disk('r2')->put($ruta, (string) file_get_contents($comprobante->getRealPath()));

        $gasto->update([
            'comprobante_url' => $ruta,
            'comprobante_hash' => $hash,
        ]);
    }
}

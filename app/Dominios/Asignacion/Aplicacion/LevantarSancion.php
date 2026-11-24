<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Sancion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Un administrador levanta una sanción con motivo: la apelación del proveedor
 * (política de calificación, «Defensa») o la decisión de una revisión de
 * suspensión. Idempotente: levantar una sanción ya levantada no hace nada.
 */
final class LevantarSancion
{
    /** @throws AsignacionInvalida */
    public function ejecutar(int $actorUsuarioId, int $sancionId, string $motivo): Sancion
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw AsignacionInvalida::motivoRequerido();
        }

        return DB::transaction(function () use ($actorUsuarioId, $sancionId, $motivo): Sancion {
            $sancion = Sancion::query()->lockForUpdate()->findOrFail($sancionId);

            if ($sancion->levantada_en === null) {
                $sancion->forceFill([
                    'levantada_en' => Carbon::now(),
                    'levantada_por' => $actorUsuarioId,
                    'levantada_motivo' => $motivo,
                ])->save();
            }

            return $sancion;
        });
    }
}

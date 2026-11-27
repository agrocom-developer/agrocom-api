<?php

namespace App\Dominios\Reclamos\Aplicacion;

use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;
use Illuminate\Support\Facades\DB;

/**
 * Lo que Reclamos aporta a la eliminación de una cuenta (ADR 0047, punto 3): un reclamo abierto o en revisión, como
 * quien lo abrió o como cliente, la impide hasta que se resuelva. No borra nada: los reclamos cerrados se conservan como
 * registro del resultado.
 */
final class EliminacionDeReclamos implements ParticipanteDeEliminacion
{
    public function impedimentos(int $usuarioId): array
    {
        $abierto = DB::table('rcl_reclamos')
            ->whereNull('deleted_at')
            ->whereIn('estado', [EstadoReclamo::Abierta->value, EstadoReclamo::EnRevision->value])
            ->where(static function ($consulta) use ($usuarioId): void {
                $consulta->where('abierta_por_usuario_id', $usuarioId)->orWhere('cliente_usuario_id', $usuarioId);
            })
            ->exists();

        return $abierto ? ['reclamo_abierto'] : [];
    }

    public function alSolicitar(int $usuarioId): void {}

    public function anonimizar(int $usuarioId): void {}
}

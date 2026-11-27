<?php

namespace App\Dominios\Disputas\Aplicacion;

use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;
use Illuminate\Support\Facades\DB;

/**
 * Lo que Disputas aporta a la eliminación de una cuenta (ADR 0047, punto 3): un reclamo abierto o en revisión, como
 * quien lo abrió o como cliente, la impide hasta que se resuelva. No borra nada: los reclamos cerrados se conservan como
 * registro del resultado.
 */
final class EliminacionDeDisputas implements ParticipanteDeEliminacion
{
    public function impedimentos(int $usuarioId): array
    {
        $abierto = DB::table('dsp_disputas')
            ->whereNull('deleted_at')
            ->whereIn('estado', [EstadoDisputa::Abierta->value, EstadoDisputa::EnRevision->value])
            ->where(static function ($consulta) use ($usuarioId): void {
                $consulta->where('abierta_por_usuario_id', $usuarioId)->orWhere('cliente_usuario_id', $usuarioId);
            })
            ->exists();

        return $abierto ? ['reclamo_abierto'] : [];
    }

    public function alSolicitar(int $usuarioId): void {}

    public function anonimizar(int $usuarioId): void {}
}

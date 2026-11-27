<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;
use Illuminate\Support\Facades\DB;

/**
 * Lo que Notificaciones aporta a la eliminación de una cuenta (ADR 0047, punto 6): al ejecutarla se borran, de verdad, los avisos
 * y los tokens de push de la cuenta. No impide nada ni hace nada al pedirla.
 */
final class EliminacionDeNotificaciones implements ParticipanteDeEliminacion
{
    public function impedimentos(int $usuarioId): array
    {
        return [];
    }

    public function alSolicitar(int $usuarioId): void
    {
        DB::table('ntf_dispositivos')->where('usuario_id', $usuarioId)->delete();
    }

    public function anonimizar(int $usuarioId): void
    {
        DB::table('ntf_dispositivos')->where('usuario_id', $usuarioId)->delete();
        DB::table('ntf_notificaciones')->where('usuario_id', $usuarioId)->delete();
    }
}

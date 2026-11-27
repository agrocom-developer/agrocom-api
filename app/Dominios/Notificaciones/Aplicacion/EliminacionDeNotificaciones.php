<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Infraestructura\Eloquent\DispositivoPush;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;

/**
 * Lo que Notificaciones aporta a la eliminación de una cuenta (ADR 0047, punto 6): al pedirla se borran los tokens de push y, al
 * ejecutarla, también los avisos de la cuenta. El borrado es físico (el lógico dejaría el dato): va por la consulta base, porque el
 * modelo de dominio no admite `forceDelete` (ADR 0007). No impide nada.
 */
final class EliminacionDeNotificaciones implements ParticipanteDeEliminacion
{
    public function impedimentos(int $usuarioId): array
    {
        return [];
    }

    public function alSolicitar(int $usuarioId): void
    {
        DispositivoPush::withTrashed()->where('usuario_id', $usuarioId)->toBase()->delete();
    }

    public function anonimizar(int $usuarioId): void
    {
        DispositivoPush::withTrashed()->where('usuario_id', $usuarioId)->toBase()->delete();
        Notificacion::withTrashed()->deUsuario($usuarioId)->toBase()->delete();
    }
}

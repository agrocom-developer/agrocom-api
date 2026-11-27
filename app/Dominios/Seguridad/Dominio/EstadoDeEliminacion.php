<?php

namespace App\Dominios\Seguridad\Dominio;

/** Estado de una solicitud de eliminación de cuenta (ADR 0047). */
enum EstadoDeEliminacion: string
{
    /** Pedida: dentro de la gracia la persona puede cancelarla. */
    case Solicitada = 'solicitada';

    /** Vencida la gracia, algo la impide todavía (un reclamo abierto, por ejemplo): se reintenta cada día. */
    case Pospuesta = 'pospuesta';

    case Cancelada = 'cancelada';

    case Ejecutada = 'ejecutada';

    /** La cuenta está en eliminación: su sesión solo ve el estado, cancela y cierra sesión. */
    public function vigente(): bool
    {
        return $this === self::Solicitada || $this === self::Pospuesta;
    }
}

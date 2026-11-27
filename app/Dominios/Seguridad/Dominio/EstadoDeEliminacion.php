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

    /**
     * Tabla de transiciones permitidas (invariante 7 de CLAUDE.md): una solicitud pasa a `pospuesta` si al vencer la gracia algo
     * la impide, y de ahí a `ejecutada` cuando se libera; la persona puede cancelarla mientras no se ejecute. Lo cancelado y
     * lo ejecutado son terminales.
     *
     * @return list<self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Solicitada => [self::Pospuesta, self::Cancelada, self::Ejecutada],
            self::Pospuesta => [self::Pospuesta, self::Cancelada, self::Ejecutada],
            self::Cancelada, self::Ejecutada => [],
        };
    }

    /** La cuenta está en eliminación: su sesión solo ve el estado, cancela y cierra sesión. */
    public function vigente(): bool
    {
        return $this === self::Solicitada || $this === self::Pospuesta;
    }
}

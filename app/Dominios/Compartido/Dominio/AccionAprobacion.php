<?php

namespace App\Dominios\Compartido\Dominio;

/**
 * Lo que un administrador (o el sistema, por vencimiento de un documento) hace
 * sobre el estado de aprobación de un sujeto. Cada acción lleva a un único
 * estado destino; `Reenviar` es la única que ejecuta el solicitante y no el
 * administrador (el sujeto rechazado corrige y vuelve a la cola).
 */
enum AccionAprobacion: string
{
    case Aprobar = 'aprobar';
    case Rechazar = 'rechazar';
    case Suspender = 'suspender';
    case Rehabilitar = 'rehabilitar';
    case DarDeBaja = 'dar_de_baja';
    case Reenviar = 'reenviar';

    public function estadoDestino(): EstadoAprobacion
    {
        return match ($this) {
            self::Aprobar, self::Rehabilitar => EstadoAprobacion::Aprobado,
            self::Rechazar => EstadoAprobacion::Rechazado,
            self::Suspender => EstadoAprobacion::Suspendido,
            self::DarDeBaja => EstadoAprobacion::Baja,
            self::Reenviar => EstadoAprobacion::Pendiente,
        };
    }

    /**
     * Rechazar, suspender y dar de baja siempre dejan motivo (ADR 0032, punto 5):
     * el sujeto necesita saber qué corregir o cómo apelar.
     */
    public function requiereMotivo(): bool
    {
        return in_array($this, [self::Rechazar, self::Suspender, self::DarDeBaja], true);
    }

    /** `Reenviar` es del solicitante; el resto, del administrador o del sistema. */
    public function esDelSolicitante(): bool
    {
        return $this === self::Reenviar;
    }
}

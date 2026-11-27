<?php

namespace App\Dominios\Solicitudes\Dominio;

/**
 * Estado de una solicitud de servicio (`negociacion-contrato.md` §1): el cliente
 * la abre con un precio por hectárea, los pilotos ofertan, el cliente adjudica y
 * el piloto ejecuta y finaliza. Solo `MaquinaEstadosSolicitud` lo cambia,
 * consultando {@see TransicionesSolicitud}.
 */
enum EstadoSolicitud: string
{
    case Borrador = 'borrador';
    case Abierta = 'abierta';
    case ConOfertas = 'con_ofertas';
    case Adjudicada = 'adjudicada';
    case EnEjecucion = 'en_ejecucion';
    case Finalizada = 'finalizada';
    case Calificada = 'calificada';
    case Cancelada = 'cancelada';
    case EnReclamo = 'en_reclamo';

    /** Sin más pasos posibles (salvo el reclamo, que sale de `finalizada` y `calificada`). */
    public function esTerminal(): bool
    {
        return in_array($this, [self::Calificada, self::Cancelada], true);
    }

    /** Visible en el mapa de los pilotos y abierta a ofertas. */
    public function enMercado(): bool
    {
        return $this === self::Abierta || $this === self::ConOfertas;
    }

    /** Hay un proveedor y un equipo fijados: la app de campo recibe solo estas. */
    public function tieneEquipo(): bool
    {
        return in_array($this, self::conEquipo(), true);
    }

    /**
     * Estados en que un equipo ejecuta o está por ejecutar el servicio.
     *
     * @return list<self>
     */
    public static function conEquipo(): array
    {
        return [self::Adjudicada, self::EnEjecucion];
    }

    /** Estados en que el cliente aún puede cancelar. */
    public function admiteCancelacion(): bool
    {
        return TransicionesSolicitud::permitida($this, self::Cancelada);
    }

    /** Tono del badge del panel (`atoms/badge`). */
    public function tono(): string
    {
        return match ($this) {
            self::Borrador => 'neutral',
            self::Abierta, self::ConOfertas => 'info',
            self::EnReclamo => 'warning',
            self::Adjudicada, self::EnEjecucion, self::Finalizada => 'primary-2',
            self::Calificada => 'success',
            self::Cancelada => 'danger',
        };
    }
}

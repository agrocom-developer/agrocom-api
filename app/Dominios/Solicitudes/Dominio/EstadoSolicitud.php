<?php

namespace App\Dominios\Solicitudes\Dominio;

/**
 * Estado de una solicitud de servicio (especificación del marketplace §5.1,
 * ADR 0027). Solo `MaquinaEstadosSolicitud` lo cambia, consultando
 * {@see TransicionesSolicitud}.
 */
enum EstadoSolicitud: string
{
    case Borrador = 'borrador';
    case Publicada = 'publicada';
    case EnAsignacion = 'en_asignacion';
    case SinProveedor = 'sin_proveedor';
    case Asignada = 'asignada';
    case EnCamino = 'en_camino';
    case EnEjecucion = 'en_ejecucion';
    case SuspendidaPorClima = 'suspendida_por_clima';
    case Ejecutada = 'ejecutada';
    case EnDisputa = 'en_disputa';
    case Completada = 'completada';
    case Cancelada = 'cancelada';

    /** `completada` y `cancelada` son terminales. */
    public function esTerminal(): bool
    {
        return $this === self::Completada || $this === self::Cancelada;
    }

    /**
     * Si un proveedor ya aceptó: hay equipo fijado y el servicio puede llevarse
     * al lote. La app de campo recibe solo las solicitudes en estos estados.
     */
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
        return [self::Asignada, self::EnCamino, self::EnEjecucion, self::SuspendidaPorClima];
    }

    /** Estados en que el cliente aún puede cancelar sin que haya un servicio liquidado. */
    public function admiteCancelacion(): bool
    {
        return TransicionesSolicitud::permitida($this, self::Cancelada);
    }

    /** Tono del badge del panel (`atoms/badge`). */
    public function tono(): string
    {
        return match ($this) {
            self::Borrador => 'neutral',
            self::Publicada, self::EnAsignacion => 'info',
            self::SinProveedor, self::SuspendidaPorClima, self::EnDisputa => 'warning',
            self::Asignada, self::EnCamino, self::EnEjecucion, self::Ejecutada => 'primary-2',
            self::Completada => 'success',
            self::Cancelada => 'danger',
        };
    }
}

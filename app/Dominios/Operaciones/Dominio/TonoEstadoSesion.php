<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Traducción de un estado de sesión al vocabulario visual del panel
 * (success|warning|info|neutral) — el mismo que ya usaban los mocks del
 * dashboard y que leen `dashboard-map.js` y los parciales de gráficos.
 *
 * Vive en `Dominio/` y no en la vista por una razón concreta: el mapa, el
 * donut de distribución, el resumen por lote y la tabla de sesiones pintaban
 * el mismo estado con tres criterios distintos mientras los datos eran
 * mock. Con una sola fuente, un estado nuevo en {@see EstadoSesion} obliga a
 * decidir su tono acá y no en cuatro parciales.
 */
enum TonoEstadoSesion: string
{
    case Success = 'success';
    case Warning = 'warning';
    case Info = 'info';
    case Neutral = 'neutral';

    public static function deSesion(EstadoSesion $estado, bool $anulada = false): self
    {
        if ($anulada) {
            return self::Neutral;
        }

        return match ($estado) {
            EstadoSesion::Validado => self::Success,
            EstadoSesion::Cerrado => self::Warning,
            EstadoSesion::Abierto => self::Info,
        };
    }
}

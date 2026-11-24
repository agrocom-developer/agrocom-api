<?php

namespace App\Dominios\Calificaciones\Dominio;

/**
 * Estado de una calificación (especificación §4.7). Solo `MaquinaEstadosCalificacion`
 * lo cambia. Moderar cambia el estado, nunca el contenido: una `oculta` conserva
 * su registro y deja de contar para el promedio (política de calificación).
 */
enum EstadoCalificacion: string
{
    case Visible = 'visible';
    case EnModeracion = 'en_moderacion';
    case Oculta = 'oculta';

    /**
     * Una calificación reportada sigue contando mientras un administrador decide:
     * si dejara de contar al reportarla, bastaría reportar para subir el promedio.
     */
    public function cuentaParaPromedio(): bool
    {
        return $this !== self::Oculta;
    }

    /** Lo que el proveedor ve de las calificaciones que recibe: todo menos lo ya oculto. */
    public function lasVeElProveedor(): bool
    {
        return $this !== self::Oculta;
    }

    public function tono(): string
    {
        return match ($this) {
            self::Visible => 'success',
            self::EnModeracion => 'warning',
            self::Oculta => 'neutral',
        };
    }
}

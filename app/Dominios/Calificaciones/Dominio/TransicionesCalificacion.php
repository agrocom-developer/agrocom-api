<?php

namespace App\Dominios\Calificaciones\Dominio;

/**
 * Tabla de transiciones permitidas de la calificación (invariante 7 de CLAUDE.md;
 * ADR 0031 y 0039). El proveedor reporta una `visible` (`en_moderacion`); un
 * administrador la oculta o, si el reporte no procede, la deja `visible`; y una
 * `oculta` puede restablecerse con motivo si la moderación fue un error. El
 * contenido nunca cambia. Pura, sin Eloquent ni base.
 */
final class TransicionesCalificacion
{
    /** @var array<string, list<EstadoCalificacion>> */
    private const array PERMITIDAS = [
        'visible' => [EstadoCalificacion::EnModeracion, EstadoCalificacion::Oculta],
        'en_moderacion' => [EstadoCalificacion::Visible, EstadoCalificacion::Oculta],
        'oculta' => [EstadoCalificacion::Visible],
    ];

    public static function permitida(EstadoCalificacion $desde, EstadoCalificacion $hacia): bool
    {
        return in_array($hacia, self::PERMITIDAS[$desde->value], true);
    }

    /** @return list<EstadoCalificacion> */
    public static function destinosDesde(EstadoCalificacion $desde): array
    {
        return self::PERMITIDAS[$desde->value];
    }
}

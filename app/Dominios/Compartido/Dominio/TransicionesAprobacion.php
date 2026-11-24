<?php

namespace App\Dominios\Compartido\Dominio;

/**
 * Tabla de transiciones permitidas del ciclo de aprobación (invariante 7 de
 * CLAUDE.md; especificación del marketplace §5.2): nunca un `estado = ...`
 * suelto en un controlador. Pura, sin Eloquent ni base.
 *
 * `pendiente → aprobado ⇄ suspendido`; `pendiente → rechazado` y el rechazado
 * puede reenviarse (vuelve a `pendiente`); cualquiera → `baja`, que es
 * terminal.
 */
final class TransicionesAprobacion
{
    /** @var array<string, list<EstadoAprobacion>> */
    private const array PERMITIDAS = [
        'pendiente' => [EstadoAprobacion::Aprobado, EstadoAprobacion::Rechazado, EstadoAprobacion::Baja],
        'aprobado' => [EstadoAprobacion::Suspendido, EstadoAprobacion::Baja],
        'suspendido' => [EstadoAprobacion::Aprobado, EstadoAprobacion::Baja],
        'rechazado' => [EstadoAprobacion::Pendiente, EstadoAprobacion::Baja],
        'baja' => [],
    ];

    public static function permitida(EstadoAprobacion $desde, EstadoAprobacion $hacia): bool
    {
        return in_array($hacia, self::PERMITIDAS[$desde->value], true);
    }

    /** Si la acción es válida estando el sujeto en `$desde`. */
    public static function aplicable(EstadoAprobacion $desde, AccionAprobacion $accion): bool
    {
        // «Aprobar» es la decisión sobre un pendiente; «rehabilitar» devuelve un
        // suspendido a operar. Llevan al mismo estado pero no son intercambiables.
        $correcta = match ($accion) {
            AccionAprobacion::Aprobar => $desde === EstadoAprobacion::Pendiente,
            AccionAprobacion::Rehabilitar => $desde === EstadoAprobacion::Suspendido,
            default => true,
        };

        return $correcta && self::permitida($desde, $accion->estadoDestino());
    }

    /**
     * Acciones disponibles para un sujeto en `$desde`, en el orden en que el
     * panel las ofrece.
     *
     * @return list<AccionAprobacion>
     */
    public static function accionesDesde(EstadoAprobacion $desde): array
    {
        return array_values(array_filter(
            AccionAprobacion::cases(),
            static fn (AccionAprobacion $accion): bool => self::aplicable($desde, $accion),
        ));
    }
}

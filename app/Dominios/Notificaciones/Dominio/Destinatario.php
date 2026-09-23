<?php

namespace App\Dominios\Notificaciones\Dominio;

/**
 * A quién le importa un hecho, dicho con el vocabulario de negocio y no con
 * cuentas: quien emite un evento no conoce `sec_user`. La conversión a
 * cuentas es una sola clase (`Aplicacion/ResolverDestinatarios`, ADR 0025
 * punto 3):
 *
 * - `rol`: toda cuenta interna activa con ese rol asignado.
 * - `persona`: la cuenta vinculada a esa `per_personas.id`.
 * - `equipo`: los integrantes vigentes hoy de ese `per_equipos_trabajo.id`.
 */
final readonly class Destinatario
{
    public const string ROL = 'rol';

    public const string PERSONA = 'persona';

    public const string EQUIPO = 'equipo';

    private function __construct(
        public string $tipo,
        public string|int $referencia,
    ) {}

    public static function rol(RolDestinatario $rol): self
    {
        return new self(self::ROL, $rol->value);
    }

    public static function persona(int $personaId): self
    {
        return new self(self::PERSONA, $personaId);
    }

    public static function equipo(int $equipoTrabajoId): self
    {
        return new self(self::EQUIPO, $equipoTrabajoId);
    }
}

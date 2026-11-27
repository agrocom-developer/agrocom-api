<?php

namespace App\Dominios\Notificaciones\Dominio;

/**
 * A quién le importa un hecho, dicho con el vocabulario de negocio y no con
 * cuentas: quien emite un evento no conoce `sec_user`. La conversión a
 * cuentas es una sola clase (`Aplicacion/ResolverDestinatarios`, ADR 0025
 * punto 3):
 *
 * - `rol`: toda cuenta de panel activa con ese rol asignado.
 * - `usuario`: una cuenta concreta.
 */
final readonly class Destinatario
{
    public const string ROL = 'rol';

    public const string USUARIO = 'usuario';

    private function __construct(
        public string $tipo,
        public string|int $referencia,
    ) {}

    public static function rol(RolDestinatario $rol): self
    {
        return new self(self::ROL, $rol->value);
    }

    /**
     * Una cuenta concreta. Es la excepción al «nunca un `usuario_id` suelto» del
     * ADR 0025 (extensión del ADR 0036): en el marketplace quien emite el hecho
     * ya conoce la cuenta que lo solicitó (el titular de un proveedor, el propio
     * piloto) porque esa cuenta es la identidad pública del solicitante.
     */
    public static function usuario(int $usuarioId): self
    {
        return new self(self::USUARIO, $usuarioId);
    }
}

<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * La persona de una cuenta de app, tal como la declara quien se registra (ADR 0046, punto 9):
 * el documento que la identifica y su nombre. {@see RegistroCuentas::crear()} la busca por
 * documento y, si no existe, la crea; si existe, no la sobrescribe.
 */
final readonly class DatosDePersona
{
    public function __construct(
        public string $documentoTipo,
        public string $documentoNumero,
        public string $nombres,
        public string $apellidos,
        public ?string $fechaNacimiento = null,
    ) {}
}

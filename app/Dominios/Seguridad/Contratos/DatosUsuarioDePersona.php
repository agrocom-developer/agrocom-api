<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * Lo que Seguridad sabe de la cuenta de una persona, para el resumen
 * relacionado de su ficha en `Personal` (ADR 0003, regla 2): quién es, si
 * puede ingresar y con cuántos roles. Nunca la contraseña ni los permisos.
 */
final readonly class DatosUsuarioDePersona
{
    public function __construct(
        public int $id,
        public string $username,
        public bool $activo,
        public int $roles,
    ) {}
}

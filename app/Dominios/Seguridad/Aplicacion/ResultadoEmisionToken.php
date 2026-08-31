<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use Illuminate\Support\Collection;

/**
 * Resultado de {@see EmitirTokenDispositivo}. Mismo patrón que
 * {@see ResultadoInicioSesion} en el panel: cuando el usuario tiene más de un
 * rol vivo y no dijo con cuál entra, no se emite nada y el controlador pide
 * la selección — nunca se elige un rol por él ni se cae a la unión de sus
 * roles (invariante 10 de CLAUDE.md).
 *
 * `tokenPlano` es el ÚNICO momento en que el token existe en claro del lado
 * del servidor: en la base solo queda su sha256. Si la app lo pierde, se
 * emite uno nuevo; no hay forma de recuperarlo.
 */
final readonly class ResultadoEmisionToken
{
    /** @param  Collection<int, SecRole>  $rolesDisponibles */
    private function __construct(
        public ?SecTokenDispositivo $token,
        public ?string $tokenPlano,
        public Collection $rolesDisponibles,
        public bool $requiereSeleccionDeRol,
    ) {}

    public static function conToken(SecTokenDispositivo $token, string $tokenPlano): self
    {
        return new self(
            token: $token,
            tokenPlano: $tokenPlano,
            rolesDisponibles: collect(),
            requiereSeleccionDeRol: false,
        );
    }

    /** @param  Collection<int, SecRole>  $rolesDisponibles */
    public static function requiereSeleccionDeRol(Collection $rolesDisponibles): self
    {
        return new self(
            token: null,
            tokenPlano: null,
            rolesDisponibles: $rolesDisponibles,
            requiereSeleccionDeRol: true,
        );
    }
}

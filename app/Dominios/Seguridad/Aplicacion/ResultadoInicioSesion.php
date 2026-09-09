<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use Illuminate\Support\Collection;

/**
 * Resultado de {@see IniciarSesionPanel}: qué hacer con la sesión de panel
 * recién autenticada respecto al rol activo (ADR 0004, extensión 27/8/2026,
 * punto 3). Value object simple, sin lógica propia — el controlador lo usa
 * para dar forma a la respuesta HTTP, nunca decide él mismo si hace falta
 * seleccionar rol.
 */
final readonly class ResultadoInicioSesion
{
    /**
     * @param  Collection<int, SecRole>  $rolesDisponibles  Vacía si el
     *                                                      usuario no tiene ningún rol vivo asignado (ADR 0004, extensión
     *                                                      27/8/2026, punto 3: "cero roles vivos" — sin panel que
     *                                                      mostrar, nunca un fallback a permitir todo).
     */
    private function __construct(
        public ?SecRole $rolActivo,
        public Collection $rolesDisponibles,
        public bool $requiereSeleccion,
    ) {}

    public static function conRolActivo(SecRole $rol): self
    {
        return new self(rolActivo: $rol, rolesDisponibles: collect([$rol]), requiereSeleccion: false);
    }

    /** @param  Collection<int, SecRole>  $rolesDisponibles */
    public static function requiereSeleccion(Collection $rolesDisponibles): self
    {
        return new self(rolActivo: null, rolesDisponibles: $rolesDisponibles, requiereSeleccion: true);
    }
}

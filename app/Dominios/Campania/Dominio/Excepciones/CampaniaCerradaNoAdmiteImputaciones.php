<?php

namespace App\Dominios\Campania\Dominio\Excepciones;

use DomainException;

/**
 * Guarda de imputación (ADR 0015 punto 1): ninguna escritura nueva puede
 * imputarse a una campaña `cerrada` — vale para contratos y gastos (los
 * únicos que la tocan hasta esta tarea; combustible, estadías y equipos
 * llegan con las tareas 72-74). La verifica el módulo dueño de cada tabla al
 * crear (`Comercial\Aplicacion\CrearContrato`, `Finanzas\Aplicacion\CrearGasto`),
 * nunca un trigger.
 */
final class CampaniaCerradaNoAdmiteImputaciones extends DomainException
{
    public static function paraCampania(string $codigo): self
    {
        return new self("La campaña '{$codigo}' está cerrada: no admite nuevas imputaciones.");
    }
}

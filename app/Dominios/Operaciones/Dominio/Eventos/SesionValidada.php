<?php

namespace App\Dominios\Operaciones\Dominio\Eventos;

/**
 * Evento de dominio (ADR 0003, regla 2: "entre módulos se viaja por
 * Contratos/... o por eventos de dominio — SesionValidada → Finanzas genera
 * devengos"). DTO primitivo, nunca un modelo Eloquent, para que el módulo
 * que lo escuche (Finanzas, HU-16) no dependa de `Operaciones` más allá de
 * este `sesionId`.
 *
 * Quien lo dispara es `Aplicacion/MaquinaEstados/MaquinaEstadosSesion::validar()`,
 * después de persistir la transición `cerrado → validado` — nunca antes,
 * para no anunciar una validación que todavía podría fallar. Es idempotente
 * por construcción: esa misma clase no lo vuelve a disparar si la sesión ya
 * estaba `validado` (ver su docblock).
 *
 * Sin oyente real todavía (HU-16, tarea aparte): el listener que genera el
 * devengo real, idempotente por `UNIQUE (sesion_id, persona_id)` sobre una
 * tabla que hoy no existe, es responsabilidad de esa tarea — ver runs/14.md.
 * Laravel no exige que un evento tenga oyente para poder dispararse.
 */
final readonly class SesionValidada
{
    public function __construct(public int $sesionId) {}
}

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
 * Oyente real desde HU-16 (tarea 16): `Finanzas\Infraestructura\FinanzasServiceProvider::boot()`
 * registra el listener que genera el devengo, idempotente por
 * `UNIQUE (sesion_id, persona_id)` sobre `fin_devengos_personal` — ver
 * `Finanzas\Aplicacion\GenerarDevengosSesion` y runs/16.md.
 */
final readonly class SesionValidada
{
    public function __construct(public int $sesionId) {}
}

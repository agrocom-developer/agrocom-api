<?php

namespace App\Dominios\Operaciones\Contratos\Eventos;

/**
 * Evento de dominio (ADR 0003, regla 2: "entre módulos se viaja por
 * Contratos/... o por eventos de dominio"): se anuncia que una sesión quedó
 * validada. DTO primitivo, nunca un modelo Eloquent, para que el módulo que
 * lo escuche no dependa de `Operaciones` más allá de este `sesionId`.
 *
 * Vive en `Contratos/`, no en `Dominio/`: un evento que otro módulo escucha
 * es, por definición, la frontera pública del módulo emisor.
 *
 * Quien lo dispara es `Aplicacion/MaquinaEstados/MaquinaEstadosSesion::validar()`,
 * después de persistir la transición `cerrado → validado` — nunca antes,
 * para no anunciar una validación que todavía podría fallar. Es idempotente
 * por construcción: esa misma clase no lo vuelve a disparar si la sesión ya
 * estaba `validado` (ver su docblock).
 */
final readonly class SesionValidada
{
    public function __construct(public int $sesionId) {}
}

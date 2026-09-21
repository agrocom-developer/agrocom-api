<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Una persona sin `tarifa_ha` configurada terminó de `piloto_id` o
 * `auxiliar_id` de una sesión que se está validando (HU-16, tarea 16).
 *
 * Decisión (documentada en runs/16.md): no se genera un devengo con monto
 * `0`/`null` en silencio (invariante 6 de CLAUDE.md — un monto derivado
 * tiene que poder recalcularse y cuadrar exacto; "sin tarifa" no tiene un
 * monto exacto que calcular). Esta excepción se lanza DENTRO de la
 * transacción de `MaquinaEstadosSesion::validar()`, así que la interrumpe
 * completa: la sesión no queda `validado` a medias sin su devengo. El jefe
 * de campo ve la validación fallar hasta que alguien complete `tarifa_ha` de
 * esa persona en `per_personas` — no hay pantalla propia para eso en esta
 * tarea (fuera de alcance de HU-16).
 */
final class PersonaSinTarifaHa extends RuntimeException
{
    public static function paraPersona(int $personaId): self
    {
        return new self(Texto::de('finanzas.errores.persona_sin_tarifa_ha', ['persona_id' => $personaId]));
    }
}

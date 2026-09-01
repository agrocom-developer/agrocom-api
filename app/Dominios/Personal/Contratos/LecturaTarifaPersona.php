<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Frontera de lectura de Personal hacia otros módulos (ADR 0003, regla 2):
 * `Finanzas` necesita `tarifa_ha` de una persona puntual para calcular un
 * devengo (HU-16, tarea 16), sin importar el modelo Eloquent `PerPersona` ni
 * el resto de sus columnas — un contrato aparte de `LecturaPersonas` (que
 * trae el catálogo completo para el pull de sync) porque este es un lookup
 * puntual por id, no un listado por cursor.
 */
interface LecturaTarifaPersona
{
    /**
     * `tarifa_ha` de la persona, como string decimal (invariante 6 de
     * CLAUDE.md — nunca float). `null` si la persona no existe o si existe
     * pero no tiene `tarifa_ha` configurada: ambos casos son indistinguibles
     * para el consumidor porque en ambos no hay tarifa con la que calcular
     * un devengo — la distinción no le sirve a quien llama.
     */
    public function tarifaHaDe(int $personaId): ?string;
}

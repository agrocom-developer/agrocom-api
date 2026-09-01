<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Frontera de lectura de Personal hacia otros módulos (ADR 0003, regla 2 y
 * ADR 0011, punto 5): el consumidor (`Sincronizacion`) recibe DTOs
 * primitivos, nunca el modelo Eloquent `PerPersona`.
 */
interface LecturaPersonas
{
    /**
     * Personas vigentes (no borradas — el soft delete alcanza, sin estado
     * propio; `activo` no se usa como filtro) modificadas después de la
     * posición del cursor, ordenadas de forma determinística por
     * (`updated_at`, `id`) ascendente.
     *
     * `$cursorActualizadoEn`/`$cursorId` en `null` (ambos, siempre juntos)
     * significa "sin posición": trae desde el principio.
     *
     * @return list<PersonaCatalogo>
     */
    public function listarModificadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite): array;
}

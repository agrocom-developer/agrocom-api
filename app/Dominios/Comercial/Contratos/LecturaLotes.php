<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Frontera de lectura de Comercial hacia otros módulos (ADR 0003, regla 2 y
 * ADR 0011, punto 5): el consumidor (`Sincronizacion`) recibe DTOs
 * primitivos, nunca el modelo Eloquent `Lote`.
 */
interface LecturaLotes
{
    /**
     * Lotes vigentes (no borrados — el soft delete alcanza, sin estado
     * propio) modificados después de la posición del cursor, ordenados de
     * forma determinística por (`updated_at`, `id`) ascendente.
     *
     * `$cursorActualizadoEn`/`$cursorId` en `null` (ambos, siempre juntos)
     * significa "sin posición": trae desde el principio.
     *
     * @return list<LoteCatalogo>
     */
    public function listarModificadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite): array;

    /**
     * Un lote puntual por id (HU-07, tarea 20: `Operaciones` necesita las
     * hectáreas del lote para calcular la cobertura de un trabajo —
     * `Aplicacion/CalcularCoberturaTrabajo.php`). `null` si no existe o está
     * borrado (soft delete).
     */
    public function obtenerPorId(int $id): ?LoteCatalogo;
}

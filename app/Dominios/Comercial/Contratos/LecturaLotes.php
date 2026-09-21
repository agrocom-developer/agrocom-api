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

    /**
     * Estado del terreno de varios lotes a la vez: `limpio`, un grado de
     * obstáculos (`pocos_obstaculos`, `algunos_obstaculos`,
     * `muchos_obstaculos`) o `null` si el lote no lo tiene cargado. Lo usa el
     * alta de Orden de Trabajo de `Operaciones` para repartir las hectáreas
     * entre los equipos según la dificultad de cada lote. Un id que no existe
     * o está borrado no aparece en el resultado.
     *
     * @param  list<int>  $ids
     * @return array<int, string|null> id del lote → limpieza
     */
    public function limpiezaPorIds(array $ids): array;
}

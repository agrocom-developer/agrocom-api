<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla 2
 * y ADR 0011, punto 5): el consumidor (`Sincronizacion`) recibe DTOs
 * primitivos, nunca el modelo Eloquent `OrdenAplicacion`.
 */
interface LecturaOrdenesVigentes
{
    /**
     * Órdenes vigentes (`estado = vigente`, espec §2.1 punto 6) modificadas
     * después de la posición del cursor, ordenadas de forma determinística
     * por (`updated_at`, `id`) ascendente — el desempate por `id` evita
     * repetir o saltear filas que comparten el mismo `updated_at`.
     *
     * `$cursorActualizadoEn`/`$cursorId` en `null` (ambos, siempre juntos)
     * significa "sin posición": trae desde el principio.
     *
     * @return list<OrdenAplicacionCatalogo>
     */
    public function listarModificadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite): array;
}

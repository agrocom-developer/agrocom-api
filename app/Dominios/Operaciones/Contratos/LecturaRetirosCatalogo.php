<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia `Sincronizacion` (ADR 0003, regla
 * 2) para las dos secciones de retirados del catálogo de la app de campo
 * (opción B de la propuesta de #312, decisión del dueño del 1/10/2026). Mismo
 * contrato de cursor que el resto del catálogo: posición (`updated_at`, `id`)
 * por sección, orden determinístico ascendente, `null`/`null` = desde el
 * principio.
 */
interface LecturaRetirosCatalogo
{
    /**
     * Órdenes en `pausada`, `consumida`, `cancelada` o `vencida` modificadas
     * después de la posición. Las `emitida` no cuentan: nunca llegaron al
     * catálogo (solo baja `vigente`), y son las únicas que se dan de baja.
     *
     * @return list<OrdenRetiradaCatalogo>
     */
    public function ordenesRetiradasDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite): array;

    /**
     * Trabajos que el operario tuvo como asignados y ya no debe tener, ver
     * `Dominio\MotivoRetiroTrabajo`.
     *
     * "Los tuvo" se decide sin exponer trabajos ajenos: es de uno de sus
     * equipos vigentes hoy (`$equiposVigentes`), o la bitácora de auditoría
     * muestra que alguna vez estuvo asignado a uno de los equipos en los que
     * la persona fue integrante (`$equiposHistoricos`). Ambas listas las
     * resuelve el consumidor con `Personal\Contratos\LecturaEquipoTrabajo`.
     *
     * @param  list<int>  $equiposVigentes
     * @param  list<int>  $equiposHistoricos
     * @return list<TrabajoRetiradoCatalogo>
     */
    public function trabajosRetiradosDesde(
        ?string $cursorActualizadoEn,
        ?int $cursorId,
        int $limite,
        array $equiposVigentes,
        array $equiposHistoricos,
    ): array;
}

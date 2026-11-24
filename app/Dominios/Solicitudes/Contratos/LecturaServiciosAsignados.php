<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Frontera de lectura de `Solicitudes` hacia `Sincronizacion` (ADR 0003, regla
 * 2): el pull de catálogo recibe DTOs primitivos, nunca el modelo Eloquent. Son
 * los «servicios asignados» que reemplazan a las órdenes de aplicación: las
 * solicitudes aceptadas que el equipo del operario lleva al lote.
 */
interface LecturaServiciosAsignados
{
    /**
     * Solicitudes con equipo fijado y en un estado de ejecución (`asignada`,
     * `en_camino`, `en_ejecucion`, `suspendida_por_clima`) de alguno de
     * `$equipoIds`, modificadas después de la posición del cursor, ordenadas de
     * forma determinística por (`updated_at`, `id`) ascendente.
     * `$cursorActualizadoEn`/`$cursorId` en `null` (siempre juntos) significa
     * «sin posición»: trae desde el principio. Lista de equipos vacía: ninguno.
     *
     * @param  list<int>  $equipoIds
     * @return list<ServicioAsignadoCatalogo>
     */
    public function asignadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite, array $equipoIds): array;

    /**
     * Solicitudes que alguno de `$equipoIds` tuvo y ya no están en ejecución
     * (canceladas, devueltas a asignación o ya ejecutadas), modificadas después
     * de la posición del cursor. Nunca viajan a la vez en `asignadosDesde()`.
     *
     * @param  list<int>  $equipoIds
     * @return list<ServicioRetiradoCatalogo>
     */
    public function retiradosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite, array $equipoIds): array;
}

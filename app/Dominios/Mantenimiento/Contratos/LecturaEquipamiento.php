<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Frontera de lectura de Mantenimiento hacia otros módulos (ADR 0003, regla
 * 2): las baterías que una cuadrilla lleva al campo viven en el catálogo de
 * Mantenimiento (`man_baterias`), y quien las necesita es otro módulo:
 * `Personal` para armar la cuadrilla.
 *
 * `bateriasDisponibles()` devuelve solo lo que se puede asignar hoy (en
 * estado activo, sin dar de baja), ordenado por identificador. `porIds()`
 * resuelve etiquetas de lo YA asignado, esté como esté ahora: una batería
 * que pasó a mantenimiento sigue figurando en la cuadrilla que la tenía.
 */
interface LecturaEquipamiento
{
    public const TIPO_BATERIA = 'bateria';

    /** @return list<RecursoCatalogo> */
    public function bateriasDisponibles(): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, RecursoCatalogo> indexado por id; un id que no existe
     *                                     (o de un tipo desconocido) no figura.
     */
    public function porIds(string $tipo, array $ids): array;

    /**
     * ¿Existe y se puede asignar hoy? Es la guarda que reemplaza a la FK que
     * `per_equipo_recursos.recurso_id` no puede tener.
     */
    public function estaDisponible(string $tipo, int $id): bool;
}

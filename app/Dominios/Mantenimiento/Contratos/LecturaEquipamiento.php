<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Frontera de lectura de Mantenimiento hacia otros módulos (ADR 0003, regla
 * 2): el equipamiento que una cuadrilla lleva al campo —su camioneta, su
 * generador, sus baterías— vive en el catálogo de Mantenimiento
 * (`man_vehiculos`, `man_generadores`, `man_baterias`), y quien lo necesita
 * es otro módulo: `Personal` para armar la cuadrilla y `Operaciones` para
 * anotar con qué vehículo llegó a una hacienda.
 *
 * Los tres `*Disponibles()` devuelven solo lo que se puede asignar hoy (en
 * estado activo, sin dar de baja), ordenado por identificador. `porIds()`
 * resuelve etiquetas de lo YA asignado, esté como esté ahora: un vehículo
 * que pasó a taller sigue figurando en la cuadrilla que lo tenía.
 */
interface LecturaEquipamiento
{
    public const TIPO_VEHICULO = 'vehiculo';

    public const TIPO_GENERADOR = 'generador';

    public const TIPO_BATERIA = 'bateria';

    /** @return list<RecursoCatalogo> */
    public function vehiculosDisponibles(): array;

    /** @return list<RecursoCatalogo> */
    public function generadoresDisponibles(): array;

    /** @return list<RecursoCatalogo> */
    public function bateriasDisponibles(): array;

    /**
     * @param  self::TIPO_*  $tipo
     * @param  list<int>  $ids
     * @return array<int, RecursoCatalogo> indexado por id; un id que no existe
     *                                     (o de un tipo desconocido) no figura.
     */
    public function porIds(string $tipo, array $ids): array;

    /**
     * ¿Existe y se puede asignar hoy? Es la guarda que reemplaza a la FK que
     * `per_equipo_recursos.recurso_id` no puede tener.
     *
     * @param  self::TIPO_*  $tipo
     */
    public function estaDisponible(string $tipo, int $id): bool;
}

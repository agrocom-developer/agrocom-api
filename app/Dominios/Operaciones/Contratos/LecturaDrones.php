<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla 2):
 * el catálogo operativo de drones (`ope_drones`) es de `Operaciones`, y
 * quien necesita elegir uno es `Personal`, al armar el equipamiento de una
 * cuadrilla. Reemplaza al `DB::table('ope_drones')` que `Personal` hacía por
 * su cuenta.
 */
interface LecturaDrones
{
    /** @return list<DronCatalogo> drones que no están dados de baja, por identificador. */
    public function disponibles(): array;

    /**
     * Etiquetas de drones YA asignados, estén como estén ahora.
     *
     * @param  list<int>  $ids
     * @return array<int, DronCatalogo> indexado por id; un id que no existe no figura.
     */
    public function porIds(array $ids): array;

    /**
     * El dron cuyo identificador es exactamente `$identificador`, o `null` si
     * no hay ninguno vivo. Es el cruce de la ficha de inventario de
     * `Mantenimiento` (`man_drones.identificador_dron`), que se correlaciona
     * por el TEXTO del identificador y no por FK.
     */
    public function porIdentificador(string $identificador): ?DronCatalogo;

    /**
     * ¿Existe y no está dado de baja? Es la guarda que reemplaza a la FK que
     * `per_equipo_recursos.recurso_id` no puede tener.
     */
    public function estaDisponible(int $id): bool;
}

<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Frontera de lectura de Mantenimiento hacia el panel (ADR 0003, regla 2;
 * tarea 138): en qué estado está el equipamiento que una cuadrilla lleva al
 * campo. Hermano de {@see LecturaEquipamiento}, que resuelve QUÉ es cada
 * recurso (su identificador) y cuáles se pueden asignar; este resuelve CÓMO
 * está, en lote, para pintarlo junto a una orden o a una cuadrilla.
 *
 * Los dos tipos son los de `per_equipo_recursos.recurso_tipo` que tienen
 * estado: la batería ({@see LecturaEquipamiento::TIPO_BATERIA}) y el dron,
 * que no guarda ninguno — `ope_drones` es de Operaciones — y figura como
 * operativo.
 */
interface LecturaEstadoEquipamiento
{
    public const TIPO_DRON = 'dron';

    /**
     * Estado de los recursos pedidos, sea cual sea: una batería dada de baja
     * sigue figurando, con su estado. `$tipo` es `self::TIPO_DRON` o
     * `LecturaEquipamiento::TIPO_BATERIA`.
     *
     * @param  list<int>  $ids
     * @return array<int, EstadoRecursoPanel> indexado por id; un id que no existe
     *                                        (o de un tipo desconocido) no figura.
     */
    public function porIds(string $tipo, array $ids): array;
}

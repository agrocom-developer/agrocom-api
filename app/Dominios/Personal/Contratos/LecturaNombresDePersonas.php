<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Frontera de lectura de Personal hacia el ABM de cuentas de `Seguridad`
 * (ADR 0003, regla 2): nombres legibles de persona por id, sin que el
 * consumidor lea `per_personas` ni el modelo Eloquent.
 */
interface LecturaNombresDePersonas
{
    /**
     * Personas vivas, ordenadas por nombre, salvo las de `$idsExcluidos`.
     *
     * @param  iterable<int>  $idsExcluidos
     * @return array<int, string> `id de persona => nombre`
     */
    public function vivasExcepto(iterable $idsExcluidos): array;

    /**
     * Nombre de cada persona pedida (incluidas las dadas de baja).
     *
     * @param  list<int>  $ids
     * @return array<int, string> `id de persona => nombre`
     */
    public function nombresDe(array $ids): array;
}

<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Frontera de lectura de Comercial hacia otros módulos (ADR 0003, regla 2):
 * el catálogo de propiedades (`com_propiedades`) es de `Comercial`, y quien
 * necesita elegir una sin importar el modelo Eloquent `Propiedad` es
 * `Operaciones`, en la pantalla de estadías en hacienda (reforma 19/9/2026,
 * alta/edición desde el panel). Reemplaza al `DB::table('com_propiedades')`
 * que `EstadiasHaciendaController` hacía por su cuenta.
 */
interface LecturaPropiedades
{
    /** @return list<PropiedadCatalogo> propiedades no dadas de baja, por nombre. */
    public function disponibles(): array;

    /**
     * Etiquetas de propiedades YA usadas, estén como estén ahora.
     *
     * @param  list<int>  $ids
     * @return array<int, PropiedadCatalogo> indexado por id; un id que no existe no figura.
     */
    public function porIds(array $ids): array;

    /**
     * Ids de propiedades cuyo nombre, o la razón social de su cliente,
     * coincide con `$texto` — para resolver una búsqueda libre sin que el
     * módulo consumidor arme el `WHERE`/`JOIN` sobre tablas ajenas.
     *
     * @return list<int>
     */
    public function idsQueCoinciden(string $texto): array;
}

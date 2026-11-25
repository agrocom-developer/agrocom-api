<?php

namespace App\Dominios\Solicitudes\Contratos;

/** Nombre de las zonas para quien las muestra (la ficha de un proveedor) sin importar `Zona`. */
interface LecturaZonas
{
    public function nombre(int $id): ?string;

    /**
     * Todas las zonas vivas, por nombre, para un selector.
     *
     * @return array<int, string> id => nombre
     */
    public function todas(): array;

    /**
     * Centro de la zona, para medir distancias en la asignación.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function centroide(int $id): ?array;
}

<?php

namespace App\Dominios\Solicitudes\Contratos;

/** Nombre de las zonas para quien las muestra (la ficha de un proveedor) sin importar `Zona`. */
interface LecturaZonas
{
    public function nombre(int $id): ?string;
}

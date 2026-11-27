<?php

namespace App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor;

/**
 * Lo de una solicitud nueva que los filtros del proveedor miran. Un dato que aún no se
 * conoce (`null`) nunca excluye: un filtro sin qué comparar deja pasar.
 */
final readonly class SolicitudParaAviso
{
    public function __construct(
        public string $hectareas,
        public ?string $departamentoCodigo = null,
        public ?string $tipoAplicacion = null,
    ) {}
}

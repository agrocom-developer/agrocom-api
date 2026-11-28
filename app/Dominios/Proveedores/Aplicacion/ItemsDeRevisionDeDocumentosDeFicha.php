<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Proveedores\Contratos\ItemsDeRevisionDeDocumentosDeSujeto;

/** Entrega {@see ItemsDeRevisionDeDocumentos::deFicha()} a otros módulos por su contrato. */
final class ItemsDeRevisionDeDocumentosDeFicha implements ItemsDeRevisionDeDocumentosDeSujeto
{
    public function deFicha(array $datos, bool $puedeRevisar, bool $puedeDescargar): array
    {
        return ItemsDeRevisionDeDocumentos::deFicha($datos, $puedeRevisar, $puedeDescargar);
    }
}

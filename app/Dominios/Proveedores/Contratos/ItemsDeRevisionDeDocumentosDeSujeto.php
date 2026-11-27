<?php

namespace App\Dominios\Proveedores\Contratos;

/** Los documentos de un sujeto como ítems de la cuadrícula de revisión del panel, también para el dron (de `Flota`). */
interface ItemsDeRevisionDeDocumentosDeSujeto
{
    /**
     * @param  list<DatosDocumento>  $datos  los documentos de una ficha.
     * @return list<array<string, mixed>>
     */
    public function deFicha(array $datos, bool $puedeRevisar, bool $puedeDescargar): array;
}

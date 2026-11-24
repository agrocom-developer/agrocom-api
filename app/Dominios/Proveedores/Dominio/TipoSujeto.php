<?php

namespace App\Dominios\Proveedores\Dominio;

/**
 * Sujeto al que pertenece un documento y que pasa por el ciclo de aprobación
 * (especificación §4.3). `dron` pertenece al módulo Flota, pero sus documentos
 * viven en `prv_documentos`.
 */
enum TipoSujeto: string
{
    case Proveedor = 'proveedor';
    case Piloto = 'piloto';
    case Auxiliar = 'auxiliar';
    case Dron = 'dron';
}

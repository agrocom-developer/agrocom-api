<?php

namespace App\Dominios\Reclamos\Dominio;

/** Resultado que fija un administrador (política de reclamos, «Proceso», punto 3). */
enum ResultadoReclamo: string
{
    case AFavorCliente = 'a_favor_cliente';
    case AFavorProveedor = 'a_favor_proveedor';
    case Parcial = 'parcial';
    case SinLugar = 'sin_lugar';
}

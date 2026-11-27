<?php

namespace App\Dominios\Disputas\Dominio;

/** Resultado que fija un administrador (política de disputas, «Proceso», punto 3). */
enum ResultadoDisputa: string
{
    case AFavorCliente = 'a_favor_cliente';
    case AFavorProveedor = 'a_favor_proveedor';
    case Parcial = 'parcial';
    case SinLugar = 'sin_lugar';
}

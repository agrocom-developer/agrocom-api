<?php

namespace App\Dominios\Solicitudes\Dominio;

/** Quién pone el producto o caldo: el cliente (lo entrega) o el proveedor (lo cobra como recargo). */
enum AportaProducto: string
{
    case Cliente = 'cliente';
    case Proveedor = 'proveedor';
}

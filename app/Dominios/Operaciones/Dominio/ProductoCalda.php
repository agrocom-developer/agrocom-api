<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Lo que puede llevar la calda de una Orden de Trabajo (pedido del dueño,
 * 19/9/2026): cuatro casillas, sin cantidades. Es la constancia de QUÉ se
 * aplicó en la tanda — no una formulación: qué lleva, en qué dosis y en qué
 * orden se incorpora lo decide el agrónomo del cliente (§7 de la
 * especificación).
 *
 * Se guarda como lista de estos valores en `ope_ordenes_trabajo.calda_productos`.
 * El orden de los casos es el del formulario.
 */
enum ProductoCalda: string
{
    case Glifosato = 'glifosato';
    case DosCuatroD = 'dos_cuatro_d';
    case Agua = 'agua';
    case Urea = 'urea';
}

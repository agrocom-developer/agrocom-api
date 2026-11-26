<?php

namespace App\Dominios\Calificaciones\Dominio;

/**
 * A quién se califica (especificación §4.7): el cliente califica al proveedor y al
 * piloto; el proveedor califica al cliente, en privado.
 */
enum SujetoCalificacion: string
{
    case Proveedor = 'proveedor';
    case Piloto = 'piloto';
    case Cliente = 'cliente';

    /** Los que reciben una calificación del cliente y cuentan para la reputación del proveedor. */
    public function esDelLadoProveedor(): bool
    {
        return $this !== self::Cliente;
    }

    /** @return list<self> */
    public static function delLadoProveedor(): array
    {
        return [self::Proveedor, self::Piloto];
    }
}

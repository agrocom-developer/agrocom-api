<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Dónde se aloja la cuadrilla durante una estadía en hacienda (pedido del
 * dueño, 19/9/2026): dentro de la propia hacienda, en el pueblo más cercano,
 * o acampando en la propiedad. Catálogo cerrado, columna nullable — ver el
 * docblock de la migración `add_tipo_alojamiento_a_ope_estadias_hacienda_table`
 * para por qué admite `null` (un registro que llega por sync de una app
 * vieja no lo trae todavía).
 */
enum TipoAlojamiento: string
{
    case Hacienda = 'hacienda';
    case Pueblo = 'pueblo';
    case Camping = 'camping';
}

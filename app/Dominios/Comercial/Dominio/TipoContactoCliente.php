<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Tipos de contacto del cliente (insumos §4; CHECK en com_cliente_contactos).
 * El agrónomo emite órdenes y firma actas; el encargado de la propiedad
 * opera (indica lotes, prepara caldo, ordena pausas) pero no firma nada.
 */
enum TipoContactoCliente: string
{
    case Dueno = 'dueno';
    case Agronomo = 'agronomo';
    case EncargadoPropiedad = 'encargado_propiedad';
    case Otro = 'otro';
}

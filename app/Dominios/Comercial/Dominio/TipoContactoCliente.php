<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Tipos de contacto del cliente (insumos §4; CHECK en com_cliente_contactos).
 * El agrónomo emite órdenes y firma actas; el encargado de la propiedad
 * opera (indica lotes, prepara caldo, ordena pausas) pero no firma nada.
 *
 * `GerenteGeneral`/`Finanzas`/`Secretario` (HU-75, tarea 91): contactos de
 * oficina central que hasta acá no tenían dónde clasificarse.
 */
enum TipoContactoCliente: string
{
    case Dueno = 'dueno';
    case Agronomo = 'agronomo';
    case EncargadoPropiedad = 'encargado_propiedad';
    case Otro = 'otro';
    case GerenteGeneral = 'gerente_general';
    case Finanzas = 'finanzas';
    case Secretario = 'secretario';
}

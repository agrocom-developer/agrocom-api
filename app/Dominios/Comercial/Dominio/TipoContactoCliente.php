<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Tipos de contacto del cliente (insumos §4; CHECK en com_cliente_contactos).
 * El agrónomo emite órdenes y firma actas; el encargado de la propiedad
 * opera (indica lotes, prepara caldo, ordena pausas) pero no firma nada.
 *
 * `GerenteGeneral`/`Finanzas`/`Secretario` (HU-75, tarea 91): contactos de
 * oficina central que hasta acá no tenían dónde clasificarse.
 *
 * `Otro` queda deliberadamente último en la declaración (tarea "resumen de
 * cliente"): el orden de los `case` es el orden en que `::cases()` los
 * entrega, y de ahí sale el orden del `<select>` del formulario — un cajón de
 * sastre no debería competir en visibilidad con los tipos concretos. El valor
 * persistido ('otro') no cambia, así que reordenar acá no afecta filas ya
 * guardadas ni el CHECK de la base.
 */
enum TipoContactoCliente: string
{
    case Dueno = 'dueno';
    case Agronomo = 'agronomo';
    case EncargadoPropiedad = 'encargado_propiedad';
    case GerenteGeneral = 'gerente_general';
    case Finanzas = 'finanzas';
    case Secretario = 'secretario';
    case Otro = 'otro';
}

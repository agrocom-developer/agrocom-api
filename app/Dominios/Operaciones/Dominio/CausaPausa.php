<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Catálogo cerrado de causas de pausa (HU-44, tarea 58; DS-01 +
 * `DatosDemoPanel::pausas()`). Sin máquina de estados: es una clasificación
 * fija, no algo que transicione — mismo criterio que {@see TipoIncidencia}.
 *
 * `ImprevistoDelCliente`: mención textual de DS-01 ("insumos del cliente que
 * no llegan"), el hallazgo de campo que motiva toda la HU.
 */
enum CausaPausa: string
{
    case Clima = 'clima';
    case ImprevistoDelCliente = 'imprevisto_del_cliente';
    case CambioLoteCliente = 'cambio_lote_cliente';
    case FallaEquipo = 'falla_equipo';
    case Logistica = 'logistica';
}

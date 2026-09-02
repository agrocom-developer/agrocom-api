<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Catálogo cerrado de la espec (§4.3, fila `evidencias`; TE-07, tarea 19).
 * Sin máquina de estados: es una clasificación fija, no algo que transicione.
 */
enum TipoEvidencia: string
{
    case CapturaRc = 'captura_rc';
    case ImagenCampo = 'imagen_campo';
    case FotoIncidencia = 'foto_incidencia';
    case Comprobante = 'comprobante';
    case FirmaActa = 'firma_acta';
}

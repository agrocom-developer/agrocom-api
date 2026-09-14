<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Catálogo cerrado de la espec (§4.3, fila `evidencias`; TE-07, tarea 19).
 * Sin máquina de estados: es una clasificación fija, no algo que transicione.
 *
 * Los tres últimos casos (HU-80, tarea 86) son la evidencia fotográfica del
 * "Reporte de Equipos" del dueño (13/9/2026): foto de control, foto de cada
 * ciclo de batería y balanceo, foto del dron limpio — mismo mecanismo
 * genérico de `POST /api/evidencias` que ya usan `captura_rc`/`imagen_campo`,
 * solo tipos nuevos.
 */
enum TipoEvidencia: string
{
    case CapturaRc = 'captura_rc';
    case ImagenCampo = 'imagen_campo';
    case FotoIncidencia = 'foto_incidencia';
    case Comprobante = 'comprobante';
    case FirmaActa = 'firma_acta';
    case FotoControl = 'foto_control';
    case FotoCicloBateriaBalanceo = 'foto_ciclo_bateria_balanceo';
    case FotoDronLimpio = 'foto_dron_limpio';
}

<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Estados de una sesión (espec §4.3; TE-05, tarea 09). Mismo recorte que
 * `EstadoTrabajo`: la apertura la trae el sync, el cierre real con hectáreas
 * y `motivo_cierre` llegan con HU-05. Las transiciones permitidas viven en
 * `Dominio/MaquinaEstados/TransicionesSesion.php`; la única clase que escribe
 * este valor es `Aplicacion/MaquinaEstados/MaquinaEstadosSesion.php`
 * (invariante 7 de CLAUDE.md).
 *
 * `Validado` (HU-14, tarea 14): el jefe de campo aprueba una sesión
 * `cerrado` desde el panel — dispara el evento de dominio `SesionValidada`
 * (invariante 3). El RECHAZO de una sesión, en cambio, NO es un estado: por
 * la invariante 2 ("nunca sobrescribe un registro validado... la corrección
 * es un registro nuevo"), una sesión rechazada se queda en `Cerrado` para
 * siempre — se marca `anulada_en` (columna aparte, no `estado`) y la decisión
 * en sí vive como fila nueva en `ope_sesion_rechazos`. Ver runs/14.md.
 */
enum EstadoSesion: string
{
    case Abierto = 'abierto';
    case Cerrado = 'cerrado';
    case Validado = 'validado';
}

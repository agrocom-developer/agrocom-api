<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Estados de una sesión (espec §4.3; TE-05, tarea 09). Mismo recorte que
 * `EstadoTrabajo`: la apertura la trae el sync, el cierre real con hectáreas
 * y `motivo_cierre` llegan con HU-05. Las transiciones permitidas viven en
 * `Dominio/MaquinaEstados/TransicionesSesion.php`; la única clase que escribe
 * este valor es `Aplicacion/MaquinaEstados/MaquinaEstadosSesion.php`
 * (invariante 7 de CLAUDE.md).
 */
enum EstadoSesion: string
{
    case Abierto = 'abierto';
    case Cerrado = 'cerrado';
}

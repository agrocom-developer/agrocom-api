<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Estados de un trabajo (espec §4.3; TE-05, tarea 09). Solo dos por ahora:
 * la apertura la trae el sync (TE-05), el cierre real con hectáreas llega
 * con HU-05 — no hace falta más que esto todavía. Las transiciones
 * permitidas y sus guardas viven en
 * `Dominio/MaquinaEstados/TransicionesTrabajo.php`; la única clase que
 * escribe este valor es `Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo.php`
 * (invariante 7 de CLAUDE.md).
 */
enum EstadoTrabajo: string
{
    case Abierto = 'abierto';
    case Cerrado = 'cerrado';
}

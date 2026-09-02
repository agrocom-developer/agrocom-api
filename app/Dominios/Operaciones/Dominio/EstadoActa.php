<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Estados de un acta de conformidad (espec §4.3, tabla `actas`; HU-17,
 * tarea 24). Dos valores: `pendiente` (generada, sin firma) y `firmada`
 * (con evidencia de firma del agrónomo). Las transiciones permitidas viven
 * en `Dominio/MaquinaEstados/TransicionesActa.php`; la única clase que
 * escribe este valor es `Aplicacion/MaquinaEstados/MaquinaEstadosActa.php`
 * (invariante 7 de CLAUDE.md).
 *
 * No confundir con `EstadoTrabajo`: "conformado" es un estado del ACTA, no
 * del trabajo — el trabajo sigue con `abierto`/`cerrado` (mismo criterio ya
 * fijado en las tareas 20/21, ver el prompt de esta tarea, "Qué NO hacer").
 */
enum EstadoActa: string
{
    case Pendiente = 'pendiente';
    case Firmada = 'firmada';
}

<?php

namespace App\Dominios\Finanzas\Dominio;

/**
 * Estados de una planilla del período (espec Sprint 8 §193; HU-30, tarea
 * 44). Dos valores: `Borrador` (recién generada desde devengos y anticipos,
 * su total puede leerse pero nada la respalda todavía en papel) y
 * `Aprobada` (el dueño la aprobó; cada detalle tiene su recibo en PDF). Las
 * transiciones permitidas viven en
 * `Dominio/MaquinaEstados/TransicionesPlanilla.php`; la única clase que
 * escribe este valor es
 * `Aplicacion/MaquinaEstados/MaquinaEstadosPlanilla.php` (invariante 7 de
 * CLAUDE.md).
 *
 * Sin un tercer estado (`rechazada`, `pagada`, ver "Qué NO hacer" del prompt
 * de esta tarea): el criterio de aceptación es literal `borrador →
 * aprobada`. Un flujo de pago real es una HU futura.
 */
enum EstadoPlanilla: string
{
    case Borrador = 'borrador';
    case Aprobada = 'aprobada';
}

<?php

namespace App\Dominios\Finanzas\Dominio;

/**
 * Estados de una rendición de campo (espec §4.4; HU-34, tarea 48). Tres
 * valores: `Abierta` (recién creada, todavía acepta gastos asociados),
 * `Presentada` (el jefe de campo la mandó a aprobación, con al menos un
 * gasto y su `monto` ya calculado) y `Aprobada` (el encargado la aprobó para
 * reponer el fondo). Las transiciones permitidas viven en
 * `Dominio/MaquinaEstados/TransicionesRendicion.php`; la única clase que
 * escribe este valor es `Aplicacion/MaquinaEstados/MaquinaEstadosRendicion.php`
 * (invariante 7 de CLAUDE.md).
 *
 * Sin vuelta atrás: `abierta → presentada → aprobada`, un único camino, mismo
 * criterio que `EstadoPlanilla`.
 */
enum EstadoRendicion: string
{
    case Abierta = 'abierta';
    case Presentada = 'presentada';
    case Aprobada = 'aprobada';
}

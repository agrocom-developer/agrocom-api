<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Ciclo de vida de una alerta de la bandeja (espec CA de HU-19: "estado
 * atendida/pendiente"). Transición única, un solo sentido — sin tabla de
 * transiciones/guardas propia (a diferencia de `EstadoTrabajo`/`EstadoSesion`):
 * la idempotencia de "atender una alerta ya atendida" la resuelve
 * `Aplicacion/AtenderAlerta.php` con un `return` temprano, mismo criterio que
 * `MaquinaEstadosSesion::validar()` usa para su propia idempotencia.
 */
enum EstadoAlerta: string
{
    case Pendiente = 'pendiente';
    case Atendida = 'atendida';
}

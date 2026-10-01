<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Por qué un trabajo que el dispositivo tenía como asignado deja de estarlo
 * (sección `trabajos_retirados` de `GET /api/sync/catalogo`, opción B de la
 * propuesta de #312, decisión del dueño del 1/10/2026). Catálogo cerrado:
 * la app de campo lo espeja tal cual.
 *
 * Si aplican varios a la vez, gana el primero en este orden (el más
 * definitivo): un trabajo dado de baja ya no existe; uno reasignado ya no es
 * del operario, esté como esté; uno cerrado terminó; y uno abierto cuya orden
 * se cerró ya no se puede ejecutar.
 */
enum MotivoRetiroTrabajo: string
{
    /** Baja lógica desde el panel (`EliminarTrabajo`, soft delete). */
    case DadoDeBaja = 'dado_de_baja';

    /**
     * Su equipo ya no es uno en el que la persona del token esté vigente hoy
     * (otro equipo, o sin equipo).
     */
    case Reasignado = 'reasignado';

    /** `ope_trabajos.estado = cerrado` (por el sync o por el panel). */
    case Cerrado = 'cerrado';

    /**
     * Su orden de aplicación pasó a un estado cerrado (`consumida`,
     * `cancelada` o `vencida`, ADR 0022) con el trabajo todavía abierto.
     */
    case OrdenCerrada = 'orden_cerrada';
}

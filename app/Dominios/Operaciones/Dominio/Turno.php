<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Turno de trabajo de un equipo dentro de una tanda (Orden de Trabajo, tarea
 * de la reforma 18/9/2026): mañana, noche o todo el día — catálogo cerrado,
 * pedido explícito del dueño. Cada `Trabajo` (equipo×lote) lo carga junto con
 * `turno_hora_inicio`/`turno_hora_fin`, siempre los tres juntos.
 */
enum Turno: string
{
    case Manana = 'manana';
    case Noche = 'noche';
    case TodoElDia = 'todo_el_dia';
}

<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Cobertura de hectáreas de un trabajo frente al lote (espec §5, HU-07,
 * tarea 20) — PROYECCIÓN DE LECTURA, mismo criterio que `EstadoTableroTrabajo`
 * (tarea 15): NO es un estado real de `Trabajo` (ese sigue siendo
 * `EstadoTrabajo`, con dos valores; invariante 7 — solo lo escribe
 * `Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo.php`). Se recalcula en
 * cada consulta desde `sesiones` + hectáreas del lote (`Comercial\Contratos\LecturaLotes`)
 * + tolerancia configurable (`config('operaciones.tolerancia_solape_hectareas')`)
 * — nunca se persiste. Ver `Aplicacion/CalcularCoberturaTrabajo.php` y
 * runs/20.md para el porqué de esta decisión de diseño (camino 2 del prompt,
 * no el diagrama de ocho estados de la espec §5 original).
 *
 * `null` (fuera de este enum, es el tipo de retorno de
 * `CalcularCoberturaTrabajo::ejecutar()`) es un cuarto caso implícito: "en
 * curso, sin alerta" — ni completó las hectáreas del lote ni tiene ninguna
 * sesión cerrada con motivo distinto de `completado`. No se modela como un
 * cuarto valor de este enum para no inventar un estado que la espec no
 * nombra (el diagrama original lo llamaría `en_ejecucion`, que esta tarea
 * tiene explícitamente prohibido reabrir).
 */
enum EstadoCoberturaTrabajo: string
{
    /** Suma de hectáreas de sesiones vigentes ≥ hectáreas del lote, dentro de tolerancia. */
    case Completo = 'completo';

    /** Alguna sesión vigente cerró con motivo distinto de `completado` y el lote no está cubierto. */
    case Parcial = 'parcial';

    /** Suma de hectáreas de sesiones vigentes > hectáreas del lote + tolerancia configurada. */
    case Observado = 'observado';
}

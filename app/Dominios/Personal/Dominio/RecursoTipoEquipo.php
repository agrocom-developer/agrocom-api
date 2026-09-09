<?php

namespace App\Dominios\Personal\Dominio;

/**
 * Tipo de equipamiento asignable a un equipo de trabajo (tarea 72, HU-49;
 * `per_equipo_recursos.recurso_tipo`, CHECK en la migración). Cada valor
 * apunta a una tabla distinta, en otro módulo — `Dron` a `ope_drones`
 * (Operaciones), `Vehiculo` a `man_vehiculos` y `Generador` a
 * `man_generadores` (Mantenimiento) — resuelta a mano por el caso de uso que
 * asigna, nunca por una FK (ver el docblock de la migración
 * `2026_09_08_500003_create_per_equipo_recursos_table.php`).
 */
enum RecursoTipoEquipo: string
{
    case Dron = 'dron';
    case Vehiculo = 'vehiculo';
    case Generador = 'generador';
}

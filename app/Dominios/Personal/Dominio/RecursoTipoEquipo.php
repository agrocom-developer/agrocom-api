<?php

namespace App\Dominios\Personal\Dominio;

/**
 * Tipo de equipamiento asignable a un equipo de trabajo (tarea 72, HU-49;
 * `per_equipo_recursos.recurso_tipo`, CHECK en la migración). Cada valor
 * apunta a una tabla distinta, en otro módulo — `Dron` a `ope_drones`
 * (Operaciones), `Vehiculo`, `Generador` y `Bateria` a `man_vehiculos`,
 * `man_generadores` y `man_baterias` (Mantenimiento) — resuelta a mano por el
 * caso de uso que asigna, nunca por una FK (ver el docblock de la migración
 * `2026_09_08_500003_create_per_equipo_recursos_table.php`).
 *
 * `Bateria` (tarea "cuadrillas-estadias", 19/9/2026, CHECK ampliado en
 * `add_bateria_a_per_equipo_recursos_tipo_chk`): cada batería es una unidad
 * identificada del catálogo, igual que un vehículo o un generador — la
 * "cantidad de baterías" de una cuadrilla que pide el dueño es el CONTEO de
 * filas vigentes con este tipo, no un número suelto sin origen.
 */
enum RecursoTipoEquipo: string
{
    case Dron = 'dron';
    case Vehiculo = 'vehiculo';
    case Generador = 'generador';
    case Bateria = 'bateria';
}

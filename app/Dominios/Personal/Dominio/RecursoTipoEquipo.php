<?php

namespace App\Dominios\Personal\Dominio;

/**
 * Tipo de equipamiento asignable a un equipo de trabajo (tarea 72, HU-49;
 * `per_equipo_recursos.recurso_tipo`, CHECK en la migración). Cada valor
 * apunta a una tabla distinta, en otro módulo — `Dron` a `ope_drones`
 * (Operaciones) y `Bateria` a `man_baterias` (Mantenimiento) — resuelta a mano
 * por el caso de uso que asigna, nunca por una FK.
 *
 * `Bateria`: cada batería es una unidad identificada del catálogo; la
 * "cantidad de baterías" de una cuadrilla es el CONTEO de filas vigentes con
 * este tipo, no un número suelto sin origen.
 */
enum RecursoTipoEquipo: string
{
    case Dron = 'dron';
    case Bateria = 'bateria';
}

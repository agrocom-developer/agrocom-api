<?php

namespace App\Dominios\Personal\Dominio;

/**
 * Rol de una persona DENTRO de un equipo de trabajo (tarea 72, HU-49;
 * `per_equipo_integrantes.rol_equipo`, CHECK en la migración). Distinto de
 * `RolOperativoPersona` (la clasificación general de la persona, que admite
 * más valores): una persona con rol operativo `jefe_campo` puede figurar acá
 * como `Auxiliar` de una cuadrilla puntual — son dos preguntas distintas.
 */
enum RolEquipo: string
{
    case Piloto = 'piloto';
    case Auxiliar = 'auxiliar';
}

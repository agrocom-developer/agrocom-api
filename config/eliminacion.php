<?php

/*
 * Eliminación de cuenta (ADR 0047, punto 5): la persona tiene `gracia_dias` para arrepentirse; la tarea diaria la ejecuta
 * después. Si algo la impide (un servicio adjudicado, un reclamo abierto) queda `pospuesta`; pasado `plazo_dias` desde la
 * solicitud sin poder ejecutarse, se avisa a la oficina.
 */
return [
    'gracia_dias' => (int) env('ELIMINACION_GRACIA_DIAS', 7),
    'plazo_dias' => (int) env('ELIMINACION_PLAZO_DIAS', 30),
];

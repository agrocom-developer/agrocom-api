<?php

/*
 * Copy de vocabulario del dominio Mantenimiento (HU-39, tarea 51: catálogo
 * de baterías; HU-82, tarea 97: ficha técnica del dron). Mismo criterio que
 * lang/es/operaciones.php: las claves de estado nunca se hardcodean en la
 * vista, se resuelven acá contra el valor crudo que viaja como dato (ADR
 * 0013).
 */

return [

    // Estado descriptivo de una batería (EstadoBateria): compartido por el
    // filtro, el badge del listado y el select del formulario.
    'estado_bateria' => [
        'activa' => 'Activa',
        'retirada' => 'Retirada',
        'mantenimiento' => 'En mantenimiento',
    ],

    // Estado de un dron. No tiene uno propio: figura siempre operativo.
    'estado_dron' => [
        'operativo' => 'Operativo',
    ],

    // Mensajes de error.
    'errores' => [
        'bateria_duplicada' => "Ya existe una batería activa con el identificador ':identificador'.",
        'correccion_ciclos_no_autorizada' => "La batería ':identificador' tiene :actual ciclos acumulados; bajar a :propuesto requiere un motivo de corrección.",
        'ficha_dron_duplicada' => "Ya existe una ficha activa para el dron con identificador ':identificador_dron'.",
    ],

];

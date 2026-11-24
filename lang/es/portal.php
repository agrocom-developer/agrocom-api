<?php

/*
 * Portal del cliente (guard `cliente`, ADR 0004): por ahora solo el chrome y el
 * perfil de la cuenta. Las pantallas del cliente sobre sus solicitudes
 * llegan con su propia tarea; mientras tanto el cliente opera desde la app
 * (ADR 0027).
 */
return [

    'chrome' => [
        'titulo' => 'Portal del cliente',
        'nav_aria_label' => 'Navegación del portal',
        'nav_perfil' => 'Mi perfil',
        'cerrar_sesion' => 'Cerrar sesión',
    ],

];

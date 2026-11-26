<?php

/*
 * Nombre de las páginas del panel que NO pertenecen a ningún ítem del menú
 * (búsqueda, perfil, Mis notificaciones…). Cuando el usuario está en una de
 * ellas, el módulo Inicio queda activo y su panel muestra un solo ítem, sin
 * enlace, con este nombre (templates/panel-layout).
 *
 * La clave es `rutas.<nombre de la ruta de Laravel>`: una entrada por página,
 * resuelta solo para la página visible. Las páginas de detalle de un ítem del
 * menú (la ficha de una solicitud, de un cliente…) NO van acá: resaltan el
 * ítem de su listado. `PaginasFueraDelMenuTest` falla si una ruta GET del panel
 * queda fuera del menú sin su nombre en este archivo.
 */

return [

    'rutas' => [
        'panel' => [
            'buscar' => 'Búsqueda',
            'notificaciones' => [
                'index' => 'Mis notificaciones',
            ],
            'perfil' => [
                'edit' => 'Mi perfil',
            ],
            // Zonas salió del menú; la pantalla sigue por su ruta mientras no se retire.
            'zonas' => [
                'index' => 'Zonas',
                'create' => 'Nueva zona',
                'edit' => 'Editar zona',
            ],
        ],
    ],

];

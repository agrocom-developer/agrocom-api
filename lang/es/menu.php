<?php

/*
 * Etiquetas y descripciones del menú de tres niveles del panel (quinta
 * vuelta — maquetas aprobadas 4a/5a/5b/5c): módulos (nivel 1, riel) con sus
 * ítems (nivel 2, sidebar). `sec_menu.label`/`sec_menu.descripcion` guardan
 * estas claves tal cual (ver Database\Seeders\Catalogo\SecMenuSeeder) y se
 * resuelven vía __() recién en presentación (ADR 0013).
 *
 * Los módulos y su vocabulario salen de la ESPECIFICACIÓN
 * (`docs/especificacion/marketplace.md`): Mercado, Proveedores, Catálogos y
 * Seguridad.
 *
 * Archivo propio (no `seguridad.php`): el menú es transversal a todos los
 * módulos de dominio, aunque el catálogo `sec_menu` viva en Seguridad.
 */

return [

    // Marketplace (ADR 0027): la solicitud de servicio es la unidad de negocio.
    'solicitudes' => [
        'label' => 'Mercado',
        'descripcion' => 'Solicitudes con sus ofertas, clientes, calificaciones, disputas y métricas.',
        'items' => [
            'tablero' => 'Tablero',
            'solicitudes' => 'Solicitudes',
            'clientes' => 'Clientes',
            'calificaciones' => 'Calificaciones',
            'disputas' => 'Disputas',
            'metricas' => 'Métricas del mercado',
        ],
    ],

    // Marketplace (ADR 0028, 0029, 0032): aprobación de quienes prestan el servicio.
    'proveedores' => [
        'label' => 'Proveedores',
        'descripcion' => 'Aprobación de proveedores, pilotos, auxiliares, drones y documentos de verificación.',
        'items' => [
            'proveedores' => 'Proveedores',
            'pilotos' => 'Pilotos',
            'auxiliares' => 'Auxiliares',
            'drones_flota' => 'Drones',
            'equipos_flota' => 'Equipos',
            'documentos' => 'Documentos',
        ],
    ],

    'catalogos' => [
        'label' => 'Catálogos',
        'descripcion' => 'Las zonas de Santa Cruz que agrupan el mapa y el tablero.',
        'items' => [
            'zonas' => 'Zonas',
        ],
    ],

    'seguridad' => [
        'label' => 'Seguridad',
        'descripcion' => 'Usuarios, roles y organización del sistema.',
        'items' => [
            'usuarios' => 'Usuarios',
            'roles' => 'Roles y permisos',
            'dispositivos' => 'Dispositivos',
            'organizacion' => 'Organización',
            'versiones_apk' => 'Versiones de las apps',
            'configuracion' => 'Configuración del sistema',
            'bitacora' => 'Bitácora',
        ],
    ],

];

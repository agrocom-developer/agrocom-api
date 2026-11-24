<?php

/*
 * Etiquetas y descripciones del menú de tres niveles del panel: módulos
 * (nivel 1, riel) con sus ítems (nivel 2, sidebar). `sec_menu.label` y
 * `sec_menu.descripcion` guardan estas claves tal cual (ver
 * Database\Seeders\Catalogo\SecMenuSeeder) y se resuelven vía __() recién en
 * presentación (ADR 0013).
 *
 * Archivo propio (no `seguridad.php`): el menú es transversal a todos los
 * módulos de dominio, aunque el catálogo `sec_menu` viva en Seguridad.
 */

return [

    'operacion' => [
        'label' => 'Torre de control',
        'descripcion' => 'El estado del mercado: solicitudes, disputas, calificaciones y métricas.',
        'items' => [
            'tablero' => 'Tablero',
            'solicitudes' => 'Solicitudes',
            'disputas' => 'Disputas',
            'calificaciones' => 'Calificaciones',
            'metricas' => 'Métricas del mercado',
        ],
    ],

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

    'directorio' => [
        'label' => 'Clientes y zonas',
        'descripcion' => 'Clientes del marketplace, zonas de Santa Cruz y avisos del sistema.',
        'items' => [
            'clientes' => 'Clientes',
            'zonas' => 'Zonas',
            'avisos' => 'Avisos',
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
            'versiones_apk' => 'Versiones del APK',
            'configuracion' => 'Configuración del sistema',
            'bitacora' => 'Bitácora',
        ],
    ],

];

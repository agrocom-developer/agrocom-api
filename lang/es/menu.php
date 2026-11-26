<?php

/*
 * Etiquetas y descripciones del menú de tres niveles del panel (quinta
 * vuelta — maquetas aprobadas 4a/5a/5b/5c): módulos (nivel 1, riel) con sus
 * ítems (nivel 2, sidebar). `sec_menu.label`/`sec_menu.descripcion` guardan
 * estas claves tal cual (ver Database\Seeders\Catalogo\SecMenuSeeder) y se
 * resuelven vía __() recién en presentación (ADR 0013).
 *
 * Los módulos y su vocabulario salen de la ESPECIFICACIÓN
 * (`docs/especificacion/marketplace.md`): Mercado, Proveedores, Soporte,
 * Seguridad y Sistema.
 *
 * Archivo propio (no `seguridad.php`): el menú es transversal a todos los
 * módulos de dominio, aunque el catálogo `sec_menu` viva en Seguridad.
 */

return [

    // Marketplace (ADR 0027): la solicitud de servicio es la unidad de negocio.
    'solicitudes' => [
        'label' => 'Mercado',
        'descripcion' => 'El tablero, las solicitudes con sus ofertas, los clientes, las calificaciones y las métricas.',
        'items' => [
            'tablero' => 'Tablero',
            'solicitudes' => 'Solicitudes',
            'clientes' => 'Clientes',
            'calificaciones' => 'Calificaciones',
            'metricas' => 'Métricas del mercado',
        ],
    ],

    // Marketplace (ADR 0028, 0029, 0032): aprobación de quienes prestan el servicio.
    'proveedores' => [
        'label' => 'Proveedores',
        'descripcion' => 'Aprobación de proveedores, pilotos, drones y documentos de verificación.',
        'items' => [
            'documentos' => 'Documentos',
            'proveedores' => 'Proveedores',
            'pilotos' => 'Pilotos',
            'drones_flota' => 'Drones',
        ],
    ],

    // Atención a quienes usan la plataforma: reclamos y, más adelante, consultas.
    // Es un módulo propio porque dar soporte no es operar el mercado.
    'soporte' => [
        'label' => 'Soporte',
        'descripcion' => 'Reclamos de clientes y proveedores sobre servicios ya prestados.',
        'items' => [
            'disputas' => 'Disputas',
        ],
    ],

    'seguridad' => [
        'label' => 'Seguridad',
        'descripcion' => 'Usuarios, roles, dispositivos y bitácora.',
        'items' => [
            'usuarios' => 'Usuarios',
            'roles' => 'Roles y permisos',
            'dispositivos' => 'Dispositivos',
            'bitacora' => 'Bitácora',
        ],
    ],

    // Ajustes de la plataforma que se tocan poco, y los avisos del sistema,
    // que son una herramienta de auditoría y no de gestión del mercado.
    'sistema' => [
        'label' => 'Sistema',
        'descripcion' => 'Zonas, organización, configuración, versiones de las apps y avisos del sistema.',
        'items' => [
            'zonas' => 'Zonas',
            'organizacion' => 'Organización',
            'configuracion' => 'Configuración del sistema',
            'versiones_apk' => 'Versiones de las apps',
            'avisos' => 'Avisos del sistema',
        ],
    ],

];

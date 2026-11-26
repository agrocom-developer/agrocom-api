<?php

/*
 * Etiquetas y descripciones del menú de tres niveles del panel (quinta
 * vuelta — maquetas aprobadas 4a/5a/5b/5c): módulos (nivel 1, riel) con sus
 * ítems (nivel 2, sidebar). `sec_menu.label`/`sec_menu.descripcion` guardan
 * estas claves tal cual (ver Database\Seeders\Catalogo\SecMenuSeeder) y se
 * resuelven vía __() recién en presentación (ADR 0013).
 *
 * Los módulos y su vocabulario salen de la ESPECIFICACIÓN
 * (`docs/especificacion/marketplace.md`): Mercado, Proveedores, Atención,
 * Seguridad y Sistema, más el acceso directo a Inicio.
 *
 * Archivo propio (no `seguridad.php`): el menú es transversal a todos los
 * módulos de dominio, aunque el catálogo `sec_menu` viva en Seguridad.
 */

return [

    // Acceso directo del riel al tablero: no es un módulo con ítems.
    'inicio' => [
        'label' => 'Inicio',
        'descripcion' => 'El tablero del mercado: lo que pasa hoy y lo que espera una decisión.',
    ],

    // Marketplace (ADR 0027): la solicitud de servicio es la unidad de negocio.
    'solicitudes' => [
        'label' => 'Mercado',
        'descripcion' => 'Las solicitudes con sus ofertas y las métricas del mercado.',
        'items' => [
            'solicitudes' => 'Solicitudes',
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

    // La relación con quienes usan la plataforma: quiénes son, qué opinan de
    // un servicio y qué reclaman.
    'atencion' => [
        'label' => 'Atención',
        'descripcion' => 'Clientes, calificaciones y reclamos de quienes usan la plataforma.',
        'items' => [
            'clientes' => 'Clientes',
            'calificaciones' => 'Calificaciones',
            'reclamos' => 'Reclamos',
        ],
    ],

    'seguridad' => [
        'label' => 'Seguridad',
        'descripcion' => 'Usuarios, permisos y bitácora.',
        'items' => [
            'usuarios' => 'Usuarios',
            'roles' => 'Permisos',
            'bitacora' => 'Bitácora',
        ],
    ],

    // Ajustes de la plataforma que se tocan poco, y los avisos del sistema,
    // que son una herramienta de auditoría y no de gestión del mercado.
    'sistema' => [
        'label' => 'Sistema',
        'descripcion' => 'Zonas, organización, configuración y avisos del sistema.',
        'items' => [
            'zonas' => 'Zonas',
            'organizacion' => 'Organización',
            'configuracion' => 'Configuración del sistema',
            'avisos' => 'Avisos del sistema',
        ],
    ],

];

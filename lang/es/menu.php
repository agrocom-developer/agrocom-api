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
        'descripcion' => 'Las métricas del mercado, las solicitudes con sus ofertas y las calificaciones.',
        'items' => [
            'metricas' => 'Métricas del mercado',
            'solicitudes' => 'Solicitudes de fumigación',
            'calificaciones' => 'Calificaciones de servicios',
        ],
    ],

    // Marketplace (ADR 0028, 0029, 0032): aprobación de quienes prestan el servicio.
    'proveedores' => [
        'label' => 'Proveedores',
        'descripcion' => 'Aprobación de proveedores, pilotos, drones y documentos de verificación.',
        'items' => [
            'proveedores' => 'Cuentas de proveedores',
            'pilotos' => 'Pilotos registrados',
            'drones_flota' => 'Drones registrados',
            'documentos' => 'Documentos y vigencias',
        ],
    ],

    // La relación con quienes usan la plataforma: quiénes son y qué reclaman.
    'atencion' => [
        'label' => 'Atención',
        'descripcion' => 'Clientes y reclamos de quienes usan la plataforma.',
        'items' => [
            'clientes' => 'Clientes registrados',
            'reclamos' => 'Reclamos de servicios',
        ],
    ],

    'seguridad' => [
        'label' => 'Seguridad',
        'descripcion' => 'Usuarios, permisos y bitácora.',
        'items' => [
            'usuarios' => 'Usuarios del panel',
            'roles' => 'Permisos del panel',
            'bitacora' => 'Bitácora de actividad',
        ],
    ],

    // Ajustes de la plataforma que se tocan poco, y los avisos del sistema,
    // que son una herramienta de auditoría y no de gestión del mercado.
    'sistema' => [
        'label' => 'Sistema',
        'descripcion' => 'Organización, avisos y configuración del sistema.',
        'items' => [
            'organizacion' => 'Datos de la organización',
            'avisos' => 'Avisos del sistema',
            'configuracion' => 'Configuración general',
        ],
    ],

];

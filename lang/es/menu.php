<?php

/*
 * Etiquetas y descripciones del menú de tres niveles del panel (quinta
 * vuelta — maquetas aprobadas 4a/5a/5b/5c): módulos (nivel 1, riel) con sus
 * ítems (nivel 2, sidebar). `sec_menu.label`/`sec_menu.descripcion` guardan
 * estas claves tal cual (ver Database\Seeders\Catalogo\SecMenuSeeder) y se
 * resuelven vía __() recién en presentación (ADR 0013).
 *
 * Los módulos y su vocabulario salen de la ESPECIFICACIÓN, no del mockup
 * (pedido explícito del 28/8/2026): §4 de
 * `docs/especificacion/especificacion_funcional_tecnica.md` — Comercial
 * (4.1), Recursos (4.2), Operación (4.3), Financiero (4.4), Seguridad (4.6) —
 * más Reportes (cap. 9/10). El mockup
 * aprobado fija el LAYOUT (riel + sidebar + badges); los nombres "Flota",
 * "Insumos", "Personas" y "Finanzas" que ese mockup inventaba se reemplazan
 * por las áreas reales del documento.
 *
 * Archivo propio (no `seguridad.php`): el menú es transversal a todos los
 * módulos de dominio, aunque el catálogo `sec_menu` viva en Seguridad.
 */

return [

    'operacion' => [
        'label' => 'Operación',
        'descripcion' => 'Trabajos, cuadrillas y seguimiento de vuelos de cada servicio.',
        'items' => [
            'tablero' => 'Tablero',
            'orden_trabajo' => 'Trabajos',
            'cuadrillas' => 'Cuadrillas',
            'seguimiento_vuelos' => 'Seguimiento de vuelos',
        ],
    ],

    // Marketplace (ADR 0027): la solicitud de servicio es la unidad de negocio.
    'solicitudes' => [
        'label' => 'Solicitudes',
        'descripcion' => 'Solicitudes de servicio de los clientes y su ciclo hasta la conformidad.',
        'items' => [
            'solicitudes' => 'Solicitudes de servicio',
            'asignaciones' => 'Asignaciones',
            'calificaciones' => 'Calificaciones',
            'disputas' => 'Disputas',
            'metricas' => 'Métricas del mercado',
        ],
    ],

    'recursos' => [
        'label' => 'Recursos',
        'descripcion' => 'Drones, baterías, bases, personal y fichas técnicas de dron.',
        'items' => [
            'drones' => 'Drones',
            'baterias' => 'Baterías',
            'bases' => 'Bases',
            'personal' => 'Personal',
            'fichas_dron' => 'Fichas de dron',
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
            'sanciones' => 'Sanciones',
            'documentos' => 'Documentos',
        ],
    ],

    'reportes' => [
        'label' => 'Reportes',
        'descripcion' => 'Reportes técnicos y alertas por excepción.',
        'items' => [
            'tecnicos' => 'Técnicos',
            'alertas' => 'Alertas',
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

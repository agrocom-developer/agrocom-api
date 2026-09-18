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
 * (4.1), Recursos (4.2), Operación (4.3), Financiero (4.4), Mantenimiento e
 * inventario (4.5), Seguridad (4.6) — más Reportes (cap. 9/10). El mockup
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
        'descripcion' => 'Órdenes, trabajos, asignación de equipos, estadías y seguimiento de vuelos de cada jornada.',
        'items' => [
            'tablero' => 'Tablero',
            'ordenes' => 'Órdenes de aplicación',
            'orden_trabajo' => 'Orden de Trabajo',
            'asignacion_equipos' => 'Distribución de equipos',
            'estadias' => 'Estadías en hacienda',
            'seguimiento_vuelos' => 'Seguimiento de vuelos',
        ],
    ],

    'comercial' => [
        'label' => 'Comercial',
        'descripcion' => 'Clientes, contratos, propiedades, lotes, cultivos y campañas.',
        'items' => [
            'clientes' => 'Clientes',
            'contratos' => 'Contratos',
            'propiedades' => 'Propiedades',
            'campos' => 'Campos',
            'lotes' => 'Lotes',
            'cultivos' => 'Cultivos',
            'campanias' => 'Campañas',
        ],
    ],

    'recursos' => [
        'label' => 'Recursos',
        'descripcion' => 'Drones, baterías, vehículos, generadores, bases, personal, equipos de trabajo y fichas de inventario de dron.',
        'items' => [
            'drones' => 'Drones',
            'baterias' => 'Baterías',
            'vehiculos' => 'Vehículos',
            'generadores' => 'Generadores',
            'bases' => 'Bases',
            'personal' => 'Personal',
            'equipos_trabajo' => 'Equipos de trabajo',
            'fichas_dron' => 'Fichas de dron',
        ],
    ],

    'mantenimiento' => [
        'label' => 'Mantenimiento',
        'descripcion' => 'Planes, órdenes de mantenimiento, repuestos y stock por base.',
        'items' => [
            'ordenes' => 'Órdenes de mantenimiento',
            'planes' => 'Planes de mantenimiento',
            'repuestos' => 'Repuestos',
            'stock' => 'Stock por base',
        ],
    ],

    'financiero' => [
        'label' => 'Financiero',
        'descripcion' => 'Gastos, combustible, devengos, anticipos, planilla y facturación.',
        'items' => [
            'gastos' => 'Gastos',
            'combustible' => 'Combustible',
            'rendiciones' => 'Rendiciones',
            'devengos' => 'Devengos',
            'planilla' => 'Planilla de pagos',
            'facturas' => 'Facturas y cobranzas',
            'anticipos' => 'Anticipos',
        ],
    ],

    'reportes' => [
        'label' => 'Reportes',
        'descripcion' => 'Reportes técnicos y comerciales, alertas por excepción.',
        'items' => [
            'tecnicos' => 'Técnicos',
            'comerciales' => 'Comerciales',
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

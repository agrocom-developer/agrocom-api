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
        'descripcion' => 'Órdenes, trabajos, sesiones y pausas de cada jornada de vuelo.',
        'items' => [
            'tablero' => 'Tablero',
            'ordenes' => 'Órdenes de aplicación',
            'trabajos' => 'Trabajos',
            'sesiones' => 'Sesiones',
            'pausas' => 'Pausas',
        ],
    ],

    'comercial' => [
        'label' => 'Comercial',
        'descripcion' => 'Clientes, contratos y campos.',
        'items' => [
            'clientes' => 'Clientes',
            'contratos' => 'Contratos',
            // Tarea 77 (HU-54): "Campos y lotes" era un solo ítem — el
            // pedido del dueño (7/9/2026) separa la propiedad de sus lotes,
            // cada uno con su propia pantalla. "Propiedad" es el rótulo de
            // negocio para lo que la tabla sigue llamando `com_campos`.
            'propiedades' => 'Propiedades',
            'lotes' => 'Lotes',
        ],
    ],

    'recursos' => [
        'label' => 'Recursos',
        'descripcion' => 'Drones, baterías, vehículos, generadores, bases, personal y equipos de trabajo.',
        'items' => [
            'drones' => 'Drones',
            'baterias' => 'Baterías',
            'vehiculos' => 'Vehículos',
            // Tarea 72 (HU-49, ADR 0015 punto 3): catálogo de generadores.
            'generadores' => 'Generadores',
            'bases' => 'Bases',
            // Tarea 77 (HU-54): pedido del dueño (7/9/2026), "Personas" era
            // el nombre de la tabla filtrándose a la interfaz.
            'personal' => 'Personal',
            // Tarea 72 (HU-49, ADR 0015 punto 3): equipos de trabajo — el
            // piloto y su auxiliar, con el equipamiento asignado.
            'equipos_trabajo' => 'Equipos de trabajo',
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
            // HU-20: sin módulo raíz propio en la espec §4 — entra acá, mismo
            // criterio que "Organización" (pantalla de administración
            // transversal, ver runs/10-diseno.md).
            'versiones_apk' => 'Versiones del APK',
            // HU-46 (tarea 69, ADR 0015 punto 1): la campaña como eje
            // transversal, configuración de toda la operación — mismo
            // criterio que "Organización" arriba.
            'campanias' => 'Campañas',
        ],
    ],

];

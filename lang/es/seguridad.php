<?php

/*
 * Copy específico de las pantallas del módulo Seguridad (login, selección de
 * rol, cambio de rol activo) — distinto de `lang/es/ui.php`, que solo tiene
 * strings genéricos del catálogo Atomic Design (ADR 0013; convención fijada
 * en docs/diseno/sistema_diseno_panel.md §5).
 *
 * Nombres de rol (`sec_role.name`/`description`) NO se traducen acá: son
 * vocabulario del dominio (ADR 0013 punto 3) y llegan ya resueltos a los
 * componentes vía prop — este archivo solo cubre el copy fijo alrededor de
 * esos datos (títulos, subtítulos, botones).
 */

return [

    // Copy del panel editorial de templates/auth-layout (galería de fotos +
    // wordmark + headline) — compartido por login y selección de rol, ambos
    // armados sobre ese mismo template. El wordmark reutiliza `ui.logo.alt`
    // ("Agrocom"), no hace falta una clave nueva para eso.
    'auth' => [
        // Galería de 3 imágenes (rediseño de login, cuarta vuelta): un
        // headline/subheadline por slide, en el mismo orden que
        // `$galeriaImagenes` de templates/auth-layout.blade.php (drone-hero.jpg,
        // -2.jpg, -3.jpg). Los nombres de archivo NO viven acá: no son copy,
        // son datos de presentación que arma el Blade.
        'galeria' => [
            [
                'headline' => 'Cada hectárea, medida y tratada con precisión.',
                'subheadline' => 'Planificación de vuelo, mezclas y reportes de aplicación en un solo panel.',
            ],
            [
                'headline' => 'Cada vuelo, documentado desde el despegue.',
                'subheadline' => 'Sesiones, mezclas y áreas cubiertas, trazables en tiempo real.',
            ],
            [
                'headline' => 'Del campo a la planilla, sin perder un dato.',
                'subheadline' => 'Los devengos se generan solos al validar cada sesión.',
            ],
        ],
        'galeria_aria_label' => 'Elegí qué imagen mostrar',
        'galeria_dot' => 'Mostrar imagen :numero de :total',
        'tagline' => 'FUMIGACIÓN CON DRONES',
        'sin_alta_publica' => '¿No tenés cuenta? Las altas las gestiona el administrador de tu operación.',
    ],

    'login' => [
        'tabs_aria_label' => 'Opciones de acceso',
        'tab_ingreso' => 'Ingreso',
        'tab_recuperar' => 'Recuperar acceso',
        'titulo' => 'Ingresá a tu panel',
        'subtitulo' => 'Usá el usuario que te asignó el administrador.',
        'campo_usuario' => 'Usuario',
        'campo_password' => 'Contraseña',
        'recordarme' => 'Recordarme',
        'olvido_password' => '¿Olvidaste tu contraseña?',
        'boton_ingresar' => 'Iniciar sesión',
        'boton_ingresando' => 'Verificando acceso…',
    ],

    'recuperar' => [
        'titulo' => 'Recuperar acceso',
        'subtitulo' => 'Ingresá tu correo electrónico. El administrador recibe la solicitud y te entrega una clave temporal.',
        'campo_email' => 'Correo electrónico',
        'boton_enviar' => 'Enviar solicitud',
        'volver' => 'Volver al ingreso',
    ],

    'rol' => [
        'seleccion_titulo' => 'Elegí con qué rol continuar',
        'seleccion_subtitulo' => 'Tu cuenta tiene más de un rol asignado. Los permisos de esta sesión serán los del rol que elijas — podés cambiarlo después sin volver a loguearte.',
        'seleccion_boton_continuar' => 'Continuar',
        'seleccion_vacia' => 'Todavía no tenés ningún rol asignado. Contactá a un administrador.',
        'switch_trigger' => 'Cambiar de rol',
        'switch_titulo' => 'Cambiar de rol activo',
        'badge_activo' => 'Rol activo',
    ],

    // Etiquetas del menú lateral (sec_menu.label guarda estas claves tal
    // cual, ver Database\Seeders\Catalogo\SecMenuSeeder) — resueltas por
    // molecules/menu-item vía __(), nunca antes (ADR 0013).
    'menu' => [
        'inicio' => 'Inicio',
        'usuarios' => 'Usuarios',
        'organizacion' => 'Organización',
    ],

    // Copy mínimo de las páginas placeholder de HU-02 (GET /panel/dashboard,
    // GET /panel/usuarios) — `frontend` lo reemplaza al ensamblar el
    // contenido real de cada pantalla.
    'dashboard' => [
        'titulo' => 'Panel',
        'bienvenida' => 'Hola, :nombre. Estás operando como :rol — el menú de la izquierda muestra solo lo que tu rol activo puede ver.',
        // Datos MOCK para mockup de dashboard (reemplazar cuando existan módulos reales)
        'mock' => [
            'sesiones_personales' => 'Sesiones personales (este mes)',
            'hectareas_cubiertas' => 'Hectáreas cubiertas',
            'proxima_orden' => 'Próxima orden',
            'sesiones_equipo' => 'Sesiones del equipo',
            'hectareas_equipo' => 'Hectáreas del equipo',
            'pendientes_validar' => 'Pendientes de validar',
            'usuarios_activos' => 'Usuarios activos',
            'sesiones_mes' => 'Sesiones este mes',
            'hectareas_totales' => 'Hectáreas totales cubiertas',
            'devengos_pendientes' => 'Devengos pendientes',
            'ordenes_por_estado' => 'Órdenes por estado',
            'notificacion_1_titulo' => 'Nueva orden asignada',
            'notificacion_1_hora' => 'hace 5 minutos',
            'notificacion_2_titulo' => 'Sesión validada correctamente',
            'notificacion_2_hora' => 'hace 1 hora',
            'notificacion_3_titulo' => 'Actualización de sistema disponible',
            'notificacion_3_hora' => 'hace 3 horas',
        ],
    ],

    'usuarios' => [
        'titulo' => 'Usuarios',
        'proximamente' => 'Próximamente: gestión de usuarios.',
    ],

    // Mockup de "Registro de la compañía" (GET /panel/organizacion, vista previa de SaaS multi-tenant)
    'organizacion' => [
        'titulo' => 'Registro de la compañía',
        'subtitulo' => 'Gestión centralizada de tu organización y configuración de suscripción. Esta es una vista previa — sin guardado funcional en esta versión.',
        'seccion_datos_empresa' => 'Datos de empresa',
        'campo_nombre' => 'Nombre de empresa',
        'campo_rubro' => 'Rubro',
        'campo_logo' => 'Logo de empresa',
        'seccion_contacto' => 'Datos de contacto',
        'campo_email' => 'Correo electrónico',
        'campo_telefono' => 'Teléfono',
        'campo_direccion' => 'Dirección',
        'seccion_plan' => 'Plan de suscripción',
        'plan_group_label' => 'Elige tu plan de suscripción',
        'plan_basico_nombre' => 'Básico',
        'plan_basico_precio' => 'Bs 250',
        'plan_basico_feat_1' => 'Hasta 3 usuarios',
        'plan_basico_feat_2' => 'Sesiones y reportes básicos',
        'plan_basico_feat_3' => 'Soporte por correo',
        'plan_profesional_nombre' => 'Profesional',
        'plan_profesional_precio' => 'Bs 450',
        'plan_profesional_feat_1' => 'Hasta 10 usuarios',
        'plan_profesional_feat_2' => 'Análisis avanzado y dashboard',
        'plan_profesional_feat_3' => 'Soporte prioritario',
        'plan_profesional_feat_4' => 'Integración con terceros',
        'plan_enterprise_nombre' => 'Enterprise',
        'plan_enterprise_precio' => 'Bs 1.200',
        'plan_enterprise_feat_1' => 'Usuarios ilimitados',
        'plan_enterprise_feat_2' => 'Análisis en tiempo real',
        'plan_enterprise_feat_3' => 'Soporte dedicado 24/7',
        'plan_enterprise_feat_4' => 'Multi-sucursal incluido',
        'plan_enterprise_feat_5' => 'Personalización avanzada',
        'plan_destacado' => 'Más elegido',
        'plan_period' => '/mes',
        'seccion_funcionalidades' => 'Funcionalidades',
        'switch_multi_sucursal' => 'Habilitar multi-sucursal',
        'switch_multi_sucursal_help' => 'Permite gestionar múltiples sucursales desde una sola cuenta.',
        'vista_previa_nota' => 'Mockup visual — sin guardado real en esta versión',
        'mock_rubro' => 'Fumigación aérea con drones',
        'mock_logo_desc' => 'Logo de Agrocom SRL — transparente y listo para usar',
        'mock_direccion' => 'Av. Simonó 1150, San Miguel de Tucumán, Argentina',
    ],

];

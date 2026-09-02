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

    // Pantalla de selección de rol (quinta vuelta, maqueta 5c) y cambio de
    // rol activo. `meta.*` es METADATA de presentación por slug de rol
    // (nombre legible, descripción comercial, chips de permisos) — no
    // traduce vocabulario de dominio (ADR 0013 punto 3): el slug sigue
    // viajando intacto, y un rol sin metadata degrada al `name`/
    // `description` crudos de la base (ver PresentadorRol).
    'rol' => [
        'foto_headline' => 'Un solo usuario, varias responsabilidades.',
        'foto_subheadline' => 'El rol define qué módulos ves y qué podés aprobar durante esta sesión.',
        'seleccion_titulo' => '¿Con qué rol vas a trabajar?',
        'seleccion_subtitulo' => 'Podés cambiarlo en cualquier momento desde el menú, sin volver a ingresar.',
        'seleccion_boton_continuar' => 'Continuar como :rol',
        'seleccion_grupo_aria' => 'Roles disponibles',
        'seleccion_vacia' => 'Todavía no tenés ningún rol asignado. Contactá a un administrador.',
        'ultimo_usado' => 'ÚLTIMO USADO',
        'recordar' => 'Entrar siempre con este rol',
        'error_actualizar' => 'No se pudo cambiar de rol. Intentá nuevamente.',
        'error_red' => 'Error de red. Intentá nuevamente.',
        'switch_trigger' => 'Cambiar de rol',
        'switch_titulo' => 'Cambiar de rol activo',
        'badge_activo' => 'Rol activo',
        'meta' => [
            'dueno' => [
                'nombre' => 'Dueño',
                'descripcion' => 'Acceso total: finanzas, personas, contratos y gestión de otros dueños.',
                'permisos' => ['Todos los módulos', 'Aprobar devengos', 'Ver costos'],
            ],
            'piloto' => [
                'nombre' => 'Piloto de dron',
                'descripcion' => 'Ejecuta sesiones de vuelo en campo y carga la evidencia del RC.',
                'permisos' => ['Programación', 'Sesiones', 'Mezclas'],
            ],
            'auxiliar' => [
                'nombre' => 'Auxiliar de campo',
                'descripcion' => 'Apoya la preparación de mezclas y la logística de cada sesión.',
                'permisos' => ['Mezclas', 'Checklist', 'Evidencias'],
            ],
            'jefe_campo' => [
                'nombre' => 'Jefe de campo',
                'descripcion' => 'Coordina la cuadrilla y valida sesiones ajenas — nunca las propias.',
                'permisos' => ['Programación', 'Validación', 'Pausas'],
            ],
            'encargado_operaciones' => [
                'nombre' => 'Encargado de operaciones',
                'descripcion' => 'Administra usuarios, órdenes, stock y la planificación de la base.',
                'permisos' => ['Operación', 'Recursos', 'Usuarios'],
            ],
        ],
    ],

    // Dashboard "Operación de hoy" (quinta vuelta — maquetas 4a/5a/5b):
    // copy fijo de pantalla; los DATOS (cifras, sesiones, causas) viajan por
    // DatosDemoPanel, nunca por acá.
    'dashboard' => [
        'titulo' => 'Operación de hoy',
        'bajada' => ':fecha · lo que falta cerrar antes del corte de planilla.',
        'exportar' => 'Exportar',
        'programar_sesion' => 'Programar sesión',
        'tabs_aria' => 'Vistas del dashboard',
        'tab_resumen' => 'Resumen',
        'tab_mapa' => 'Mapa',
        'tab_resumen_lote' => 'Resumen por lote',
        'tab_multimedia' => 'Multimedia',
        'ventana_titulo' => 'Ventana volable :horario.',
        'ventana_accion' => 'Revisar autorización',
        'programacion_titulo' => 'Programación de hoy',
        'ver_todas' => 'Ver todas',
        'hoy' => 'Hoy',
        'col_hora' => 'Hora',
        'col_lote' => 'Lote',
        'col_piloto' => 'Piloto',
        'col_dron' => 'Dron',
        'col_ha' => 'Ha',
        'col_estado' => 'Estado',
        'pausas_titulo' => 'Pausas por causa',
        'stock_titulo' => 'Stock bajo mínimo',
        'stock_accion' => 'Generar pedido',
        'rc_alerta' => ':cantidad sesiones cerradas sin captura del RC.',
        'rc_detalle' => 'Ninguna sesión se valida ni devenga sin evidencia adjunta.',
        'rc_resolver' => 'Resolver',

        // Sectorización (sexta vuelta parte 2 — Fases 3/4): títulos de
        // section-head, nunca los rótulos genéricos de ui.php porque son
        // copy de ESTA pantalla, no del catálogo de componentes.
        'seccion_distribucion' => 'Distribución de sesiones',
        'distribucion_centro' => 'sesiones',

        // Fase 4 (novena vuelta) — gráficos ApexCharts del tab Resumen.
        'seccion_sesiones_estado' => 'Sesiones por estado',
        'seccion_hectareas_periodo' => 'Hectáreas aplicadas por día',
        'seccion_avance_meta' => 'Avance de meta del mes',
        'avance_meta_label' => 'Cumplido',
        'avance_meta_pie' => ':valor ha de :meta ha planificadas',

        // Fase 5 (novena vuelta) — detalle de clientes del tab Resumen:
        // actividad reciente + estado de contrato combinados.
        'seccion_clientes' => 'Clientes',
        'clientes_col_cliente' => 'Cliente',
        'clientes_col_actividad' => 'Actividad del período',
        'clientes_col_ultima_sesion' => 'Última sesión',
        'clientes_col_contrato' => 'Contrato',
        'clientes_col_ejecucion' => 'Ejecución',
        'clientes_col_vence' => 'Vence en',
        'clientes_actividad' => ':sesiones sesiones · :ha',
        'clientes_vencido' => 'Venció',
        'clientes_vence_en' => ':dias días',

        // Fase 6 (novena vuelta) — tab Mapa.
        'mapa_lotes_titulo' => 'Lotes en el mapa',
        'mapa_hectareas_titulo' => 'Hectáreas en el mapa',
        'mapa_sesiones_titulo' => 'Sesiones georreferenciadas',
        'mapa_leyenda_en_vuelo' => 'En vuelo',
        'mapa_leyenda_atencion' => 'Necesita atención',
        'mapa_leyenda_programado' => 'Programado',
        'mapa_leyenda_completado' => 'Completado',

        // Fase 7 (novena vuelta) — tab Resumen por lote.
        'seccion_resumen_lote' => 'Cuadros por lote',
        'lote_col_completadas' => 'Completadas',
        'lote_col_pendientes' => 'Pendientes',
        'lote_col_total' => 'Total',
        'lote_col_litros' => 'Pesticida',
        'lote_col_litros_ha' => 'L/ha',
        'lote_col_tiempo' => 'Tiempo de vuelo',

        // Fase 8 (novena vuelta) — tab Multimedia.
        'multimedia_vistas_aria' => 'Formato de las capturas',
        'multimedia_vista_galeria' => 'Galería',
        'multimedia_vista_carrusel' => 'Carrusel',
        'multimedia_vista_tabla' => 'Tabla',
        'multimedia_anterior' => 'Anterior',
        'multimedia_siguiente' => 'Siguiente',
        'multimedia_col_fecha' => 'Fecha',
        'multimedia_col_sesion' => 'Sesión',
        'multimedia_col_piloto' => 'Piloto',
        'multimedia_col_lote' => 'Lote',
        'multimedia_col_descripcion' => 'Descripción',
        'multimedia_ver' => 'Ver',
    ],

    'usuarios' => [
        'titulo' => 'Usuarios',
        'proximamente' => 'Próximamente: gestión de usuarios.',
    ],

    // Revocación de sesiones de la app de campo (HU-03). El nombre del rol
    // (`sec_role.name`) NO se traduce acá: es vocabulario de dominio y llega
    // resuelto desde la base (ADR 0013 punto 3).
    'dispositivos' => [
        'titulo' => 'Dispositivos',
        'subtitulo' => 'Equipos con sesión abierta en la app de campo. Revocar deja al dispositivo sin acceso en el acto: quien lo use tendrá que volver a iniciar sesión.',
        'col_usuario' => 'Usuario',
        'col_dispositivo' => 'Dispositivo',
        'col_rol' => 'Rol',
        'col_ultimo_uso' => 'Último uso',
        'revocar' => 'Revocar',
        'revocado' => 'El dispositivo perdió el acceso.',
        'vacio' => 'No hay dispositivos con sesión abierta.',
        'equipo_sin_nombre' => 'Equipo sin nombre',
        'usuario_desconocido' => 'Cuenta dada de baja',
        'sin_uso' => 'Todavía sin uso',
    ],

    // Mockup de "Registro de la compañía" (GET /panel/organizacion, vista previa de SaaS
    // multi-tenant) — reconstruida sobre el arquetipo formulario (tarea 31, ver
    // docs/diseno/guia_pantalla_panel.md §6.3).
    'organizacion' => [
        'titulo' => 'Registro de la compañía',
        'subtitulo' => 'Gestión centralizada de tu organización y configuración de suscripción.',
        'alerta_vista_previa' => 'Vista previa. Los cambios no se persisten todavía.',
        'accion_descartar' => 'Descartar',
        'estado_sin_cambios' => 'Sin cambios pendientes',

        'tabs_aria' => 'Secciones de organización',
        'tab_organizacion' => 'Organización',
        'tab_usuarios_roles' => 'Usuarios y roles',
        'tab_facturacion' => 'Facturación',
        'tab_proximamente' => 'Próximamente.',

        'campos_contador' => ':cantidad campos',

        'seccion_datos_empresa' => 'Datos de empresa',
        'campo_nombre' => 'Nombre de empresa',
        'campo_rubro' => 'Rubro',
        'campo_logo' => 'Logo de empresa',
        'campo_logo_reemplazar' => 'Reemplazar',
        'campo_logo_quitar' => 'Quitar',
        'campo_logo_ayuda' => 'PNG o SVG, fondo transparente recomendado.',

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

        'aside_progreso_titulo' => 'Perfil completo',
        'aside_progreso_resumen' => ':completos de :total campos',
        'aside_progreso_item_nombre' => 'Nombre',
        'aside_progreso_item_rubro' => 'Rubro',
        'aside_progreso_item_logo' => 'Logo',
        'aside_progreso_item_contacto' => 'Contacto',
        'aside_progreso_item_domicilio_fiscal' => 'Domicilio fiscal',
        'aside_progreso_item_datos_bancarios' => 'Datos bancarios',

        'aside_suscripcion_titulo' => 'Suscripción',
        'aside_suscripcion_plan' => 'Plan',
        'aside_suscripcion_plan_valor' => 'Operación Pro',
        'aside_suscripcion_estado' => 'Estado',
        'aside_suscripcion_estado_valor' => 'Vigente',
        'aside_suscripcion_renueva' => 'Renueva',
        'aside_suscripcion_dispositivos' => 'Dispositivos',
        'aside_suscripcion_accion' => 'Ver facturación',

        'mock_nombre_empresa' => 'Agrocom SRL',
        'mock_rubro' => 'Fumigación aérea con drones',
        'mock_logo_nombre' => 'logo-agrocom-srl.png',
        'mock_logo_peso' => '240 KB',
        'mock_email' => 'contacto@agrocom.com.ar',
        'mock_telefono' => '+54 9 3815 55-4433',
        'mock_direccion' => 'Av. Simonó 1150, San Miguel de Tucumán, Argentina',
        'mock_renueva_fecha' => '01/10/2026',
        'mock_dispositivos_valor' => '6 / 10',
    ],

];

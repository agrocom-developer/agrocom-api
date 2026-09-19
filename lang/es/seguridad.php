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
                'headline' => 'Todo lo que necesitas para entrar al campo.',
                'subheadline' => 'DJI Agras T50 completo: dron, baterías, generador y control listos para trabajar.',
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
        'galeria_aria_label' => 'Elige qué imagen mostrar',
        'galeria_dot' => 'Mostrar imagen :numero de :total',
        'tagline' => 'FUMIGACIÓN CON DRONES',
        'sin_alta_publica' => '¿No tienes cuenta? Las altas las gestiona el administrador de tu operación.',
    ],

    'login' => [
        'tabs_aria_label' => 'Opciones de acceso',
        'tab_ingreso' => 'Ingreso',
        'tab_recuperar' => 'Recupera tu acceso',
        'titulo' => 'Ingresa al sistema',
        'subtitulo' => 'Usa el usuario que te asignó el administrador.',
        'campo_usuario' => 'Usuario',
        'campo_password' => 'Contraseña',
        'recordarme' => 'Recordarme',
        'olvido_password' => '¿Olvidaste tu contraseña?',
        'boton_ingresar' => 'Iniciar sesión',
        'boton_ingresando' => 'Verificando acceso…',
        'error_credenciales' => 'Las credenciales no coinciden con ningún registro.',
        'error_generico' => 'Ocurrió un error. Intenta nuevamente.',
        'error_username_requerido' => 'Ingresa tu usuario.',
        'error_password_requerido' => 'Ingresa tu contraseña.',
    ],

    'recuperar' => [
        'titulo' => 'Recupera tu acceso',
        'subtitulo' => 'Ingresa tu correo electrónico y te enviamos un enlace para elegir una contraseña nueva.',
        'campo_email' => 'Correo electrónico',
        'boton_enviar' => 'Enviar solicitud',
        'volver' => 'Volver al ingreso',
        // Misma respuesta exista o no la cuenta, esté bloqueada o no
        // (invariante de negocio de esta tarea: nunca revelar qué correos
        // existen) — se muestra tal cual en el propio panel "Recupera tu
        // acceso" del login, nunca como redirect a otra pantalla.
        'estado_generico' => 'Si el correo existe en el sistema, vas a recibir un enlace para restablecer tu contraseña.',

        // Correo enviado por RestablecerContrasena (Notification).
        'correo_asunto' => 'Restablece tu contraseña — Agrocom',
        'correo_saludo' => 'Hola, :nombre.',
        'correo_cuerpo' => 'Recibimos un pedido para restablecer la contraseña de tu cuenta. Si fuiste tú, haz clic en el botón de abajo para elegir una nueva.',
        'correo_boton' => 'Restablecer contraseña',
        'correo_expiracion' => 'Este enlace vence en 60 minutos.',
        'correo_ignorar' => 'Si no pediste este cambio, puedes ignorar este correo — tu contraseña actual sigue funcionando.',
        'correo_despedida' => 'Saludos, Agrocom.',
    ],

    // Pantalla de "elegir contraseña nueva" (tarea 66), llegada desde el
    // enlace del correo de recuperar acceso — panel y portal comparten
    // este mismo copy.
    'restablecer' => [
        'titulo' => 'Elegir contraseña nueva',
        'subtitulo' => 'Vas a poder ingresar con esta contraseña apenas la confirmes.',
        'campo_email' => 'Correo electrónico',
        'campo_password' => 'Contraseña nueva',
        'campo_password_confirmacion' => 'Confirmar contraseña nueva',
        'boton_confirmar' => 'Confirmar',
        'actualizada' => 'Tu contraseña se actualizó. Ya puedes ingresar con la nueva.',
        'token_invalido' => 'Este enlace ya no es válido — pide uno nuevo desde "Recupera tu acceso".',
    ],

    // Pantalla de selección de rol (quinta vuelta, maqueta 5c) y cambio de
    // rol activo. `meta.*` es METADATA de presentación por slug de rol
    // (nombre legible, descripción comercial, chips de permisos) — no
    // traduce vocabulario de dominio (ADR 0013 punto 3): el slug sigue
    // viajando intacto, y un rol sin metadata degrada al `name`/
    // `description` crudos de la base (ver PresentadorRol).
    /*
     * Pantalla de administración de roles y de la matriz rol↔permiso. Es
     * distinta de `rol` (abajo), que es la metadata de PRESENTACIÓN del
     * selector de rol: acá se administra el catálogo, allá se elige con cuál
     * operar.
     */
    'roles' => [
        'titulo' => 'Roles y permisos',
        'subtitulo' => 'Qué pantallas ve cada rol y qué puede hacer dentro de ellas. Los roles se asignan a las cuentas desde Usuarios.',
        'nuevo' => 'Nuevo rol',
        'col_rol' => 'Rol',
        'col_pantallas' => 'Pantallas',
        'col_acciones' => 'Acciones',
        'col_usuarios' => 'Usuarios',
        'col_estado' => 'Estado',
        'estado_activo' => 'Activo',
        'estado_inactivo' => 'Inactivo',
        'sin_usuarios' => 'nadie',
        'de_total' => 'de :total',
        'editar' => 'Editar',
        'permisos' => 'Permisos',
        'eliminar' => 'Dar de baja',
        'eliminar_confirmar' => '¿Dar de baja el rol :rol? Sus permisos se dan de baja con él.',
        'badge_rol_activo' => 'Tu rol',
        'vacio_titulo' => 'Todavía no hay roles en el catálogo',
        'vacio_detalle' => 'Los roles son administrados por el sistema. Tu cuenta debería tener asignado al menos un rol para acceder. Contacta a un administrador.',
        'catalogo_fijo' => 'El catálogo de :total permisos no se edita desde acá: cada código está escrito en el código del servidor, que es quien lo exige. Lo que se administra es qué rol tiene cuáles.',

        'creado' => 'Rol creado. Ahora dale sus permisos.',
        'actualizado' => 'Rol actualizado.',
        'eliminado' => 'Rol dado de baja.',
        'permisos_guardados' => 'Permisos actualizados.',

        'crear_titulo' => 'Nuevo rol',
        'crear_subtitulo' => 'Nace sin ningún permiso. Los permisos se dan después, en su propia pantalla.',
        'editar_titulo' => 'Editar :rol',
        'editar_subtitulo' => 'Nombre, descripción y estado. Los permisos se administran aparte.',
        'seccion_identidad' => 'Identidad del rol',
        'campo_nombre' => 'Nombre interno',
        'campo_nombre_ayuda' => 'Minúsculas, dígitos y guion bajo, hasta 30 caracteres. Es la identidad del rol en la base y no se libera si el rol se da de baja: el catálogo de roles es del sistema.',
        'campo_descripcion' => 'Descripción',
        'campo_descripcion_ayuda' => 'Una línea que explique qué hace este rol. Hasta 150 caracteres.',
        'campo_activo' => 'Activo',
        'campo_activo_ayuda' => 'Un rol inactivo no se puede elegir al iniciar sesión ni cuenta como portador de sus permisos. Quien lo tenga asignado deja de poder entrar con él en el request siguiente.',
        'campo_activo_bloqueado' => 'No puedes desactivar el rol con el que estás operando.',
        'guardar_y_permisos' => 'Crear y dar permisos',
        'cancelar' => 'Cancelar',
        'error_nombre_formato' => 'El nombre interno va en minúsculas, dígitos y guion bajo, y empieza con letra (por ejemplo: supervisor_taller).',
        'error_nombre_duplicado' => 'Ya existe un rol con ese nombre. Un rol dado de baja tampoco libera el suyo.',
        'error_nombre_requerido' => 'Ingresa el nombre interno del rol.',
        'error_descripcion_requerida' => 'Ingresa una descripción para el rol.',

        'permisos_titulo' => 'Permisos de :rol',
        'permisos_subtitulo' => 'Lo que este rol ve en el menú y lo que puede hacer dentro de cada pantalla. El menú no se edita aparte: se enciende al dar el permiso de la pantalla.',
        'permisos_preview_titulo' => 'Lo que verá este rol',
        'permisos_preview_pie' => 'Un módulo entero desaparece del riel cuando ninguna de sus pantallas está encendida.',
        'permisos_preview_oculto' => 'oculto',
        'permisos_modulo_resumen' => ':encendidas de :total pantallas',
        'permisos_sin_acciones' => 'sin acciones',
        'permisos_conteo_acciones' => ':activas/:total acciones',
        'permisos_bloqueado' => 'Es el único rol activo con este permiso. Dáselo antes a otro rol: nadie puede conceder un permiso que no tiene, así que un permiso huérfano no se recupera desde el panel.',
        'permisos_no_concedible' => 'No puedes conceder ni retirar un permiso que tu propio rol activo no tiene.',
        'permisos_sueltos_titulo' => 'Acciones sin pantalla en el panel',
        'permisos_sueltos_ayuda' => 'Se ejercen desde la app de campo, no desde el panel, así que no encienden ningún ítem del menú.',
        'permisos_guardar' => 'Guardar cambios',
        'permisos_descartar' => 'Descartar',
        'permisos_volver' => 'Volver a roles',
        'permisos_sin_cambios' => 'Sin cambios pendientes',
        'permisos_con_cambios' => ':cantidad cambio sin guardar|:cantidad cambios sin guardar',
        'permisos_huerfana' => 'Hay :cantidad acción activa sobre una pantalla que este rol no ve. No hace nada hasta que enciendas :codigo.|Hay :cantidad acciones activas sobre una pantalla que este rol no ve. No hacen nada hasta que enciendas :codigo.',
        'permisos_aviso_rol_propio' => 'Estás editando el rol con el que iniciaste sesión. Los permisos de ver y administrar roles quedan bloqueados: soltarlos te dejaría fuera de esta pantalla en el próximo clic.',
    ],

    'rol' => [
        'foto_headline' => 'Un solo usuario, varias responsabilidades.',
        'foto_subheadline' => 'El rol define qué módulos ves y qué puedes aprobar durante esta sesión.',
        'seleccion_titulo' => '¿Con qué rol vas a trabajar?',
        'seleccion_subtitulo' => 'Puedes cambiarlo en cualquier momento desde el menú, sin volver a ingresar.',
        'seleccion_boton_continuar' => 'Continuar como :rol',
        'seleccion_grupo_aria' => 'Roles disponibles',
        'seleccion_vacia' => 'Todavía no tienes ningún rol asignado. Contacta a un administrador.',
        'ultimo_usado' => 'ÚLTIMO USADO',
        'recordar' => 'Entrar siempre con este rol',
        'error_actualizar' => 'No se pudo cambiar de rol. Intenta nuevamente.',
        'error_red' => 'Error de red. Intenta nuevamente.',
        'error_id_role_requerido' => 'Elige el rol con el que vas a trabajar.',
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
            'admin_plataforma' => [
                'nombre' => 'Administrador de plataforma',
                'descripcion' => 'Acceso técnico total sobre la instalación, incluida la gestión de dueños.',
                'permisos' => ['Todos los módulos', 'Roles y permisos', 'Configuración'],
            ],
        ],
    ],

    // Dashboard "Operación de hoy" (quinta vuelta — maquetas 4a/5a/5b):
    // copy fijo de pantalla; los DATOS (cifras, sesiones, causas) viajan por
    // DatosDemoPanel, nunca por acá.
    // Copy del dashboard. Desde la tarea 67 todo dato viene de la base: acá
    // solo vive el texto fijo de pantalla. Las claves de la maqueta que ya no
    // tienen sección (ventana volable, alerta de RC fija, avance de meta,
    // vistas de carrusel/tabla de multimedia) se retiraron con ella.
    'dashboard' => [
        'titulo' => 'Operación de hoy',
        'bajada' => ':fecha · lo que falta cerrar antes del corte de planilla.',
        'viendo_como' => 'Viendo como :rol',
        'tabs_aria' => 'Vistas del dashboard',
        'tab_resumen' => 'Resumen',
        'tab_mapa' => 'Mapa',
        'tab_resumen_lote' => 'Resumen por lote',
        'tab_multimedia' => 'Multimedia',
        'ver_todas' => 'Ver todas',

        // Estado vacío de la página entera: el rol entra al dashboard pero no
        // tiene ninguna sección habilitada, o ninguna con datos todavía.
        'vacio_titulo' => 'Todavía no hay nada que mostrar acá',
        'vacio_detalle' => 'Tu rol activo no tiene secciones habilitadas en el tablero, o aún no se registró actividad. Usa el menú lateral para ir a tus pantallas.',

        // Estado vacío por falta de campaña abierta (causa más común en una
        // instalación nueva: sin campaña, no hay contratos, órdenes ni
        // sesiones que mostrar). Ver `puedeCrearCampania` en `_sin-secciones`.
        'sin_campania_titulo' => 'Activa una campaña para empezar',
        'sin_campania_detalle' => 'Los contratos, las órdenes de aplicación y el resto del panel dependen de que haya una campaña abierta. Crea la primera para empezar a cargar contratos.',
        'sin_campania_accion' => 'Crear campaña',

        // Columnas compartidas por la tabla de sesiones (cola de validación y
        // "mis sesiones" usan el mismo parcial).
        'col_hora' => 'Fecha',
        'col_lote' => 'Lote',
        'col_piloto' => 'Piloto',
        'col_dron' => 'Dron',
        'col_vuelo' => 'Vuelo',
        'col_ha' => 'Ha',
        'col_estado' => 'Estado',

        'alertas_ver' => 'Ver alertas',

        'cola_validacion_titulo' => 'Sesiones esperando validación',

        // Encabezado del bloque personal. Sin él, en el tablero de un dueño
        // estas cifras se confunden con las de la operación entera.
        'mi_actividad' => 'Mi actividad',
        'mis_sesiones_titulo' => 'Mis últimas sesiones',
        'mis_sesiones_mes' => 'Mis sesiones del mes',
        'mis_sesiones_validadas' => 'Mías validadas',
        // "Validadas" y no "del mes" a secas: solo la hectárea validada cuenta
        // como trabajo hecho, y es la que se paga. Con el rótulo genérico, un
        // 0,00 junto a sesiones cerradas parecía un error de cálculo.
        'mis_hectareas_mes' => 'Mis hectáreas validadas',

        'mis_equipos_titulo' => 'Mis equipos este mes',
        'equipos_col_sesiones' => 'Sesiones',
        'equipos_col_ultimo' => 'Último vuelo',

        'liquidacion_titulo' => 'Mi liquidación · :periodo',
        'liquidacion_total' => 'Bs :monto',
        'liquidacion_devengado' => 'Devengado',
        'liquidacion_anticipos' => 'Anticipos',
        'liquidacion_saldo' => 'Saldo',
        'liquidacion_anticipos_detalle' => 'Anticipos del período',
        'liquidacion_col_fecha' => 'Fecha',
        'liquidacion_col_tarifa' => 'Tarifa/ha',
        'liquidacion_col_monto' => 'Monto',

        'seccion_sesiones_estado' => 'Sesiones por estado',
        'seccion_hectareas_periodo' => 'Hectáreas aplicadas por día',

        'seccion_clientes' => 'Avance por contrato',
        'clientes_col_cliente' => 'Cliente',
        'clientes_col_ejecucion' => 'Ejecución',
        'clientes_col_aplicadas' => 'Aplicadas',
        'clientes_col_contratadas' => 'Contratadas',

        'pausas_titulo' => 'Pausas por causa',
        'pausas_minutos' => ':minutos min',

        'stock_titulo' => 'Stock bajo mínimo',
        'stock_nivel' => ':cantidad / :minimo',
        'stock_accion' => 'Ver inventario',

        // Vocabulario compartido por la leyenda del mapa y los badges del
        // resumen por lote: son los mismos estados operativos.
        //
        // El tono verde dice "ninguna sesión pendiente de validar", NO "lote
        // terminado": un lote con el 7 % aplicado y todas sus sesiones
        // validadas cae acá. Decía "Completado" y contradecía la barra de
        // avance de su propia tarjeta.
        'mapa_leyenda_en_vuelo' => 'En vuelo',
        'mapa_leyenda_atencion' => 'Pendiente de validar',
        'mapa_leyenda_programado' => 'Sin sesiones',
        'mapa_leyenda_completado' => 'Al día',

        // Pantalla completa del mapa operativo (tarea 79): mismo mecanismo
        // que el editor de perímetro del lote, solo el botón de expandir —
        // este mapa es de solo lectura.
        'mapa_pantalla_completa' => 'Pantalla completa',
        'mapa_salir_pantalla_completa' => 'Salir de pantalla completa',

        'seccion_resumen_lote' => 'Avance por lote',
        'lote_col_completadas' => 'Aplicadas',
        'lote_col_pendientes' => 'Pendientes',
        'lote_col_total' => 'Total',
        'lote_col_litros' => 'Litros',
        'lote_col_litros_ha' => 'L/ha',
        'lote_col_tiempo' => 'Vuelo',
    ],

    // HU-45 (tarea 39): alta y mantenimiento de usuarios internos del panel,
    // con sus roles. `persona_id` es opcional — mismo criterio de select
    // nativo que `base_id` en personal.personas. El selector de roles es un
    // `<select multiple>` nativo (sin átomo de selección múltiple en el
    // catálogo, mismo criterio que el select simple).
    'usuarios' => [
        'creado' => 'El usuario se dio de alta correctamente.',
        'actualizado' => 'Los datos del usuario se actualizaron correctamente.',
        'eliminado' => 'El usuario se dio de baja correctamente.',
        'bloqueo_actualizado' => 'El estado de acceso del usuario se actualizó correctamente.',

        // Listado
        'titulo' => 'Usuarios',
        'subtitulo' => 'Cuentas internas del panel, con sus roles asignados.',
        'nueva' => 'Nuevo usuario',
        'filtro_busqueda' => 'Buscar',
        'filtro_busqueda_placeholder' => 'Nombre o usuario…',
        'filtrar' => 'Filtrar',
        'limpiar_filtro' => 'Limpiar filtro',
        'vacio_titulo' => 'Todavía no hay usuarios registrados',
        'vacio_detalle' => 'Los usuarios se crean desde el panel de administración. Pídele a un administrador que cree tu cuenta.',
        'filtro_vacio_titulo' => 'Sin resultados para este filtro',
        'filtro_vacio_detalle' => 'Ningún usuario coincide con la búsqueda o el filtro aplicado. Prueba con otro término o quita el filtro.',
        'col_nombre' => 'Nombre',
        'col_username' => 'Usuario',
        'col_tipo' => 'Tipo',
        'col_roles' => 'Roles',
        'col_persona' => 'Persona',
        'col_estado' => 'Estado',
        'sin_roles' => 'Sin roles asignados',
        'sin_persona' => 'Sin persona asociada',
        'filtro_tipo' => 'Tipo de cuenta',
        'filtro_tipo_todos' => 'Todos',
        'estado_activo' => 'Activo',
        'estado_bloqueado' => 'Bloqueado',
        'editar' => 'Editar',
        'bloquear' => 'Bloquear',
        'desbloquear' => 'Desbloquear',
        'eliminar_accion' => 'Eliminar',
        'confirmar_baja' => '¿Confirmas la baja de este usuario?',
        'paginacion_aria' => 'Paginación de usuarios',
        'paginacion_anterior' => 'Anterior',
        'paginacion_siguiente' => 'Siguiente',
        'paginacion_info' => 'Página :actual de :total',

        // Formulario
        'titulo_crear' => 'Nuevo usuario',
        'titulo_editar' => 'Editar usuario',
        'subtitulo_form' => 'Datos de acceso, persona asociada y roles del usuario.',
        'seccion_datos' => 'Datos del usuario',
        'campos_contador' => ':cantidad campos',
        'campo_name' => 'Nombre completo',
        'campo_username' => 'Usuario',
        'campo_email' => 'Correo electrónico',
        'campo_email_ayuda' => 'Opcional. Correo de la cuenta — no el de la persona ni el del cliente.',
        'campo_password' => 'Contraseña',
        'campo_password_ayuda_alta' => 'La asigna quien da de alta la cuenta. Con un correo cargado, la persona puede después recuperarla sola desde "Recupera tu acceso".',
        'campo_password_ayuda_edicion' => 'Déjalo vacío para conservar la contraseña actual.',
        'campo_persona' => 'Persona asociada',
        'campo_persona_placeholder' => 'Sin persona asociada',
        'campo_roles' => 'Roles',
        'campo_roles_ayuda' => 'Selecciona uno o más roles. Se reemplaza el conjunto completo al guardar.',
        'estado_form' => 'Los cambios se guardan al confirmar.',

        // Tipo de cuenta (tarea 65, HU-41): interna (con roles) o de portal
        // (con contrato). Solo se elige en el alta — ver docblock del
        // partial del formulario.
        'campo_tipo' => 'Tipo de cuenta',
        'campo_tipo_ayuda_edicion' => 'No se puede cambiar: una cuenta no muta de interna a cliente ni al revés.',
        'tipo_interno' => 'Interna',
        'tipo_cliente' => 'Cliente (portal)',
        'seccion_interno' => 'Persona y roles',
        'seccion_portal' => 'Contrato del portal',
        'campo_cliente' => 'Cliente',
        'campo_cliente_placeholder' => 'Elige un cliente',
        'campo_cliente_ayuda' => 'Filtra el contrato de abajo — no se guarda como dato propio de la cuenta.',
        'campo_contrato' => 'Contrato',
        'campo_contrato_placeholder' => 'Elige un contrato vigente',
        'campo_contrato_opcion' => ':cliente — :hectareas ha',
        'volver' => 'Volver a usuarios',
        'error_type_requerido' => 'Elige el tipo de cuenta.',
        'error_name_requerido' => 'Ingresa el nombre completo del usuario.',
        'error_username_requerido' => 'Ingresa el usuario de la cuenta.',
        'error_password_requerido' => 'Ingresa una contraseña para la cuenta.',
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
        'vacio_titulo' => 'Todavía no hay dispositivos con sesión',
        'vacio_detalle' => 'Los dispositivos aparecen acá cuando la app de campo se usa en un equipo. Vuelve a esta pantalla después de tu primer vuelo.',
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

        'tabs_aria' => 'Secciones de organización',
        'tab_organizacion' => 'Organización',
        'tab_facturacion' => 'Facturación',

        'campos_contador' => ':cantidad campos',

        // Pestaña Facturación (tarea 78, HU-55): datos fiscales REALES, no mock.
        'seccion_facturacion' => 'Datos fiscales',
        'campo_razon_social_fiscal' => 'Razón social fiscal',
        'campo_nit' => 'NIT',
        'campo_domicilio_fiscal' => 'Domicilio fiscal',
        'campo_actividad_economica' => 'Actividad económica',
        'campo_leyenda_pie' => 'Leyenda al pie de la factura',
        'campo_leyenda_pie_ayuda' => 'Texto legal o comercial que se imprime al pie del documento de factura.',
        'facturacion_estado' => 'Se usa al emitir el PDF de factura cuando ese punto de consumo exista.',
        'facturacion_guardada' => 'Datos fiscales actualizados.',

        // "Datos de empresa"/"Datos de contacto" (salvo el logo): REALES desde el
        // 11/9/2026, ver OrganizacionController::actualizarEmpresa().
        'seccion_datos_empresa' => 'Datos de empresa',
        'campo_nombre' => 'Nombre de empresa',
        'campo_rubro' => 'Rubro',
        'campo_logo' => 'Logo de empresa',
        'campo_logo_reemplazar' => 'Reemplazar',
        'campo_logo_quitar' => 'Quitar',
        'campo_logo_ayuda' => 'PNG, JPG, WEBP, GIF o SVG, hasta 20 MB. Lo optimizamos automáticamente. Fondo transparente recomendado.',
        'empresa_estado' => 'Nombre, rubro y contacto visibles en el panel.',
        'empresa_guardada' => 'Datos de la empresa actualizados.',
        'error_logo_tipo' => 'El logo debe ser PNG, JPG, WEBP, GIF o SVG.',
        'error_logo_tamano' => 'El logo no puede pesar más de 20 MB.',
        'error_logo_subida' => 'No se pudo subir el archivo. Prueba de nuevo con una imagen más liviana.',
        'error_nombre_requerido' => 'Ingresa el nombre de la empresa.',
        'error_rubro_requerido' => 'Ingresa el rubro de la empresa.',
        'error_razon_social_fiscal_requerida' => 'Ingresa la razón social fiscal.',
        'error_nit_requerido' => 'Ingresa el NIT.',
        'error_domicilio_fiscal_requerido' => 'Ingresa el domicilio fiscal.',
        'error_actividad_economica_requerida' => 'Ingresa la actividad económica.',

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

        'mock_renueva_fecha' => '01/10/2026',
        'mock_dispositivos_valor' => '6 / 10',
    ],

    // Perfil propio (tarea 66): autoservicio de nombre/correo/contraseña,
    // compartido por `/panel/perfil` (guard interno) y `/portal/perfil`
    // (guard cliente) — un solo copy para las dos pantallas, mismo formulario.
    'perfil' => [
        'menu_item' => 'Mi perfil',
        'titulo' => 'Mi perfil',
        'subtitulo' => 'Tus datos de acceso — correo y contraseña.',
        'actualizado' => 'Tus datos se actualizaron correctamente.',
        'seccion_datos' => 'Datos de la cuenta',
        'campo_name' => 'Nombre completo',
        'campo_name_ayuda' => 'Tu nombre queda en la bitácora de todo lo que haces, así que lo cambia un administrador desde Seguridad › Usuarios.',
        'campo_email' => 'Correo electrónico',
        'seccion_password' => 'Cambiar contraseña',
        'seccion_password_ayuda' => 'Deja estos tres campos vacíos si no quieres cambiarla.',
        'campo_password_actual' => 'Contraseña actual',
        'campo_password_nueva' => 'Contraseña nueva',
        'campo_password_nueva_ayuda' => 'Mínimo 8 caracteres. Cambiarla cierra tu sesión en otros dispositivos.',
        'campo_password_confirmacion' => 'Confirmar contraseña nueva',
        'estado_form' => 'Los cambios se guardan al confirmar.',
        'error_name_requerido' => 'Ingresa tu nombre completo.',
    ],

    // Tarea 63 (invariante 9 de CLAUDE.md): pantalla `/panel/bitacora`.
    // `entidades` mapea nombre FÍSICO de tabla → nombre legible: es el
    // catálogo que consultan `ListarBitacora`/`BitacoraController` para el
    // filtro por entidad y la columna de la tabla — cubre toda tabla que hoy
    // lleva `RegistraBitacora` (ver `tests/Unit/BitacoraAuditoriaTest.php` y
    // los modelos con `use RegistraBitacora;`). Una tabla nueva que sume el
    // trait entra acá cuando se construya su pantalla, no antes: hasta
    // entonces el filtro cae al nombre físico (`Lang::has()` con fallback,
    // ver `ListarBitacora::nombreLegibleTabla()`), nunca a un error.
    'bitacora' => [
        'titulo' => 'Bitácora',
        'subtitulo' => 'Quién hizo qué, cuándo y en qué zona horaria.',

        'columna_instante' => 'Instante',
        'columna_usuario' => 'Usuario',
        'columna_entidad' => 'Entidad',
        'columna_accion' => 'Acción',
        'columna_detalle' => 'Detalle',

        'actor_sistema' => 'Sistema',
        'registrado_en' => 'registrado en :zona',
        'ver_detalle' => 'Ver detalle',
        'ocultar_detalle' => 'Ocultar detalle',
        'diff_campo' => 'Campo',
        'diff_antes' => 'Antes',
        'diff_despues' => 'Después',
        'diff_sin_datos' => 'Sin datos antes/después para esta fila.',
        'filtro_vacio_titulo' => 'Sin resultados para este filtro',
        'filtro_vacio_detalle' => 'No hay movimientos que coincidan con los filtros aplicados. Prueba quitando alguno.',
        'vacio_titulo' => 'Todavía no hay movimientos registrados',
        'vacio_detalle' => 'Cada acción en el panel (crear, actualizar, eliminar) queda anotada acá. Vuelve a esta pantalla después de tus primeros cambios.',

        'filtro_usuario' => 'Usuario',
        'filtro_usuario_todos' => 'Todos',
        'filtro_entidad' => 'Entidad',
        'filtro_entidad_todas' => 'Todas',
        'filtro_accion' => 'Acción',
        'filtro_accion_todas' => 'Todas',
        'filtro_desde' => 'Desde',
        'filtro_hasta' => 'Hasta',
        'filtro_registro_id' => 'ID de registro',
        'filtro_limpiar' => 'Limpiar filtros',
        'filtro_aplicar' => 'Filtrar',
        'paginacion_aria' => 'Paginación de bitácora',

        'acciones' => [
            'creado' => 'Creado',
            'actualizado' => 'Actualizado',
            'eliminado' => 'Eliminado',
        ],

        'entidades' => [
            'com_campos' => 'Campos',
            'com_cliente_contactos' => 'Contactos de cliente',
            'com_clientes' => 'Clientes',
            'com_contrato_ventanas' => 'Ventanas de contrato',
            'com_contratos' => 'Contratos',
            'com_cultivos' => 'Cultivos',
            'com_facturas' => 'Facturas',
            'com_lote_campania' => 'Lote en campaña',
            'com_lotes' => 'Lotes',
            'cpn_campanias' => 'Campañas',
            'dis_versiones_apk' => 'Versiones de la app de campo',
            'fin_anticipos' => 'Anticipos',
            'fin_combustibles' => 'Cargas de combustible',
            'fin_devengos_personal' => 'Devengos',
            'fin_gastos' => 'Gastos',
            'fin_planilla_detalles' => 'Detalles de planilla',
            'fin_planillas' => 'Planillas',
            'fin_rendiciones' => 'Rendiciones',
            'inv_movimientos' => 'Movimientos de stock',
            'inv_repuestos' => 'Repuestos',
            'man_baterias' => 'Baterías',
            'man_generadores' => 'Generadores',
            'man_ordenes_mantenimiento' => 'Órdenes de mantenimiento',
            'man_planes_mantenimiento' => 'Planes de mantenimiento',
            'man_vehiculos' => 'Vehículos',
            'ope_actas' => 'Actas de conformidad',
            'ope_alertas' => 'Alertas',
            'ope_categorias_insumo' => 'Categorías de insumo',
            'ope_drones' => 'Drones',
            'ope_estadias_hacienda' => 'Estadías en hacienda',
            'ope_ordenes_aplicacion' => 'Órdenes de aplicación',
            'ope_sesion_rechazos' => 'Rechazos de sesión',
            'ope_sesiones' => 'Sesiones',
            'ope_trabajos' => 'Trabajos',
            'per_bases' => 'Bases',
            'per_equipo_integrantes' => 'Integrantes de equipo',
            'per_equipo_recursos' => 'Recursos de equipo',
            'per_equipos_trabajo' => 'Equipos de trabajo',
            'per_personas' => 'Personas',
            'plt_configuraciones' => 'Configuración del sistema',
            'sec_datos_fiscales' => 'Datos fiscales',
            'sec_permission' => 'Permisos',
            'sec_role' => 'Roles',
            'sec_role_permission' => 'Asignación de permisos a rol',
            'sec_user' => 'Usuarios',
            'sec_user_role' => 'Asignación de roles a usuario',
        ],
    ],

    // Mensajes de error.
    'errores' => [
        'contrasena_actual_incorrecta' => 'La contraseña actual no es correcta.',
        'contrato_no_disponible' => 'El contrato #:id no existe o no está vigente.',
        'dispositivo_no_encontrado' => 'El dispositivo #:id_dispositivo no existe entre los del usuario #:id_usuario.',
        'emision_directa_token' => 'Los tokens de dispositivo no se emiten con createToken(): usa :caso_uso, que registra el dispositivo y el rol activo con el que opera.',
        'idioma_no_soportado' => "El idioma ':idioma' no está habilitado todavía.",
        'permiso_denegado' => "Falta el permiso ':permiso' para completar esta acción.",
        'rol_duplicado' => "Ya existe un rol con el nombre ':nombre'. Un rol dado de baja tampoco libera su nombre: el catálogo de roles es del sistema.",
        'rol_no_asignado' => 'El usuario #:id_usuario no tiene asignado (o ya no tiene vivo) el rol #:id_rol.',
        'rol_protegido_ultima_llave' => "No se puede dejar el sistema sin ningún rol activo que tenga ':permiso': sería la última llave, y nadie podría volver a otorgarla desde el panel.",
        'rol_protegido_rol_activo_propio' => "No puedes quitarle ':permiso' al rol con el que estás operando: perderías el acceso a esta pantalla en el próximo clic.",
        'rol_protegido_ultimo_portador' => "':permiso' quedaría sin ningún rol activo que lo tenga. Otórgaselo antes a otro rol: nadie puede conceder un permiso que no tiene, así que un permiso huérfano no se recupera desde el panel.",
        'rol_protegido_tiene_usuarios_uno' => "El rol ':rol' tiene :cantidad usuario asignado. Reasígnalos antes de darlo de baja.",
        'rol_protegido_tiene_usuarios_varios' => "El rol ':rol' tiene :cantidad usuarios asignados. Reasígnalos antes de darlo de baja.",
        'rol_protegido_es_rol_activo' => "No puedes dar de baja ni desactivar ':rol': es el rol con el que estás operando.",
        'usuario_duplicado_username' => "Ya existe una cuenta activa con el username ':username'.",
        'usuario_duplicado_persona' => 'La persona #:persona_id ya tiene una cuenta activa — una persona operativa, una sola cuenta.',
        'usuario_duplicado_email' => "Ya existe una cuenta activa con el correo ':email'.",
        'zona_horaria_invalida' => "':identificador' no es un identificador de zona horaria IANA válido.",
    ],

    // Mensajes de los formularios.
    'validacion' => [
        'usuario_username_unico' => 'Ya existe una cuenta activa con ese username.',
        'usuario_email_unico' => 'Ya existe una cuenta activa con ese correo.',
        'usuario_persona_invalida' => 'La persona seleccionada no es válida.',
        'usuario_persona_prohibida_portal' => 'Una cuenta de portal no tiene persona asociada.',
        'usuario_contrato_requerido' => 'Elige el contrato de la cuenta de portal.',
        'usuario_contrato_prohibido_interno' => 'Una cuenta interna no tiene contrato asociado.',
        'usuario_contrato_invalido' => 'El contrato elegido no existe o no está vigente.',
        'usuario_roles_requerido' => 'Falta el campo de roles.',
        'usuario_roles_prohibido_portal' => 'Una cuenta de portal no tiene roles.',
        'usuario_rol_invalido' => 'Uno de los roles seleccionados no es válido.',
        'perfil_password_actual_requerida' => 'Ingresa tu contraseña actual para poder cambiarla.',
        'perfil_password_confirmacion' => 'La confirmación no coincide con la contraseña nueva.',
    ],

    // Respuestas del sistema.
    'respuestas' => [
        'sesion_finalizada' => 'Sesión finalizada.',
        'rol_dispositivo_requerido' => 'Debes indicar con qué rol opera este dispositivo.',
        'rol_activo_requerido' => 'Debes seleccionar un rol activo antes de continuar.',
        'badge_ordenes_vigentes' => ':cantidad vigentes',
        'badge_contratos_en_ejecucion' => ':cantidad en ejecución',
        'badge_sesiones_pendientes' => ':cantidad sin validar',
        'badge_pausas_mes' => ':cantidad este mes',
        'badge_stock_bajo_minimo' => ':cantidad bajo mínimo',
    ],

];

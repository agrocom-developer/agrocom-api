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

    // Copy del panel editorial de templates/auth-layout (foto + wordmark +
    // headline) — compartido por login y selección de rol, ambos armados
    // sobre ese mismo template. El wordmark reutiliza `ui.logo.alt`
    // ("Agrocom"), no hace falta una clave nueva para eso.
    'auth' => [
        'headline' => 'Cada hectárea, medida y tratada con precisión.',
    ],

    'login' => [
        'titulo' => 'Ingresá a tu panel',
        'subtitulo' => 'Gestión de fumigación con drones — Agrocom SRL',
        'campo_usuario' => 'Usuario',
        'campo_password' => 'Contraseña',
        'boton_ingresar' => 'Iniciar sesión',
        'boton_ingresando' => 'Ingresando…',
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
    ],

    // Copy mínimo de las páginas placeholder de HU-02 (GET /panel/dashboard,
    // GET /panel/usuarios) — `frontend` lo reemplaza al ensamblar el
    // contenido real de cada pantalla.
    'dashboard' => [
        'titulo' => 'Panel',
        'bienvenida' => 'Hola, :nombre. Estás operando como :rol — el menú de la izquierda muestra solo lo que tu rol activo puede ver.',
    ],

    'usuarios' => [
        'titulo' => 'Usuarios',
        'proximamente' => 'Próximamente: gestión de usuarios.',
    ],

];

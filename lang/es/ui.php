<?php

/*
 * Claves de traducción del sistema de diseño (atoms/molecules/organisms/
 * templates del catálogo Atomic Design), distintas de las claves de texto
 * específicas de cada pantalla de negocio (ADR 0013 punto pendiente,
 * resuelto acá: `lang/es/ui.php` es del catálogo de componentes; el texto
 * propio de una pantalla —copy del login, nombres de rol, etc.— va en un
 * archivo por módulo/pantalla, p. ej. `lang/es/seguridad.php`, a cargo de
 * quien construya esa pantalla).
 */

return [

    'logo' => [
        'alt' => 'Agrocom',
    ],

    'input' => [
        'show_password' => 'Mostrar contraseña',
        'hide_password' => 'Ocultar contraseña',
    ],

    'theme' => [
        'light' => 'Tema claro',
        'dark' => 'Tema oscuro',
        'toggle' => 'Cambiar tema',
    ],

    'action' => [
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'continue' => 'Continuar',
        'close' => 'Cerrar',
    ],

    // Chrome genérico del panel (organisms/sidebar-nav, organisms/topbar,
    // HU-02): acciones de la cáscara de navegación, reutilizables en
    // cualquier pantalla — no específicas de una pantalla de negocio, por
    // eso viven acá y no en seguridad.php (ver sistema_diseno_panel.md §5).
    'sidebar' => [
        'open' => 'Abrir menú',
        'close' => 'Cerrar menú',
        'collapse' => 'Colapsar menú',
        'expand' => 'Expandir menú',
    ],

    'footer' => [
        'copyright' => 'Agrocom SRL — :year',
    ],

];

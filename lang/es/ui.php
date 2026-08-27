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

];

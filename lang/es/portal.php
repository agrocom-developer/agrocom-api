<?php

/*
 * Copy de las pantallas del portal del cliente (HU-41, tarea 55): avance,
 * actas firmadas y reportes técnicos — distinto de `lang/es/comercial.php`/
 * `operaciones.php` porque el vocabulario acá se le habla al CLIENTE, no al
 * personal interno (sin mención de "trabajo"/"orden", conceptos internos que
 * el cliente no ve, espec §13).
 */

return [

    'chrome' => [
        'titulo' => 'Portal del cliente',
        'nav_aria_label' => 'Navegación del portal',
        'nav_avance' => 'Avance',
        'nav_actas' => 'Actas',
        'nav_reportes' => 'Reportes',
        'cerrar_sesion' => 'Cerrar sesión',
    ],

    'login' => [
        'titulo' => 'Ingresa al portal',
        'subtitulo' => 'Usa el usuario que te asignó tu contacto en Agrocom.',
    ],

    'avance' => [
        'titulo' => 'Avance',
        'subtitulo' => 'Hectáreas contratadas, aplicadas y monto facturado de tu contrato.',
        'card_contratadas' => 'Hectáreas contratadas',
        'card_aplicadas' => 'Hectáreas aplicadas',
        'card_facturado' => 'Monto facturado',
        'hectareas_valor' => ':cantidad ha',
        'monto_valor' => '$ :monto',
        'vacio' => 'Todavía no hay avance registrado para tu contrato.',
    ],

    'actas' => [
        'titulo' => 'Actas firmadas',
        'subtitulo' => 'Actas de conformidad firmadas de tu contrato.',
        'col_acta' => 'Acta',
        'col_hectareas' => 'Hectáreas conformadas',
        'col_descarga' => 'PDF',
        'acta_valor' => 'Acta #:id',
        'hectareas_valor' => ':cantidad ha',
        'descargar' => 'Descargar',
        'sin_pdf' => 'Sin PDF',
        'vacio' => 'Todavía no hay actas firmadas para tu contrato.',
    ],

    'reportes' => [
        'titulo' => 'Reportes técnicos',
        'subtitulo' => 'Reportes técnicos por lote de tu contrato.',
        'col_lote' => 'Lote',
        'col_generado' => 'Generado',
        'col_descarga' => 'PDF',
        'lote_valor' => 'Lote #:id',
        'descargar' => 'Descargar',
        'sin_pdf' => 'Sin PDF',
        'vacio' => 'Todavía no hay reportes técnicos para tu contrato.',
    ],

];

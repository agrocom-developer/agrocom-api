<?php

/*
 * Documentos legales públicos: términos y condiciones, y política de
 * privacidad (`docs/politicas/legal.md`).
 *
 * Este archivo es el ÚNICO lugar donde viven la versión y la fecha de
 * vigencia de cada documento, y los datos de identidad y de contacto del
 * titular. Las páginas públicas (`GET /terminos`, `GET /privacidad`) las leen
 * de acá, y el registro de aceptación por cuenta las tomará de acá también:
 * nunca se repiten en una vista ni en un texto.
 *
 * La versión se escribe en el código a propósito (no en `.env`): cambiarla es
 * un cambio de texto legal y tiene que pasar por un PR. Los datos de contacto
 * y de la empresa sí entran por entorno: todavía no están definidos y las
 * vistas omiten, sin marcas visibles, lo que sea nulo.
 */

return [

    /*
     * Un bloque por documento, con versión y vigencia propias para que
     * actualizar uno no obligue a volver a aceptar el otro.
     *
     * - `version`: `AAAA-N` (año y número de versión dentro del año).
     * - `vigente_desde`: fecha ISO (`AAAA-MM-DD`) desde la que rige esa versión.
     */
    'documentos' => [
        'terminos' => [
            'version' => '2026-2',
            'vigente_desde' => '2026-11-27',
        ],
        'privacidad' => [
            'version' => '2026-2',
            'vigente_desde' => '2026-11-27',
        ],
    ],

    /*
     * Meses que se conservan la selfie y el documento de verificación cuando
     * hubo antecedentes o una baja administrativa; luego se eliminan. Lo usa el
     * texto de la política de privacidad (marcador `:meses`).
     */
    'conservacion_verificacion_meses' => 12,

    /*
     * Días que se conservan la selfie y el documento de quien eliminó su cuenta
     * sin antecedentes (marcador `:dias_verificacion`). Es el mismo valor que
     * `verificacion.retencion_eliminacion_dias` de la eliminación de cuenta.
     */
    'conservacion_verificacion_dias_sin_antecedentes' => 90,

    /* Días de arrepentimiento al pedir la eliminación (`config/eliminacion.php`). */
    'eliminacion_gracia_dias' => (int) env('ELIMINACION_GRACIA_DIAS', 7),

    /* Plazo máximo para completar la eliminación (`config/eliminacion.php`). */
    'eliminacion_plazo_dias' => (int) env('ELIMINACION_PLAZO_DIAS', 30),

    /* Días que rotan los respaldos (`respaldos.retencion_dias`). */
    'respaldos_dias' => 30,

    /* Meses que se conservan los mensajes y las fotos de un servicio tras cerrar el plazo de reclamo. */
    'conservacion_servicio_meses' => 12,

    /* Días que se conserva la ubicación de una emergencia tras cerrar la incidencia. */
    'conservacion_emergencia_dias' => 90,

    /*
     * Producto y titular. `producto` es el nombre visible; `titular` es quien
     * responde por los datos y presta la plataforma; `sitio` y `sitio_empresa`
     * son los dominios públicos.
     */
    'producto' => env('LEGAL_PRODUCTO', 'DronAgro'),
    'titular' => env('LEGAL_TITULAR', 'Agrocom'),
    'sitio' => env('LEGAL_SITIO', 'https://drones.agrocom.com.bo'),
    'sitio_empresa' => env('LEGAL_SITIO_EMPRESA', 'https://agrocom.com.bo'),

    /* Domicilio del titular. */
    'direccion' => env(
        'LEGAL_DIRECCION',
        'Ciudad Comercio, pasillo J, oficina 229, Santa Cruz de la Sierra, Bolivia (código plus 6VP9+48 Santa Cruz de la Sierra)'
    ),

    /*
     * Datos de la empresa que todavía no están definidos. Nulos: la vista los
     * omite. `jurisdiccion` es la ciudad cuyos tribunales resuelven.
     */
    'empresa' => [
        'razon_social' => env('LEGAL_RAZON_SOCIAL'),
        'nit' => env('LEGAL_NIT'),
        'representante' => env('LEGAL_REPRESENTANTE'),
        'jurisdiccion' => env('LEGAL_JURISDICCION'),
    ],

    /* Canales de contacto y soporte. Nulos: la vista los omite. */
    'contacto' => [
        'correo' => env('LEGAL_CONTACTO_CORREO'),
        'telefono' => env('LEGAL_CONTACTO_TELEFONO'),
        'whatsapp' => env('LEGAL_CONTACTO_WHATSAPP'),
    ],

    /*
     * Redes sociales oficiales donde se avisan los cambios de los documentos:
     * `red => nombre de la cuenta` (por ejemplo `facebook`, `instagram`,
     * `tiktok`). Nulos: la vista los omite.
     */
    'redes' => [
        'facebook' => env('LEGAL_RED_FACEBOOK'),
        'instagram' => env('LEGAL_RED_INSTAGRAM'),
        'tiktok' => env('LEGAL_RED_TIKTOK'),
        'youtube' => env('LEGAL_RED_YOUTUBE'),
    ],

];

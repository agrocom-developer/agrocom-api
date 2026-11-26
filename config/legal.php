<?php

/*
 * Documentos legales públicos: términos y condiciones, y política de
 * privacidad (`docs/politicas/legal.md`).
 *
 * Este archivo es el ÚNICO lugar donde viven la versión y la fecha de
 * vigencia de cada documento. Las páginas públicas (`GET /terminos`,
 * `GET /privacidad`) las leen de acá, y el registro de aceptación por cuenta
 * las tomará de acá también: nunca se repiten en una vista ni en un texto.
 *
 * La versión se escribe en el código a propósito (no en `.env`): cambiarla es
 * un cambio de texto legal y tiene que pasar por un PR.
 */

return [

    /*
     * Aviso «BORRADOR: pendiente de revisión legal». Activado por defecto:
     * un abogado debe revisar y aprobar los textos antes de publicarlos en las
     * tiendas. Se apaga con `LEGAL_BORRADOR=false` en el entorno que publica la
     * versión aprobada.
     */
    'borrador' => (bool) env('LEGAL_BORRADOR', true),

    /*
     * Un bloque por documento, con versión y vigencia propias para que
     * actualizar uno no obligue a volver a aceptar el otro.
     *
     * - `version`: `AAAA-N` (año y número de versión dentro del año).
     * - `vigente_desde`: fecha ISO (`AAAA-MM-DD`) desde la que rige esa versión.
     */
    'documentos' => [
        'terminos' => [
            'version' => '2026-1',
            'vigente_desde' => '2026-11-26',
        ],
        'privacidad' => [
            'version' => '2026-1',
            'vigente_desde' => '2026-11-26',
        ],
    ],

    /*
     * Meses que se conservan la selfie y el documento de verificación después
     * de dar de baja una cuenta; luego se eliminan. Lo usa el texto de la
     * política de privacidad.
     */
    'conservacion_verificacion_meses' => 12,

];

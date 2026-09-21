<?php

/*
 * Módulo Distribucion (HU-20): autorizar versiones del APK de
 * agrocom-field. Archivo propio (no dentro de seguridad.php): la pantalla
 * vive gateada bajo el grupo de menú "seguridad" (no hay módulo raíz propio
 * en la espec §4 — ver runs/10-diseno.md), pero el vocabulario es de
 * Distribucion, no de Seguridad.
 */

return [

    'versiones' => [
        'titulo' => 'Versiones del APK',
        'subtitulo' => 'Autoriza la versión del APK que va a recibir cada dispositivo de campo. Solo puede haber una vigente a la vez.',
        'col_version' => 'Versión',
        'col_version_code' => 'Código',
        'col_estado' => 'Estado',
        'estado_pendiente' => 'Pendiente',
        'estado_autorizada' => 'Autorizada',
        'estado_rechazada' => 'Rechazada',
        'autorizar' => 'Autorizar',
        'confirmar_autorizar_titulo' => 'Autorizar la versión',
        'confirmar_autorizar' => '¿Confirmas autorizar la versión :version? Los dispositivos de campo recibirán esta versión como la vigente y la que estaba autorizada vuelve a Pendiente.',
        'estado_cambio_de_a' => 'Cambio de estado de la versión: de :desde a :hacia',
        'paginacion_aria' => 'Paginación de versiones del APK',
        'campos_contador' => ':cantidad campos',
        'autorizada' => 'La versión quedó autorizada. La anterior dejó de ser la vigente.',
        'subir' => 'Subir versión',
        'subida' => 'La versión se subió correctamente, pendiente de autorización.',
        'vacio_titulo' => 'Todavía no se subió ninguna versión',
        'vacio_detalle' => 'Las versiones del APK de agrocom-field se registran a partir del release en GitHub. Se sube una nueva desde el formulario arriba.',
        'campo_version' => 'Versión (SemVer)',
        'campo_version_code' => 'Código de versión (Android)',
        'campo_url_apk' => 'URL del release en agrocom-field',
    ],

    // Mensajes de error.
    'errores' => [
        'transicion_version_apk_no_permitida' => "No se puede pasar una versión de APK de ':desde' a ':hasta'.",
    ],

    // Mensajes de los formularios.
    'validacion' => [
        'version_formato' => 'La versión debe seguir el formato SemVer (por ejemplo: 1.4.2).',
        'url_apk_https' => 'La URL debe ser una dirección https válida.',
        'version_requerida' => 'Ingresa la versión del APK.',
        'version_code_requerido' => 'Ingresa el código de versión.',
        'url_apk_requerida' => 'Ingresa la URL del release.',
    ],

];

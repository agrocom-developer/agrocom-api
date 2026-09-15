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
        'subtitulo' => 'Autorizá la versión del APK que va a recibir cada dispositivo de campo. Solo puede haber una vigente a la vez.',
        'col_version' => 'Versión',
        'col_version_code' => 'Código',
        'col_estado' => 'Estado',
        'col_accion' => 'Acción',
        'estado_pendiente' => 'Pendiente',
        'estado_autorizada' => 'Autorizada',
        'estado_rechazada' => 'Rechazada',
        'autorizar' => 'Autorizar',
        'autorizada' => 'La versión quedó autorizada. La anterior dejó de ser la vigente.',
        'subir' => 'Subir versión',
        'subida' => 'La versión se subió correctamente, pendiente de autorización.',
        'vacio_titulo' => 'Todavía no se subió ninguna versión',
        'vacio_detalle' => 'Las versiones del APK de agrocom-field se registran a partir del release en GitHub. Se sube una nueva desde el formulario arriba.',
        'campo_version' => 'Versión (SemVer)',
        'campo_version_code' => 'Código de versión (Android)',
        'campo_url_apk' => 'URL del release en agrocom-field',
    ],

];

<?php

use App\Dominios\Proveedores\Dominio\RequisitosDocumentales;

/*
|--------------------------------------------------------------------------
| Proveedores del marketplace (ADR 0032 y 0036)
|--------------------------------------------------------------------------
|
| Parámetros de la verificación de proveedores, pilotos y drones.
| «El catálogo de documentos se ajusta por parámetro» (política de verificación):
| la normativa local de una región puede pedir, por ejemplo, el certificado de
| aplicador de agroquímicos al piloto. Los valores de aquí son el piso; no hay
| secretos.
|
*/

return [
    /*
     * Disco privado de los documentos. Nunca `public`: solo administradores y
     * soporte los ven (política de verificación, «Privacidad»), y se sirven por
     * un controlador con permiso, no por URL.
     */
    'disco_documentos' => env('PROVEEDORES_DISCO_DOCUMENTOS', 'local'),

    /* Tamaño máximo de un documento, en kilobytes. */
    'documento_max_kb' => (int) env('PROVEEDORES_DOCUMENTO_MAX_KB', 8192),

    /* Extensiones aceptadas para un documento. */
    'documento_extensiones' => ['pdf', 'jpg', 'jpeg', 'png'],

    /*
     * Documentos obligatorios por sujeto. Solo se aprueba a un sujeto que
     * tiene cada uno de estos documentos aprobado y vigente.
     */
    'documentos_requeridos' => [
        'proveedor' => array_values(array_filter(explode(',', (string) env('PROVEEDORES_DOCS_PROVEEDOR', implode(',', RequisitosDocumentales::POR_DEFECTO['proveedor']))))),
        'piloto' => array_values(array_filter(explode(',', (string) env('PROVEEDORES_DOCS_PILOTO', implode(',', RequisitosDocumentales::POR_DEFECTO['piloto']))))),
        'dron' => array_values(array_filter(explode(',', (string) env('PROVEEDORES_DOCS_DRON', implode(',', RequisitosDocumentales::POR_DEFECTO['dron']))))),
    ],

    /*
     * Separación de funciones: quien registra a mano un proveedor, piloto o
     * dron en el panel no lo aprueba él mismo. `false` lo permite (decisión del dueño).
     */
    'separar_funciones' => (bool) env('PROVEEDORES_SEPARAR_FUNCIONES', true),
];

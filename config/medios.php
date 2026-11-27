<?php

/*
|--------------------------------------------------------------------------
| Archivos multimedia de las personas (fotos y documentos)
|--------------------------------------------------------------------------
|
| Todo archivo que sube una persona (documentos de identidad y licencia, fotos
| del dron, del terreno, de avance, del chat y de perfil) pasa por
| `AlmacenDeMedios`: la base guarda solo la ruta RELATIVA dentro del disco
| (nunca una URL ni una ruta absoluta del servidor) y la URL se compone al
| responder. Nada de esto es un secreto.
|
*/

return [
    /*
     * Disco privado de los medios. Nunca `public`: se sirven por un controlador
     * con permiso o por una URL firmada de corta vida.
     */
    'disco' => env('MEDIOS_DISCO', 'local'),

    /*
     * Límites de archivos multimedia (los lee la app en `GET /api/medios/limites`).
     *
     * - `por_subida`: cuántos archivos puede traer UNA petición de subida.
     * - `por_entidad`: cuántos archivos activos puede tener a la vez cada entidad
     *   dueña, por categoría: fotos de un dron, fotos de un terreno, documentos
     *   EN REVISIÓN de un proveedor, piloto o dron (el historial aprobado no
     *   cuenta), fotos de avance de un servicio y fotos del chat de un servicio.
     *   Pasarse responde 422 con el mensaje de la categoría.
     */
    'limites' => [
        'por_subida' => (int) env('MEDIOS_LIMITE_POR_SUBIDA', 10),
        'por_entidad' => [
            'dron_fotos' => (int) env('MEDIOS_LIMITE_DRON_FOTOS', 10),
            'terreno_fotos' => (int) env('MEDIOS_LIMITE_TERRENO_FOTOS', 10),
            'documentos' => (int) env('MEDIOS_LIMITE_DOCUMENTOS', 10),
            'servicio_avance' => (int) env('MEDIOS_LIMITE_SERVICIO_AVANCE', 10),
            'servicio_chat' => (int) env('MEDIOS_LIMITE_SERVICIO_CHAT', 10),
        ],
    ],

    /*
     * Derivados reducidos de las imágenes. El original se conserva intacto; la
     * miniatura (cuadrículas y listas) y el tamaño medio (vista previa) pesan
     * poco. `lado` es el lado mayor en píxeles; `calidad`, de 1 a 100. Se
     * generan en cola (`cola`) y con `php artisan medios:regenerar`.
     */
    'derivados' => [
        'cola' => env('MEDIOS_COLA', 'medios'),
        'megapixeles_maximos' => (int) env('MEDIOS_MEGAPIXELES_MAXIMOS', 40),
        'miniatura' => ['lado' => 320, 'calidad' => 70],
        'media' => ['lado' => 960, 'calidad' => 78],
    ],

    /* Imagen que se muestra cuando el archivo ya no está en el disco (relativa a `public/`). */
    'placeholder' => 'images/medio-no-disponible.svg',
];

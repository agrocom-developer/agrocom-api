<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Evidencias y reportes en almacenamiento S3-compatible (ADR 0009).
        //
        // Sin `R2_BUCKET` configurado cae a un disco local en
        // `storage/app/r2`, con la MISMA clave `r2`: la aplicación pide
        // siempre `Storage::disk('r2')` y nunca sabe cuál de los dos le
        // tocó. Es el entorno de desarrollo — el `docker-compose.yml` no
        // levanta un S3 y `.env.example` deja las cinco variables de R2
        // vacías, así que hasta ahora toda evidencia sembrada existía como
        // fila pero devolvía 404 al abrirla (`Storage::disk('r2')->exists()`
        // contra un bucket inexistente, con `throw => false`, es
        // indistinguible de "el archivo no está").
        //
        // El disco local NO es público: su raíz vive fuera de `public/`, así
        // que sigue sirviéndose por streaming desde
        // `panel.evidencias.archivo` con el permiso verificado — el mismo
        // camino que en staging y producción, que es justo lo que hace útil
        // probarlo en local. Staging/producción setean `R2_BUCKET` y usan S3
        // sin enterarse de que esta rama existe.
        'r2' => env('R2_BUCKET') === null || env('R2_BUCKET') === ''
            ? [
                'driver' => 'local',
                'root' => storage_path('app/r2'),
                'serve' => false,
                'throw' => false,
                'report' => false,
            ]
            : [
                'driver' => 's3',
                'key' => env('R2_ACCESS_KEY_ID'),
                'secret' => env('R2_SECRET_ACCESS_KEY'),
                'region' => 'auto',
                'bucket' => env('R2_BUCKET'),
                'url' => env('R2_URL'),
                'endpoint' => env('R2_ENDPOINT'),
                'use_path_style_endpoint' => false,
                'throw' => false,
                'report' => false,
            ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catálogo de claves de configuración del sistema (tarea 78, HU-55)
    |--------------------------------------------------------------------------
    |
    | Llaves y tokens con que el sistema se parametriza — separado a propósito
    | de los datos de la empresa (`/panel/organizacion`, pestaña Facturación,
    | otra mitad de la misma tarea). `/panel/configuracion` pinta esta lista
    | agrupada por `grupo`, cruzada con lo que haya en `plt_configuraciones`.
    |
    | `respaldo` es la ruta de `config()` que responde cuando NO hay fila en la
    | base para esa clave (resolución en cascada: la base manda si existe,
    | `config()`/`.env` es el respaldo — nunca al revés). `null` significa que
    | no hay valor por defecto: la ausencia no rompe nada, el consumidor sigue
    | funcionando sin ese dato (p. ej. sin llave de Google Maps, el sistema
    | sigue con Leaflet + Esri).
    |
    | El sector `integraciones` nace vacío a propósito (ver abajo): no se
    | inventan integraciones que nadie pidió.
    |
    */

    'claves' => [
        // `tipo`/`valor_activado` (default 'texto' si se omiten): la pantalla
        // los usa para decidir qué control pintar (`ListarConfiguracionPorGrupo`,
        // `configuracion/index.blade.php`) — este es hoy el único `switch` del
        // catálogo porque es el único caso binario real (forzar Leaflet sí/no).
        'mapas.proveedor_preferido' => [
            'grupo' => 'mapas',
            'es_secreto' => false,
            'respaldo' => null,
            'tipo' => 'switch',
            'valor_activado' => 'leaflet',
        ],
        'mapas.google_maps_api_key' => [
            'grupo' => 'mapas',
            'es_secreto' => true,
            'respaldo' => null,
        ],

        // Respaldo en el Mailpit de desarrollo (tarea 66, `config/mail.php` +
        // `.env.example`): sin fila en la base, el sistema sigue enviando
        // correo con lo que ya está en `.env`.
        'correo.host' => [
            'grupo' => 'correo',
            'es_secreto' => false,
            'respaldo' => 'mail.mailers.smtp.host',
        ],
        'correo.puerto' => [
            'grupo' => 'correo',
            'es_secreto' => false,
            'respaldo' => 'mail.mailers.smtp.port',
        ],
        'correo.usuario' => [
            'grupo' => 'correo',
            'es_secreto' => false,
            'respaldo' => 'mail.mailers.smtp.username',
        ],
        'correo.password' => [
            'grupo' => 'correo',
            'es_secreto' => true,
            'respaldo' => 'mail.mailers.smtp.password',
        ],
        'correo.remitente' => [
            'grupo' => 'correo',
            'es_secreto' => false,
            'respaldo' => 'mail.from.address',
        ],
    ],

    // Sectores de la pantalla, en el orden en que se pintan. `integraciones`
    // sin ninguna clave en el catálogo de arriba: la pantalla lo muestra
    // vacío, "listo para crecer" (pedido explícito de la tarea 78).
    'grupos' => ['mapas', 'correo', 'integraciones'],

];

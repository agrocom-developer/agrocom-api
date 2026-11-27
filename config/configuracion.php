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

        // Correo saliente (códigos de verificación, recuperar el acceso, restablecer
        // del panel). Sin fila en la base, el sistema sigue enviando con lo que ya
        // está en `.env` (Mailpit en desarrollo, `config/mail.php`). Con `correo.host`
        // en la base, la base manda en todo el grupo (`ConfiguracionDeCorreo`).
        // `proveedor` y `cifrado` son selectores: `opciones` son los únicos valores
        // aceptados (la validación sale de acá). El valor de `password` es una
        // contraseña de aplicación: secreto, cifrado en reposo y nunca se muestra.
        'correo.proveedor' => [
            'grupo' => 'correo',
            'es_secreto' => false,
            'respaldo' => null,
            'tipo' => 'select',
            'opciones' => ['gmail', 'outlook', 'otro'],
            'predeterminado' => 'gmail',
        ],
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
        'correo.cifrado' => [
            'grupo' => 'correo',
            'es_secreto' => false,
            'respaldo' => null,
            'tipo' => 'select',
            'opciones' => ['tls', 'ssl', 'ninguno'],
            'predeterminado' => 'tls',
        ],
        'correo.usuario' => [
            'grupo' => 'correo',
            'es_secreto' => false,
            'respaldo' => 'mail.mailers.smtp.username',
        ],
        'correo.password' => [
            'grupo' => 'correo',
            'es_secreto' => true,
            'sin_ultimos4' => true,
            'respaldo' => 'mail.mailers.smtp.password',
        ],
        'correo.remitente' => [
            'grupo' => 'correo',
            'es_secreto' => false,
            'respaldo' => 'mail.from.address',
        ],
        'correo.remitente_nombre' => [
            'grupo' => 'correo',
            'es_secreto' => false,
            'respaldo' => null,
            'predeterminado' => 'DronAgro',
        ],
    ],

    // Presets de proveedor de correo: la pantalla los vuelca en host, puerto y cifrado
    // al elegir uno (`otro` deja los campos libres). Gmail usa contraseña de aplicación.
    'proveedores_correo' => [
        'gmail' => ['host' => 'smtp.gmail.com', 'puerto' => '587', 'cifrado' => 'tls'],
        'outlook' => ['host' => 'smtp.office365.com', 'puerto' => '587', 'cifrado' => 'tls'],
        'otro' => ['host' => '', 'puerto' => '', 'cifrado' => 'tls'],
    ],

    // Sectores de la pantalla, en el orden en que se pintan. `integraciones`
    // sin ninguna clave en el catálogo de arriba: la pantalla lo muestra
    // vacío, "listo para crecer" (pedido explícito de la tarea 78).
    'grupos' => ['mapas', 'correo', 'integraciones'],

];

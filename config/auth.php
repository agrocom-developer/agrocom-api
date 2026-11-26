<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioApp;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;

/*
|--------------------------------------------------------------------------
| Autenticación — sec_user, sin App\Models\User (HU-01)
|--------------------------------------------------------------------------
| `sec_user` es la única fuente de identidad, con una tabla física y dos mundos
| separados por `tipo_cuenta` (ADR 0046): el panel autentica solo cuentas
| `panel` (guard `interno`) y la API de las apps solo cuentas `cliente` o
| `proveedor` (guard `sanctum`, token por dispositivo). Cada provider filtra su
| tipo con un global scope, así ningún guard autentica una cuenta que no le
| corresponde. Las cuentas de app no tienen sesión web: el portal web del
| cliente se retiró y, si se crea, será otra superficie con su propio ADR.
| Login por username + password (memoria del proyecto) — el email nunca es
| credencial de ingreso. La recuperación de contraseña por enlace es solo del
| panel (broker `interno`); las cuentas de app recuperan su acceso desde la app.
*/

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'interno'),
        'passwords' => 'interno',
    ],

    'guards' => [
        // Panel web: personal de Agrocom (administrador y operador).
        'interno' => [
            'driver' => 'session',
            'provider' => 'usuarios_internos',
        ],

        // Apps móviles (`agrocom-field`): token por dispositivo, sin sesión
        // (HU-03, ADR 0008). El provider NO es opcional aunque Sanctum lo
        // admita nulo: con `usuarios_app`, `Guard::hasValidProvider()`
        // comprueba que el dueño del token sea una cuenta de app, así que una
        // cuenta de panel jamás autentica en `/api/*` ni siquiera si alguien
        // le emitiera un token por error.
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'usuarios_app',
        ],
    ],

    'providers' => [
        'usuarios_internos' => [
            'driver' => 'eloquent',
            'model' => SecUsuarioInterno::class,
        ],

        'usuarios_app' => [
            'driver' => 'eloquent',
            'model' => SecUsuarioApp::class,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    // Recuperación de contraseña por correo (tarea 66, ADR 0004 ampliación
    // 9/9/2026), solo para el panel. 60 min de expiración, 60 s de throttle por
    // email (además del `throttle` de ruta por IP).
    'passwords' => [
        'interno' => [
            'provider' => 'usuarios_internos',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

];

<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioCliente;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;

/*
|--------------------------------------------------------------------------
| Autenticación — sec_user, sin App\Models\User (HU-01)
|--------------------------------------------------------------------------
|
| `App\Models\User` y la tabla `users` del esqueleto de Laravel se eliminaron
| (HU-01, diseño `modulos-roles` §6): `sec_user` es la única fuente de
| identidad, con dos guards que comparten la misma tabla física filtrada por
| `type` (ver SecUsuarioInterno/SecUsuarioCliente) para que ninguno de los
| dos autentique jamás una cuenta que no le corresponde.
|
| Login por username + password (memoria del proyecto) — el email nunca es
| credencial de ingreso. Sí hay recuperación de contraseña por correo desde
| la tarea 66 (ADR 0004, ampliación 9/9/2026): dos brokers, uno por guard,
| ver `passwords` más abajo.
|
*/

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'interno'),
        'passwords' => 'interno',
    ],

    'guards' => [
        // Panel web y apps de campo (piloto, auxiliar, jefe de campo,
        // encargado de operaciones, dueño).
        'interno' => [
            'driver' => 'session',
            'provider' => 'usuarios_internos',
        ],

        // Portal del cliente (ADR 0002 punto 6, ADR 0004 — scoping por
        // contrato, invariante 5 de CLAUDE.md). Consumido desde HU-41
        // (tarea 55): SesionPortalController + rutas /portal/* en
        // routes/web.php.
        'cliente' => [
            'driver' => 'session',
            'provider' => 'usuarios_cliente',
        ],

        // Apps de campo (`agrocom-field`): token por dispositivo, sin sesión
        // (HU-03, ADR 0008). El provider NO es opcional aunque Sanctum lo
        // admita nulo: con `usuarios_internos`, `Guard::hasValidProvider()`
        // comprueba que el dueño del token sea una cuenta interna, así que
        // una cuenta de portal jamás autentica en `/api/*` ni siquiera si
        // alguien le emitiera un token por error.
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'usuarios_internos',
        ],
    ],

    'providers' => [
        'usuarios_internos' => [
            'driver' => 'eloquent',
            'model' => SecUsuarioInterno::class,
        ],

        'usuarios_cliente' => [
            'driver' => 'eloquent',
            'model' => SecUsuarioCliente::class,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    // Tarea 66 (ADR 0004, ampliación 9/9/2026): un broker por guard, misma
    // tabla física para los dos (ver docblock de la migración
    // `create_password_reset_tokens_table`). 60 min de expiración, 60 s de
    // throttle por email (además del `throttle` de ruta por IP).
    'passwords' => [
        'interno' => [
            'provider' => 'usuarios_internos',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'cliente' => [
            'provider' => 'usuarios_cliente',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

];

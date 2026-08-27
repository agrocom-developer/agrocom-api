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
| Sin broker de reset de contraseña por correo: el login es username +
| password (memoria del proyecto), no hay flujo de "olvidé mi contraseña"
| por email en este sistema.
|
*/

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'interno'),
        'passwords' => null,
    ],

    'guards' => [
        // Panel web y apps de campo (piloto, auxiliar, jefe de campo,
        // encargado de operaciones, dueño).
        'interno' => [
            'driver' => 'session',
            'provider' => 'usuarios_internos',
        ],

        // Portal del cliente (ADR — scoping por contrato, invariante 5 de
        // CLAUDE.md). Sin consumidores todavía; declarado para no dejar dos
        // sistemas de auth en paralelo cuando llegue esa HU.
        'cliente' => [
            'driver' => 'session',
            'provider' => 'usuarios_cliente',
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

];
